<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ImportFieldTrait;

final class ChoiceImportField implements ImportFieldInterface
{
    use ImportFieldTrait;

    public const OPTION_CHOICES = 'choices';

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field
            ->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label);

        return $field;
    }

    /**
     * @param array<int|string, string> $choices
     */
    public function setChoices(array $choices): static
    {
        return $this->setCustomOption(self::OPTION_CHOICES, $choices);
    }
}
