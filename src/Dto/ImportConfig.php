<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;

final readonly class ImportConfig
{
    /**
     * @param list<ImportFieldInterface> $fields
     */
    public function __construct(
        public array $fields,
        public ?string $importSet = null,
    ) {}
}
