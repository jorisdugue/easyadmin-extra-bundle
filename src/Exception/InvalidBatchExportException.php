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

    public static function invalidEntityFqcn(string $expectedEntityFqcn, ?string $postedEntityFqcn): self
    {
        return new self(\sprintf(
            'Invalid batch export entity FQCN. Expected "%s", got "%s".',
            $expectedEntityFqcn,
            null === $postedEntityFqcn || '' === trim($postedEntityFqcn) ? '[missing]' : $postedEntityFqcn,
        ));
    }

    /**
     * @param list<int|string> $invalidIds
     */
    public static function invalidIdentifierValues(string $entityFqcn, string $identifierType, array $invalidIds): self
    {
        return new self(\sprintf(
            'Invalid batch export identifier value(s) for entity "%s" with "%s" identifier: %s.',
            $entityFqcn,
            $identifierType,
            implode(', ', array_map(static fn (int|string $id): string => \sprintf('"%s"', (string) $id), $invalidIds)),
        ));
    }

    public static function missingEntityManager(string $entityFqcn): self
    {
        return new self(\sprintf(
            'Batch export cannot resolve Doctrine metadata for entity "%s": no Doctrine entity manager handles this class.',
            $entityFqcn,
        ));
    }

    public static function missingRootAlias(): self
    {
        return new self('Unable to resolve the root alias for the batch export query builder. Ensure the selection query builder has a root alias (e.g. "entity").');
    }
}
