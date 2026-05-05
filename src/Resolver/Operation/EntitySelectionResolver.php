<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use JorisDugue\EasyAdminExtraBundle\Dto\Operation\EntitySelection;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;

final readonly class EntitySelectionResolver
{
    private const DECIMAL_IDENTIFIER_TYPES = ['integer', 'smallint', 'bigint'];
    private const CASTABLE_INTEGER_IDENTIFIER_TYPES = ['integer', 'smallint'];

    /**
     * @param list<int|string> $ids
     */
    public function resolve(array $ids): EntitySelection
    {
        if ([] === $ids) {
            throw InvalidBatchExportException::emptySelection();
        }

        return new EntitySelection(
            ids: array_values($ids),
        );
    }

    /**
     * @param list<int|string> $ids
     *
     * @return list<int|string>
     */
    public function castIds(array $ids, string $identifierType, ?string $entityFqcn = null): array
    {
        if (\in_array($identifierType, self::DECIMAL_IDENTIFIER_TYPES, true)) {
            $invalidIds = array_values(array_filter(
                $ids,
                static fn (int|string $id): bool => 1 !== preg_match('/^[+-]?\d+$/', (string) $id),
            ));

            if ([] !== $invalidIds) {
                throw InvalidBatchExportException::invalidIdentifierValues($entityFqcn ?? '[unknown]', $identifierType, $invalidIds);
            }
        }

        return array_values(array_map(
            static fn (int|string $id): int|string => \in_array($identifierType, self::CASTABLE_INTEGER_IDENTIFIER_TYPES, true)
                ? (int) $id
                : (string) $id,
            $ids,
        ));
    }
}
