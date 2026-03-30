<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

/**
 * Allows custom mapping of one exported row.
 *
 * The returned array must be keyed by export property name.
 * Every configured export property must be present in the returned array.
 *
 * Missing properties trigger an exception during payload normalization.
 * Extra properties are ignored.
 *
 * @phpstan-type ExportRow array<string, mixed>
 */
interface CustomExportRowMapperInterface
{
    /**
     * @return array<string, mixed> the mapped row indexed by export property name
     */
    public function mapExportRow(object $entity): array;
}
