<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportPreview
{
    /**
     * @param list<string>             $headers
     * @param list<list<string|null>>  $rows
     * @param list<ImportPreviewIssue> $issues
     */
    public function __construct(
        public ?string $filename,
        public string $format,
        public ?int $detectedRowCount,
        public array $headers,
        public array $rows,
        public array $issues,
    ) {}

    public function hasRows(): bool
    {
        return [] !== $this->rows;
    }

    public function hasIssues(): bool
    {
        return [] !== $this->issues;
    }
}
