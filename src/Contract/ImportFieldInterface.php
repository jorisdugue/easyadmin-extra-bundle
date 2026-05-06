<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportFieldDto;

interface ImportFieldInterface
{
    public static function new(string $propertyName, ?string $label = null): static;

    public function getAsDto(): ImportFieldDto;
}
