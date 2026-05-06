<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportResult
{
    /**
     * @param list<ImportRowResult> $rowResults
     * @param list<string>          $errors
     */
    public function __construct(
        public bool $success,
        public int $importedCount = 0,
        public int $failedCount = 0,
        public array $rowResults = [],
        public array $errors = [],
        public ?ImportPreview $preview = null,
        public ?TemporaryImportFile $temporaryFile = null,
    ) {}
}
