<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Util;

use Stringable;

/**
 * Utility to safely convert mixed values to string.
 *
 * This helper is intentionally conservative:
 * - null returns an empty string
 * - non-stringable values return an empty string
 */
final class ValueStringifier
{
    /**
     * Converts a mixed value to string when possible.
     */
    public static function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return '';
    }

    /**
     * Converts an iterable of values into a string.
     *
     * Each element is stringified using {@see self::stringify()}.
     *
     * @param iterable<mixed> $values
     */
    public static function stringifyIterable(iterable $values, string $separator = ','): string
    {
        $parts = [];

        foreach ($values as $value) {
            $parts[] = self::stringify($value);
        }

        return implode($separator, $parts);
    }
}
