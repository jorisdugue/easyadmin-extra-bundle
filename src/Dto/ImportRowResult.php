<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportRowResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public int $rowNumber,
        public bool $success,
        public array $errors = [],
    ) {}
}
