<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ImportFieldTrait;

final class DateImportField implements ImportFieldInterface
{
    use ImportFieldTrait;

    public const OPTION_FORMAT = 'format';
    private const DEFAULT_FORMAT = 'Y-m-d';

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field
            ->setFieldFqcn(self::class)
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
