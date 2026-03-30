<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Config;

use InvalidArgumentException;

final class ExportFormat
{
    public const string CSV = 'csv';
    public const string XLSX = 'xlsx';
    public const string JSON = 'json';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CSV,
            self::XLSX,
            self::JSON,
        ];
    }

    public static function isSupported(string $format): bool
    {
        $format = self::normalizeRaw($format);

        return \in_array($format, self::all(), true);
    }

    public static function normalize(string $format): string
    {
        $format = self::normalizeRaw($format);

        if (!\in_array($format, self::all(), true)) {
            throw new InvalidArgumentException(\sprintf('Unsupported export format "%s". Supported formats are: %s.', $format, implode(', ', self::all())));
        }

        return $format;
    }

    /**
     * @param list<string> $formats
     *
     * @return list<string>
     */
    public static function normalizeMany(array $formats): array
    {
        $normalized = [];

        foreach ($formats as $format) {
            $normalized[] = self::normalize($format);
        }

        return array_values(array_unique($normalized));
    }

    private static function normalizeRaw(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }
}
