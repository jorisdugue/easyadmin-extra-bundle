<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service;

use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;

class SpreadsheetCellSanitizerService
{
    /**
     * List of prefixes that can trigger spreadsheet formula execution.
     *
     * @see https://owasp.org/www-community/attacks/CSV_Injection
     *
     * @var list<string>
     */
    private const DANGEROUS_PREFIXES = ['=', '+', '-', '@'];

    /**
     * Sanitizes a value to prevent spreadsheet formula injection.
     *
     * When formulas are not allowed, any value starting with a dangerous prefix
     * (after trimming leading whitespace) will be prefixed with a single quote.
     *
     * Examples:
     * - "=SUM(A1:A2)" => "'=SUM(A1:A2)"
     * - " test" => " test" (safe)
     *
     * @param mixed $value Raw value to sanitize
     * @param bool  $allowSpreadsheetFormulas Whether to allow formulas (unsafe)
     *
     * @return string Sanitized value safe for CSV/XLSX export
     */
    public function sanitizeValue(mixed $value, bool $allowSpreadsheetFormulas = false): string
    {
        $string = ValueStringifier::stringify($value);

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
     * Sanitizes an entire row for safe spreadsheet export.
     *
     * Each value is sanitized using {@see sanitizeValue()}.
     *
     * @param iterable<mixed> $row Raw row values
     * @param bool            $allowSpreadsheetFormulas Whether to allow formulas (unsafe)
     *
     * @return list<string> Sanitized row values
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
