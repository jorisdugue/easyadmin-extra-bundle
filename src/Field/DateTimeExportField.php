<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldRoleTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

final class DateTimeExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldRoleTrait;
    use ExportFieldTrait;

    /**
     * @var string
     */
    public const OPTION_FORMAT = 'format';
    /**
     * @var string
     */
    private const DEFAULT_FORMAT = 'Y-m-d H:i:s';

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setCustomOption(self::OPTION_FORMAT, self::DEFAULT_FORMAT);

        return $field;
    }

    public function setFormat(string $format): static
    {
        return $this->setCustomOption(self::OPTION_FORMAT, $format);
    }
}
