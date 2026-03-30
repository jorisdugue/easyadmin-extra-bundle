<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Util;

use InvalidArgumentException;

/**
 * Central utility for converting controller class names to route names/paths.
 * Used by both the route loader and the action extension to guarantee consistency.
 */
final class ControllerNaming
{
    private static function normalize(string $value, string $separator, string $suffixToTrim): string
    {
        $value = trim($value);

        if ('' === $value) {
            throw new InvalidArgumentException('ControllerNaming expects a non-empty string.');
        }

        if (str_contains($value, '\\')) {
            $value = substr($value, strrpos($value, '\\') + 1);
        }

        if ('' !== $suffixToTrim && str_ends_with($value, $suffixToTrim)) {
            $value = substr($value, 0, -\strlen($suffixToTrim));
        }

        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1' . $separator . '$2', $value) ?? $value;
        $value = preg_replace('/([A-Z])([A-Z][a-z])/', '$1' . $separator . '$2', $value) ?? $value;

        return strtolower($value);
    }

    public static function toKebabCase(string $value, string $suffixToTrim = ''): string
    {
        return self::normalize($value, '-', $suffixToTrim);
    }

    public static function toSnakeCase(string $value, string $suffixToTrim = ''): string
    {
        return self::normalize($value, '_', $suffixToTrim);
    }
}
