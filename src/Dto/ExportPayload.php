<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ExportPayload
{
    /**
     * @param list<string> $headers
     * @param list<string> $properties
     * @param iterable<list<string>> $rows
     */
    public function __construct(
        public string $filename,
        public string $format,
        public array $headers,
        public array $properties,
        public iterable $rows,
        /**
         * Whether spreadsheet formulas are allowed in exported values.
         *
         * Applies only to CSV and XLSX exports.
         * Has no effect for JSON exports.
         */
        public bool $allowSpreadsheetFormulas,
    ) {}
}
