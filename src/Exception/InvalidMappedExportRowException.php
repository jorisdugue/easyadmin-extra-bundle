<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class InvalidMappedExportRowException extends EasyAdminExtraException
{
    /**
     * @param list<string> $missingKeys
     * @param list<string> $expectedKeys
     * @param list<string> $actualKeys
     */
    public static function missingProperties(array $missingKeys, array $expectedKeys, array $actualKeys): self
    {
        $missingPart = 1 === \count($missingKeys)
            ? \sprintf('Custom export row mapper is missing key "%s".', $missingKeys[0])
            : \sprintf('Custom export row mapper is missing keys: %s.', implode(', ', $missingKeys));

        return new self(\sprintf(
            '%s Expected keys: %s. Returned keys: %s.',
            $missingPart,
            implode(', ', $expectedKeys),
            implode(', ', $actualKeys),
        ));
    }
}
