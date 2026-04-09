<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Attribute;

use Attribute;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AdminExport
{
    /**
     * @param list<string> $formats
     */
    public function __construct(
        public string $filename = 'export_{date}_{time}',
        public array $formats = ['csv'],
        public bool $fullExport = true,
        public bool $filteredExport = true,
        public ?int $maxRows = 50000,
        public ?string $requiredRole = null,
        public string $csvLabel = 'Export CSV',
        public string $xlsxLabel = 'Export Excel',
        public string $jsonLabel = 'Export JSON',
        public string $xmlLabel = 'Export XML',
        /**
         * Whether spreadsheet formulas should be allowed in exported files.
         *
         * When disabled (default), values starting with '=', '+', '-', or '@'
         * are sanitized to prevent spreadsheet formula injection.
         *
         * Enable this only if you fully trust the exported data.
         */
        public bool $allowSpreadsheetFormulas = false,
        public ?string $routeName = null,
        public ?string $routePath = null,
        public ?ExportActionDisplay $actionDisplay = null,
        public bool $previewEnabled = false,
        public int $previewLimit = 20,
        public string $previewLabel = 'Preview export',
        public bool $batchExport = true,
        public string $batchExportLabel = 'Export selection',
    ) {}
}
