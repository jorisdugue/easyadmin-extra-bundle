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
        public string $csvLabel = 'Export CSV',
        public string $xlsxLabel = 'Export Excel',
        public string $jsonLabel = 'Export JSON',
        public bool $allowSpreadsheetFormulas = false,
        public ?string $routeName = null,
        public ?string $routePath = null,
        public ExportActionDisplay $actionDisplay = ExportActionDisplay::BUTTONS,
    ) {}

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
}
