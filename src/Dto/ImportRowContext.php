<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportRowContext
{
    /**
     * @param array<string, string|null> $rawRow
     */
    public function __construct(
        public int $rowNumber,
        public string $header,
        public string $property,
        public array $rawRow,
    ) {}
}
