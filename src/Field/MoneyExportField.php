<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Field;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldFormatTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldMaskTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldRoleTrait;
use JorisDugue\EasyAdminExtraBundle\Trait\ExportFieldTrait;

final class MoneyExportField implements ExportFieldInterface
{
    use ExportFieldFormatTrait;
    use ExportFieldMaskTrait;
    use ExportFieldRoleTrait;
    use ExportFieldTrait;

    public const string OPTION_SYMBOL = 'symbol';
    public const string OPTION_SYMBOL_POSITION = 'symbol_position';
    public const string OPTION_SYMBOL_SPACING = 'symbol_spacing';
    public const string OPTION_DECIMALS = 'decimals';
    public const string OPTION_DECIMAL_SEPARATOR = 'decimal_separator';
    public const string OPTION_THOUSANDS_SEPARATOR = 'thousands_separator';
    public const string OPTION_STORED_AS_CENTS = 'stored_as_cents';

    public const string SYMBOL_POSITION_PREFIX = 'prefix';
    public const string SYMBOL_POSITION_SUFFIX = 'suffix';

    private const string DEFAULT_SYMBOL = '€';
    private const string DEFAULT_SYMBOL_POSITION = self::SYMBOL_POSITION_SUFFIX;
    private const bool DEFAULT_SYMBOL_SPACING = true;
    private const int DEFAULT_DECIMALS = 2;
    private const string DEFAULT_DECIMAL_SEPARATOR = ',';
    private const string DEFAULT_THOUSANDS_SEPARATOR = ' ';
    private const bool DEFAULT_STORED_AS_CENTS = false;

    public static function new(string $propertyName, ?string $label = null): static
    {
        $field = new self();
        $field
            ->setFieldFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setCustomOption(self::OPTION_SYMBOL, self::DEFAULT_SYMBOL)
            ->setCustomOption(self::OPTION_SYMBOL_POSITION, self::DEFAULT_SYMBOL_POSITION)
            ->setCustomOption(self::OPTION_SYMBOL_SPACING, self::DEFAULT_SYMBOL_SPACING)
            ->setCustomOption(self::OPTION_DECIMALS, self::DEFAULT_DECIMALS)
            ->setCustomOption(self::OPTION_DECIMAL_SEPARATOR, self::DEFAULT_DECIMAL_SEPARATOR)
            ->setCustomOption(self::OPTION_THOUSANDS_SEPARATOR, self::DEFAULT_THOUSANDS_SEPARATOR)
            ->setCustomOption(self::OPTION_STORED_AS_CENTS, self::DEFAULT_STORED_AS_CENTS);

        return $field;
    }

    public function setSymbol(string $symbol): static
    {
        return $this->setCustomOption(self::OPTION_SYMBOL, $symbol);
    }

    public function setSymbolPosition(string $position): static
    {
        if (!\in_array($position, [self::SYMBOL_POSITION_PREFIX, self::SYMBOL_POSITION_SUFFIX], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid symbol position "%s". Allowed values are "%s" and "%s".', $position, self::SYMBOL_POSITION_PREFIX, self::SYMBOL_POSITION_SUFFIX));
        }

        return $this->setCustomOption(self::OPTION_SYMBOL_POSITION, $position);
    }

    public function setSymbolSpacing(bool $spacing): static
    {
        return $this->setCustomOption(self::OPTION_SYMBOL_SPACING, $spacing);
    }

    public function setDecimals(int $decimals): static
    {
        if ($decimals < 0) {
            throw new InvalidArgumentException('The "decimals" value must be >= 0.');
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

    public function storedAsCents(bool $storedAsCents = true): static
    {
        return $this->setCustomOption(self::OPTION_STORED_AS_CENTS, $storedAsCents);
    }

    public function currency(
        string $symbol,
        string $position = self::SYMBOL_POSITION_SUFFIX,
        bool $spacing = true,
        int $decimals = 2,
        string $decimalSeparator = ',',
        string $thousandsSeparator = ' ',
    ): static {
        return $this
            ->setSymbol($symbol)
            ->setSymbolPosition($position)
            ->setSymbolSpacing($spacing)
            ->setDecimals($decimals)
            ->setDecimalSeparator($decimalSeparator)
            ->setThousandsSeparator($thousandsSeparator);
    }

    /**
     * Shortcut for common euro format.
     */
    public function euro(): static
    {
        return $this->currency(
            symbol: '€',
            position: self::SYMBOL_POSITION_SUFFIX,
            spacing: true,
            decimals: 2,
            decimalSeparator: ',',
            thousandsSeparator: ' '
        );
    }

    /**
     * Shortcut for USD format.
     */
    public function usd(): static
    {
        return $this->currency(
            symbol: '$',
            position: self::SYMBOL_POSITION_PREFIX,
            spacing: false,
            decimals: 2,
            decimalSeparator: '.',
            thousandsSeparator: ','
        );
    }
}
