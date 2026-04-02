<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldRoleTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

/**
 * Boolean export field.
 *
 * This field allows converting boolean values into custom
 * human-readable labels.
 *
 * Example:
 *  BooleanExportField::new('enabled', 'Enabled')
 *      ->setLabels('Yes', 'No');
 */
final class BooleanExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldTrait;
    use ExportFieldRoleTrait;

    public const string OPTION_TRUE_LABEL = 'true_label';
    public const string OPTION_FALSE_LABEL = 'false_label';

    public static function new(string $propertyName, ?string $label = null): static
    {
        return new self()
            ->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setCustomOption(self::OPTION_TRUE_LABEL, 'Yes')
            ->setCustomOption(self::OPTION_FALSE_LABEL, 'No');
    }

    public function setLabels(string $trueLabel, string $falseLabel): static
    {
        return $this
            ->setCustomOption(self::OPTION_TRUE_LABEL, $trueLabel)
            ->setCustomOption(self::OPTION_FALSE_LABEL, $falseLabel);
    }
}
