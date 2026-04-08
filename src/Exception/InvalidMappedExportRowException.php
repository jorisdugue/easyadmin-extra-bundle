<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class InvalidMappedExportRowException extends EasyAdminExtraException
{
    /**
     * @param list<string> $expectedKeys
     * @param list<string> $actualKeys
     */
    public static function missingProperty(string $property, array $expectedKeys, array $actualKeys): self
    {
        return new self(sprintf(
            'The custom export row mapper did not return the "%s" key. Expected keys: %s. Returned keys: %s.',
            $property,
            implode(', ', $expectedKeys),
            implode(', ', $actualKeys),
        ));
    }
}