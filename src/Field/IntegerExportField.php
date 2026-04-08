<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldRoleTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

final class IntegerExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldRoleTrait;
    use ExportFieldTrait;

    /**
     * @var string
     */
    public const OPTION_THOUSANDS_SEPARATOR = 'thousands_separator';

    /**
     * @var string
     */
    private const DEFAULT_THOUSANDS_SEPARATOR = '';

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field
            ->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setCustomOption(self::OPTION_THOUSANDS_SEPARATOR, self::DEFAULT_THOUSANDS_SEPARATOR);

        return $field;
    }

    public function setThousandsSeparator(string $separator): static
    {
        return $this->setCustomOption(self::OPTION_THOUSANDS_SEPARATOR, $separator);
    }
}
