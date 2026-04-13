<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Config;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;

final readonly class ExportConfig
{
    /**
     * @param list<ExportFieldInterface> $fields
     * @param list<string> $requiredRoles
     * @param list<string> $formats
     */
    public function __construct(
        public string $filename,
        public array $fields,
        public array $formats = ['csv'],
        public bool $fullExport = true,
        public bool $filteredExport = true,
        public ?int $maxRows = 50000,
        public ?string $requiredRole = null,
        public array $requiredRoles = [],
        public string $csvLabel = 'Export CSV',
        public string $xlsxLabel = 'Export Excel',
        public string $jsonLabel = 'Export JSON',
        public string $xmlLabel = 'Export XML',
        public bool $allowSpreadsheetFormulas = false,
        public ?string $routeName = null,
        public ?string $routePath = null,
        public ExportActionDisplay $actionDisplay = ExportActionDisplay::BUTTONS,
        public bool $previewEnabled = false,
        public int $previewLimit = 20,
        public string $previewLabel = 'Preview export',
        public bool $batchExport = true,
        public string $batchExportLabel = 'Export selection',
    ) {}

    public function getDefaultFormat(): string
    {
        return $this->formats[0];
    }

    public function useDropdown(): bool
    {
        return ExportActionDisplay::DROPDOWN === $this->actionDisplay;
    }

    public function supportsFormat(string $format): bool
    {
        return \in_array(self::normalizeFormat($format), $this->formats, true);
    }

    private static function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }

    public function getLabelForFormat(string $format): string
    {
        return match (self::normalizeFormat($format)) {
            ExportFormat::CSV => $this->csvLabel,
            ExportFormat::JSON => $this->jsonLabel,
            ExportFormat::XLSX => $this->xlsxLabel,
            ExportFormat::XML => $this->xmlLabel,
            default => throw new InvalidArgumentException(\sprintf('Unsupported export format "%s".', $format)),
        };
    }
}
