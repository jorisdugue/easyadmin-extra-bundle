<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;

final readonly class ExportPreview
{
    /**
     * @param list<string> $headers
     * @param list<list<mixed>> $rows
     * @param array<string,string> $formatLabels
     */
    public function __construct(
        public string $format,
        public string $scope,
        public string $entityName,
        public int $limit,
        public array $headers,
        public array $rows,
        public bool $showFormatPreviewActions,
        public ExportActionDisplay $actionDisplay,
        public array $formatLabels,
    ) {}

    public function hasRows(): bool
    {
        return [] !== $this->rows;
    }
}
