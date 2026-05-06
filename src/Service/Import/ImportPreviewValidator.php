<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use DateTime;
use DateTimeInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportRowContext;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceImportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateImportField;
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportFieldHeaderResolver;
use Throwable;

final readonly class ImportPreviewValidator
{
    public function __construct(private ImportFieldHeaderResolver $headerResolver) {}

    /**
     * @param list<string>             $headers
     * @param list<list<string|null>>  $rows
     * @param list<ImportPreviewIssue> $issues
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function validate(array $headers, array $rows, ImportConfig $config, bool $firstRowContainsHeaders, array &$issues): array
    {
        $this->validateConfiguration($config);

        $explicitPositionMode = $this->hasExplicitPositions($config);
        if ($explicitPositionMode && $this->hasUnpositionedImportableFields($config)) {
            throw InvalidImportConfigurationException::mixedExplicitAndSequentialMapping();
        }

        if (!$firstRowContainsHeaders || $explicitPositionMode) {
            return $this->validateRowsByPosition($headers, $rows, $config, $explicitPositionMode, $issues);
        }

        return $this->validateRowsByHeader($headers, $rows, $config, $issues);
    }

    /**
     * @param list<string>             $headers
     * @param list<list<string|null>>  $rows
     * @param list<ImportPreviewIssue> $issues
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function validateRowsByHeader(array $headers, array $rows, ImportConfig $config, array &$issues): array
    {
        $configuredHeaders = $this->resolveConfiguredHeaders($this->getImportableFields($config));
        $knownHeaders = $this->resolveConfiguredHeaders($config->fields);
        $headerIndexByName = $this->indexHeaders($headers);
        $knownHeaderNames = array_fill_keys($knownHeaders, true);

        foreach ($headers as $header) {
            if (!isset($knownHeaderNames[$header])) {
                $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, \sprintf('Unknown CSV column "%s" was ignored.', $header));
            }
        }

        foreach ($this->getImportableFields($config) as $index => $field) {
            $header = $configuredHeaders[$index];
            if ($field->getAsDto()->isRequired() && !\array_key_exists($header, $headerIndexByName)) {
                $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Required CSV column "%s" is missing.', $header));
            }
        }

        $previewRows = [];
        foreach ($rows as $rowIndex => $row) {
            $rawRow = $this->combineRow($headers, $row);
            $previewRow = [];

            foreach ($this->getImportableFields($config) as $index => $field) {
                $header = $configuredHeaders[$index];
                $rawValue = \array_key_exists($header, $headerIndexByName) ? ($row[$headerIndexByName[$header]] ?? null) : null;
                $value = $this->transformValue($field, $rawValue, $rowIndex + 1, $header, $rawRow, $issues);

                $previewRow[] = $this->validateValue($field, $value, $rowIndex + 1, $header, $issues);
            }

            $previewRows[] = $previewRow;
        }

        return [$configuredHeaders, $previewRows];
    }

    /**
     * @param list<string>             $sourceHeaders
     * @param list<list<string|null>>  $rows
     * @param list<ImportPreviewIssue> $issues
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    private function validateRowsByPosition(array $sourceHeaders, array $rows, ImportConfig $config, bool $explicitPositionMode, array &$issues): array
    {
        $mappings = $this->createPositionMappings($config, $explicitPositionMode);
        $configuredHeaders = array_values(array_map(static fn (array $mapping): string => $mapping['header'], array_filter(
            $mappings,
            static fn (array $mapping): bool => !$mapping['field'] instanceof IgnoredImportField,
        )));
        $previewRows = [];
        $mappedColumnIndexes = array_fill_keys(array_map(static fn (array $mapping): int => $mapping['columnIndex'], $mappings), true);
        $maxColumnCount = \count($sourceHeaders);

        foreach ($rows as $row) {
            $maxColumnCount = max($maxColumnCount, \count($row));
        }

        if (!$explicitPositionMode) {
            for ($columnIndex = 0; $columnIndex < $maxColumnCount; ++$columnIndex) {
                if (!isset($mappedColumnIndexes[$columnIndex])) {
                    $position = $columnIndex + 1;
                    foreach ($rows as $row) {
                        if (\array_key_exists($columnIndex, $row)) {
                            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, \sprintf('Extra CSV column at position %d was ignored.', $position));
                            break;
                        }
                    }

                    if ([] === $rows && \array_key_exists($columnIndex, $sourceHeaders)) {
                        $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, \sprintf('Extra CSV column at position %d was ignored.', $position));
                    }
                }
            }
        }

        foreach ($rows as $rowIndex => $row) {
            $rawRow = $this->combineRow($sourceHeaders, $row);
            $previewRow = [];

            foreach ($mappings as $mapping) {
                $field = $mapping['field'];
                if ($field instanceof IgnoredImportField) {
                    continue;
                }

                $header = $mapping['header'];
                $rawValue = $row[$mapping['columnIndex']] ?? null;
                $value = $this->transformValue($field, $rawValue, $rowIndex + 1, $header, $rawRow, $issues);

                $previewRow[] = $this->validateValue($field, $value, $rowIndex + 1, $header, $issues);
            }

            $previewRows[] = $previewRow;
        }

        return [$configuredHeaders, $previewRows];
    }

    /**
     * @param list<ImportFieldInterface> $fields
     *
     * @return list<string>
     */
    private function resolveConfiguredHeaders(array $fields): array
    {
        return array_map(fn (ImportFieldInterface $field): string => $this->headerResolver->resolve($field), $fields);
    }

    /**
     * @return list<ImportFieldInterface>
     */
    private function getImportableFields(ImportConfig $config): array
    {
        return array_values(array_filter(
            $config->fields,
            static fn (ImportFieldInterface $field): bool => !$field instanceof IgnoredImportField,
        ));
    }

    private function hasExplicitPositions(ImportConfig $config): bool
    {
        foreach ($config->fields as $field) {
            if (null !== $field->getAsDto()->getPosition()) {
                return true;
            }
        }

        return false;
    }

    private function hasUnpositionedImportableFields(ImportConfig $config): bool
    {
        foreach ($this->getImportableFields($config) as $field) {
            if (null === $field->getAsDto()->getPosition()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{field: ImportFieldInterface, header: string, columnIndex: int}>
     */
    private function createPositionMappings(ImportConfig $config, bool $explicitPositionMode): array
    {
        $mappings = [];
        $usedColumnIndexes = [];
        $nextColumnIndex = 0;

        foreach ($config->fields as $field) {
            $position = $field->getAsDto()->getPosition();
            if (null !== $position) {
                $columnIndex = $position - 1;
            } elseif ($explicitPositionMode) {
                continue;
            } else {
                while (isset($usedColumnIndexes[$nextColumnIndex])) {
                    ++$nextColumnIndex;
                }

                $columnIndex = $nextColumnIndex;
            }

            $usedColumnIndexes[$columnIndex] = true;
            $mappings[] = [
                'field' => $field,
                'header' => $this->headerResolver->resolve($field),
                'columnIndex' => $columnIndex,
            ];
        }

        return $mappings;
    }

    private function validateConfiguration(ImportConfig $config): void
    {
        $fieldsByPosition = [];

        foreach ($config->fields as $field) {
            $position = $field->getAsDto()->getPosition();
            if (null === $position) {
                continue;
            }

            $property = (string) ($field->getAsDto()->getProperty() ?? '');
            if (isset($fieldsByPosition[$position])) {
                throw InvalidImportConfigurationException::duplicateCsvColumnPosition($position, $fieldsByPosition[$position], $property);
            }

            $fieldsByPosition[$position] = $property;
        }
    }

    /**
     * @param list<string> $headers
     *
     * @return array<string, int>
     */
    private function indexHeaders(array $headers): array
    {
        $indexedHeaders = [];

        foreach ($headers as $index => $header) {
            if (!\array_key_exists($header, $indexedHeaders)) {
                $indexedHeaders[$header] = $index;
            }
        }

        return $indexedHeaders;
    }

    /**
     * @param list<string>      $headers
     * @param list<string|null> $row
     *
     * @return array<string, string|null>
     */
    private function combineRow(array $headers, array $row): array
    {
        $combined = [];

        foreach ($headers as $index => $header) {
            $combined[$header] = $row[$index] ?? null;
        }

        return $combined;
    }

    /**
     * @param array<string, string|null> $rawRow
     * @param list<ImportPreviewIssue>   $issues
     */
    private function transformValue(
        ImportFieldInterface $field,
        ?string $rawValue,
        int $rowNumber,
        string $header,
        array $rawRow,
        array &$issues,
    ): mixed {
        $transformer = $field->getAsDto()->getTransformer();
        if (null === $transformer) {
            return $rawValue;
        }

        try {
            return $transformer($rawValue, new ImportRowContext(
                rowNumber: $rowNumber,
                header: $header,
                property: (string) $field->getAsDto()->getProperty(),
                rawRow: $rawRow,
            ));
        } catch (Throwable) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The value could not be transformed.', $rowNumber, $header));

            return null;
        }
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function validateValue(ImportFieldInterface $field, mixed $value, int $rowNumber, string $header, array &$issues): mixed
    {
        if ($this->isEmptyValue($value)) {
            if ($field->getAsDto()->isRequired()) {
                $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": This value is required.', $rowNumber, $header));
            }

            return null;
        }

        if ($field instanceof ChoiceImportField) {
            return $this->validateChoiceValue($field, $value, $rowNumber, $header, $issues);
        }

        if ($field instanceof DateImportField) {
            return $this->validateDateValue($field, $value, $rowNumber, $header, $issues);
        }

        $displayValue = $this->normalizeDisplayValue($value);
        if (null === $displayValue) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The value is not supported for preview.', $rowNumber, $header));

            return null;
        }

        return $displayValue;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function validateChoiceValue(ChoiceImportField $field, mixed $value, int $rowNumber, string $header, array &$issues): ?string
    {
        $displayValue = $this->normalizeDisplayValue($value);
        if (null === $displayValue) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The selected value is not valid.', $rowNumber, $header));

            return null;
        }

        $choices = $field->getAsDto()->getCustomOption(ChoiceImportField::OPTION_CHOICES);
        $choices = \is_array($choices) ? $choices : [];

        if (!\array_key_exists($displayValue, $choices)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The selected value is not valid.', $rowNumber, $header));
        }

        return $displayValue;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function validateDateValue(DateImportField $field, mixed $value, int $rowNumber, string $header, array &$issues): ?DateTime
    {
        $format = $field->getAsDto()->getCustomOption(DateImportField::OPTION_FORMAT);
        $format = \is_string($format) && '' !== $format ? $format : 'Y-m-d';

        if ($value instanceof DateTimeInterface) {
            return DateTime::createFromInterface($value);
        }

        if (!\is_string($value)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The date value is not valid.', $rowNumber, $header));

            return null;
        }

        $date = DateTime::createFromFormat('!' . $format, $value);
        if (!$date instanceof DateTime || $date->format($format) !== $value) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('Row %d, field "%s": The date value is not valid.', $rowNumber, $header));

            return null;
        }

        return $date;
    }

    private function isEmptyValue(mixed $value): bool
    {
        return null === $value || (\is_string($value) && '' === trim($value));
    }

    private function normalizeDisplayValue(mixed $value): ?string
    {
        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (\is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }
}
