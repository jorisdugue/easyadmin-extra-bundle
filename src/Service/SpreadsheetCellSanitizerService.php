<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service;

class SpreadsheetCellSanitizerService
{
    private const array DANGEROUS_PREFIXES = ['=', '+', '-', '@'];

    public function sanitizeValue(mixed $value, bool $allowSpreadsheetFormulas = false): string
    {
        $string = null === $value ? '' : (string) $value;

        if ($allowSpreadsheetFormulas) {
            return $string;
        }

        $trimmed = ltrim($string, " \t\n\r\0\x0B");

        if ('' !== $trimmed && \in_array($trimmed[0], self::DANGEROUS_PREFIXES, true)) {
            return "'" . $string;
        }

        return $string;
    }

    /**
     * @param iterable<mixed> $row
     *
     * @return list<string>
     */
    public function sanitizeRow(iterable $row, bool $allowSpreadsheetFormulas = false): array
    {
        $sanitized = [];
        foreach ($row as $value) {
            $sanitized[] = $this->sanitizeValue($value, $allowSpreadsheetFormulas);
        }

        return $sanitized;
    }
}
