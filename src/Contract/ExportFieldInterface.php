<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;

interface ExportFieldInterface
{
    /**
     * Creates a new export field instance.
     */
    public static function new(string $propertyName, ?string $label = null): static;

    /**
     * Returns the field configuration as DTO.
     */
    public function getAsDto(): ExportFieldDto;
}
