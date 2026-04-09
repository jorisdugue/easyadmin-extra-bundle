<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Config;

use InvalidArgumentException;

final class ExportFormat
{
    /**
     * @var string
     */
    public const CSV = 'csv';
    /**
     * @var string
     */
    public const XLSX = 'xlsx';
    /**
     * @var string
     */
    public const JSON = 'json';

    public const XML = 'xml';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CSV,
            self::XLSX,
            self::JSON,
            self::XML,
        ];
    }

    public static function isSupported(string $format): bool
    {
        try {
            return \in_array(self::normalizeRaw($format), self::all(), true);
        } catch (InvalidArgumentException) {
            return false;
        }
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
            $candidate = self::normalize($format);

            if (!\in_array($candidate, $normalized, true)) {
                $normalized[] = $candidate;
            }
        }

        return $normalized;
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
