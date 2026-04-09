<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class InvalidBatchExportException extends EasyAdminExtraException
{
    public static function emptySelection(): self
    {
        return new self('Batch export requires at least one selected entity ID. Ensure batchActionEntityIds[] is submitted with one or more values.');
    }

    public static function compositeIdentifiersNotSupported(string $entityFqcn): self
    {
        return new self(\sprintf(
            'Batch export is not supported for entity "%s": composite identifiers are not supported.',
            $entityFqcn,
        ));
    }

    public static function missingRootAlias(): self
    {
        return new self('Unable to resolve the root alias for the batch export query builder. Ensure the selection query builder has a root alias (e.g. "entity").');
    }
}
