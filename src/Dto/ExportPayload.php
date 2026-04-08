<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ExportPayload
{
    /**
     * @param list<string> $headers
     * @param list<string> $properties
     * @param iterable<list<mixed>> $rows
     * @param bool $allowSpreadsheetFormulas Whether spreadsheet formulas are allowed
     *                                       in exported values. Applies only to CSV
     *                                       and XLSX exports, and has no effect for JSON.
     */
    public function __construct(
        public string $filename,
        public string $format,
        public array $headers,
        public array $properties,
        public iterable $rows,
        public bool $allowSpreadsheetFormulas,
    ) {}
}
