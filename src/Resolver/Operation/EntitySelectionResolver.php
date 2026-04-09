<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use JorisDugue\EasyAdminExtraBundle\Dto\Operation\EntitySelection;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;

final readonly class EntitySelectionResolver
{
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
    public function castIds(array $ids, string $identifierType): array
    {
        return array_values(array_map(
            static fn (int|string $id): int|string => \in_array($identifierType, ['integer', 'smallint', 'bigint'], true)
                ? (int) $id
                : (string) $id,
            $ids,
        ));
    }
}
