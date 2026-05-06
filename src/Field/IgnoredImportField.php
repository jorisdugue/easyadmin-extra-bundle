<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ImportFieldTrait;

final class IgnoredImportField implements ImportFieldInterface
{
    use ImportFieldTrait;

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field
            ->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label);

        return $field;
    }
}
