<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

/**
 * Choice export field.
 *
 * This field allows mapping raw values to human-readable labels
 * using a defined set of choices
 *
 * Example:
 *   ChoiceExportField::new('status', 'Status')
 *       ->setChoices([
 *           'draft' => 'Draft',
 *           'published' => 'Published',
 *       ]);
 */
final class ChoiceExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldTrait;

    public const string OPTION_CHOICES = 'choices';

    /**
     * Creates a new choice export field.
     */
    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label);

        return $field;
    }

    /**
     * Defines the available choices.
     *
     * @param array<string, string> $choices associative array of value => label
     */
    public function setChoices(array $choices): static
    {
        return $this->setCustomOption(self::OPTION_CHOICES, $choices);
    }
}
