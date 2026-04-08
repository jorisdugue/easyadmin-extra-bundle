<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use Doctrine\ORM\QueryBuilder;
use Generator;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportRowMapperInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exception\ExportLimitExceededException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidMappedExportRowException;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;

final readonly class ExportPayloadFactory
{
    public function __construct(
        private ExportFieldValueResolver $fieldValueResolver,
        private FilenameResolver $filenameResolver,
        private ExportFieldFormatResolver $exportFieldFormatResolver,
        private ExportCountResolverInterface $exportCountResolver,
    ) {}

    /**
     * Normalizes a custom mapped row returned by a CRUD controller into the
     * exact column order expected by the export payload.
     *
     * Every enabled field must expose a non-empty property name, and the mapped
     * row must contain a matching key for each enabled field.
     *
     * @param array<string, mixed> $mappedRow
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return list<mixed>
     */
    private function normalizeMappedRow(array $mappedRow, array $enabledFields): array
    {
        $normalized = [];
        $expectedKeys = [];

        foreach ($enabledFields as $field) {
            $property = $field->getAsDto()->getProperty();
            $label = $field->getAsDto()->getLabel();
            if (!\is_string($label) || '' === trim($label)) {
                $label = '[unnamed]';
            }

            if (null === $property || '' === trim($property)) {
                throw InvalidExportConfigurationException::missingFieldProperty($label);
            }

            $expectedKeys[] = $property;
        }

        /** @var list<string> $actualKeys */
        $actualKeys = array_values(array_map('strval', array_keys($mappedRow)));

        /** @var list<string> $missingKeys */
        $missingKeys = array_values(array_diff($expectedKeys, $actualKeys));

        if ([] !== $missingKeys) {
            throw InvalidMappedExportRowException::missingProperties($missingKeys, $expectedKeys, $actualKeys);
        }

        foreach ($expectedKeys as $property) {
            $normalized[] = $mappedRow[$property];
        }

        return $normalized;
    }

    /**
     * Generates export rows lazily from the export query.
     *
     * If the CRUD controller provides a custom row mapper, that mapper is used.
     * Otherwise, each enabled export field is resolved one by one.
     *
     * The EntityManager is cleared periodically to keep memory usage stable on
     * large exports.
     *
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return Generator<int, list<mixed>>
     */
    private function generateRows(
        object $crudController,
        QueryBuilder $qb,
        array $enabledFields,
    ): Generator {
        $batchSize = 500;
        $i = 0;
        $em = $qb->getEntityManager();

        foreach ($qb->getQuery()->toIterable() as $entity) {
            if (!\is_object($entity)) {
                continue;
            }

            if ($crudController instanceof CustomExportRowMapperInterface) {
                $mappedRow = $crudController->mapExportRow($entity);
                yield $this->normalizeMappedRow($mappedRow, $enabledFields);
            } else {
                $row = [];

                foreach ($enabledFields as $field) {
                    $row[] = $this->fieldValueResolver->resolve($entity, $field);
                }

                yield $row;
            }

            if (0 === (++$i % $batchSize)) {
                $em->clear();
            }
        }
    }

    /**
     * @throws InvalidArgumentException when the format is empty after normalization
     */
    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }

    /**
     * Creates a preview payload made of headers and a limited subset of rows.
     *
     * @return array{0: list<string>, 1: list<list<mixed>>}
     */
    public function createPreview(object $crudController, QueryBuilder $queryBuilder, ExportConfig $config, ExportContext $context, int $limit): array
    {
        $roles = $context->roles;
        $format = $this->normalizeFormat($context->format);
        $enabledFields = $this->resolveEnabledFields($config, $format, $roles);
        $rows = [];

        foreach ($this->generateRows($crudController, $queryBuilder, $enabledFields) as $row) {
            $rows[] = $row;
            if (\count($rows) >= $limit) {
                break;
            }
        }

        return [
            $this->buildHeaders($enabledFields, $format, $roles),
            $rows,
        ];
    }

    /**
     * Resolves the enabled export fields for the given format and role set, then
     * returns them sorted by configured position.
     *
     * @param list<string> $roles
     *
     * @return list<ExportFieldInterface>
     */
    private function resolveEnabledFields(ExportConfig $config, string $format, array $roles = []): array
    {
        $enabledFields = [];

        foreach ($config->fields as $field) {
            $dto = $field->getAsDto();

            if (!$dto->isEnabled()) {
                continue;
            }

            if (!$this->exportFieldFormatResolver->isVisible($dto, $format, $roles)) {
                continue;
            }

            $enabledFields[] = $field;
        }

        return $this->sortFields($enabledFields);
    }

    /**
     * Builds the header row for the given enabled fields.
     *
     * @param list<ExportFieldInterface> $enabledFields
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function buildHeaders(array $enabledFields, string $format, array $roles = []): array
    {
        return array_map(
            fn (ExportFieldInterface $field): string => $this->exportFieldFormatResolver->resolveHeader($field->getAsDto(), $format, $roles),
            $enabledFields,
        );
    }

    /**
     * Builds the ordered property list matching the enabled field order.
     *
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return list<string>
     */
    private function buildProperties(array $enabledFields): array
    {
        return array_map(
            static function (ExportFieldInterface $field): string {
                $property = $field->getAsDto()->getProperty();
                $label = $field->getAsDto()->getLabel();
                if (!\is_string($label) || '' === trim($label)) {
                    $label = '[unnamed]';
                }

                if (null === $property || '' === trim($property)) {
                    throw InvalidExportConfigurationException::missingFieldProperty($label);
                }

                return $property;
            },
            $enabledFields,
        );
    }

    /**
     * Creates the final export payload for a given CRUD controller and query.
     *
     * If a maxRows limit is configured, the row count is resolved before any
     * entity is streamed in memory.
     */
    public function create(
        object $crudController,
        QueryBuilder $queryBuilder,
        ExportConfig $config,
        ExportContext $context,
    ): ExportPayload {
        $roles = $context->roles;
        $format = $this->normalizeFormat($context->format);
        $enabledFields = $this->resolveEnabledFields($config, $format, $roles);
        $headers = $this->buildHeaders($enabledFields, $format, $roles);
        $properties = $this->buildProperties($enabledFields);

        // Guard: count BEFORE loading any entity into memory.
        if (null !== $config->maxRows) {
            $count = $this->exportCountResolver->count($queryBuilder, $crudController);

            if ($count > $config->maxRows) {
                throw ExportLimitExceededException::maxRowsExceeded($config->maxRows, $count);
            }
        }

        return new ExportPayload(
            filename: $this->filenameResolver->resolve($crudController, $config, $context),
            format: $context->format,
            headers: $headers,
            properties: $properties,
            rows: $this->generateRows($crudController, $queryBuilder, $enabledFields),
            allowSpreadsheetFormulas: $config->allowSpreadsheetFormulas,
        );
    }

    /**
     * Sorts export fields using the following rules:
     * - fields with an explicit position come first, sorted ascending;
     * - ties on position are resolved using the original declaration order;
     * - fields without position come last, preserving declaration order.
     *
     * @param list<ExportFieldInterface> $fields
     *
     * @return list<ExportFieldInterface>
     */
    private function sortFields(array $fields): array
    {
        /** @var list<array{index: int, position: int|null, field: ExportFieldInterface}> $decorated */
        $decorated = [];

        foreach ($fields as $index => $field) {
            $decorated[] = [
                'index' => $index,
                'position' => $field->getAsDto()->getPosition(),
                'field' => $field,
            ];
        }

        usort(
            $decorated,
            /**
             * @param array{index: int, position: int|null, field: ExportFieldInterface} $left
             * @param array{index: int, position: int|null, field: ExportFieldInterface} $right
             */
            static function (array $left, array $right): int {
                $leftPosition = $left['position'];
                $rightPosition = $right['position'];

                $leftHasPosition = null !== $leftPosition;
                $rightHasPosition = null !== $rightPosition;

                if ($leftHasPosition && $rightHasPosition) {
                    $positionComparison = $leftPosition <=> $rightPosition;

                    if (0 !== $positionComparison) {
                        return $positionComparison;
                    }

                    return $left['index'] <=> $right['index'];
                }

                if ($leftHasPosition) {
                    return -1;
                }

                if ($rightHasPosition) {
                    return 1;
                }

                return $left['index'] <=> $right['index'];
            },
        );

        return array_map(
            static fn (array $item): ExportFieldInterface => $item['field'],
            $decorated,
        );
    }
}
