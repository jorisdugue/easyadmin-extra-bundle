<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldRoleTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

final class NumberExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldRoleTrait;
    use ExportFieldTrait;

    /**
     * @var string
     */
    public const OPTION_DECIMALS = 'decimals';
    /**
     * @var string
     */
    public const OPTION_DECIMAL_SEPARATOR = 'decimal_separator';
    /**
     * @var string
     */
    public const OPTION_THOUSANDS_SEPARATOR = 'thousands_separator';

    /**
     * @var int
     */
    private const DEFAULT_DECIMALS = 2;
    /**
     * @var string
     */
    private const DEFAULT_DECIMAL_SEPARATOR = '.';
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
            ->setCustomOption(self::OPTION_DECIMALS, self::DEFAULT_DECIMALS)
            ->setCustomOption(self::OPTION_DECIMAL_SEPARATOR, self::DEFAULT_DECIMAL_SEPARATOR)
            ->setCustomOption(self::OPTION_THOUSANDS_SEPARATOR, self::DEFAULT_THOUSANDS_SEPARATOR);

        return $field;
    }

    public function setDecimals(int $decimals): static
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException('The "decimals" value must be greater than or equal to 0.');
        }

        return $this->setCustomOption(self::OPTION_DECIMALS, $decimals);
    }

    public function setDecimalSeparator(string $separator): static
    {
        return $this->setCustomOption(self::OPTION_DECIMAL_SEPARATOR, $separator);
    }

    public function setThousandsSeparator(string $separator): static
    {
        return $this->setCustomOption(self::OPTION_THOUSANDS_SEPARATOR, $separator);
    }

    public function setNumberFormat(
        int $decimals = self::DEFAULT_DECIMALS,
        string $decimalSeparator = self::DEFAULT_DECIMAL_SEPARATOR,
        string $thousandsSeparator = self::DEFAULT_THOUSANDS_SEPARATOR,
    ): static {
        return $this
            ->setDecimals($decimals)
            ->setDecimalSeparator($decimalSeparator)
            ->setThousandsSeparator($thousandsSeparator);
    }
}
