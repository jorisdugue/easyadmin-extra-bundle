<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use Doctrine\ORM\QueryBuilder;
use Generator;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportCountQueryBuilderInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportRowMapperInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use RuntimeException;

final readonly class ExportPayloadFactory
{
    public function __construct(
        private ExportFieldValueResolver $fieldValueResolver,
        private FilenameResolver $filenameResolver,
        private ExportFieldFormatResolver $exportFieldFormatResolver,
    ) {}

    /**
     * @param array<string, mixed> $mappedRow
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return list<mixed>
     */
    private function normalizeMappedRow(array $mappedRow, array $enabledFields): array
    {
        $normalized = [];

        foreach ($enabledFields as $field) {
            $property = $field->getAsDto()->getProperty();

            if (null === $property || '' === trim($property)) {
                throw new RuntimeException('An enabled export field is missing its property configuration.');
            }

            if (!\array_key_exists($property, $mappedRow)) {
                throw new RuntimeException(\sprintf('Custom export row mapper is missing key "%s". Expected keys: [%s]. Returned keys: [%s].', $property, implode(', ', array_map(static fn (ExportFieldInterface $f) => (string) $f->getAsDto()->getProperty(), $enabledFields)), implode(', ', array_keys($mappedRow))));
            }
            $normalized[] = $mappedRow[$property];
        }

        return $normalized;
    }

    /**
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return Generator<int, list<string>>
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

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }

    /**
     * @return list<list<mixed>>
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
     * @param list<ExportFieldInterface> $enabledFields
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function buildHeaders(array $enabledFields, string $format, array $roles = []): array
    {
        return array_map(
            fn (ExportFieldInterface $field): string => $this->exportFieldFormatResolver->resolveHeader($field->getAsDto(), $format, $roles),
            $enabledFields
        );
    }

    /**
     * @param list<ExportFieldInterface> $enabledFields
     *
     * @return list<string>
     */
    private function buildProperties(array $enabledFields): array
    {
        return array_map(
            static function (ExportFieldInterface $field): string {
                $property = $field->getAsDto()->getProperty();

                if (null === $property || '' === trim($property)) {
                    throw new RuntimeException('An enabled export field is missing its property configuration.');
                }

                return $property;
            },
            $enabledFields
        );
    }

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
            $count = $this->countRows($crudController, $queryBuilder);

            if ($count > $config->maxRows) {
                throw new RuntimeException(\sprintf('Export limited to %d rows, but %d rows were found. Use filters to reduce the selection.', $config->maxRows, $count));
            }
        }

        return new ExportPayload(
            filename: $this->filenameResolver->resolve($crudController, $config, $context),
            format: $context->format,
            headers: $headers,
            properties: $properties,
            rows: $this->generateRows($crudController, $queryBuilder, $enabledFields),
            allowSpreadsheetFormulas: $config->allowSpreadsheetFormulas
        );
    }

    /**
     * Counts the number of rows that would be exported.
     *
     * If the CRUD controller implements CustomExportCountQueryBuilderInterface,
     * its custom count QueryBuilder is used. Otherwise, the count is derived
     * from the main export QueryBuilder.
     */
    private function countRows(object $crudController, QueryBuilder $qb): int
    {
        if ($crudController instanceof CustomExportCountQueryBuilderInterface) {
            $countQb = $crudController->createExportCountQueryBuilder();

            return (int) $countQb->getQuery()->getSingleScalarResult();
        }

        $countQb = clone $qb;
        $aliases = $countQb->getRootAliases();

        if ([] === $aliases) {
            throw new RuntimeException('Unable to determine the root alias for export row counting.');
        }

        $alias = $aliases[0];

        return (int) $countQb
            ->resetDQLPart('orderBy')
            ->select(\sprintf('COUNT(%s)', $alias))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param list<ExportFieldInterface> $fields
     *
     * @return list<ExportFieldInterface>
     */
    private function sortFields(array $fields): array
    {
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
            }
        );

        return array_map(
            static fn (array $item): ExportFieldInterface => $item['field'],
            $decorated
        );
    }
}
