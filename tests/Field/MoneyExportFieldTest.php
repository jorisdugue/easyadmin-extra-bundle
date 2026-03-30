<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use PHPUnit\Framework\TestCase;

final class MoneyExportFieldTest extends TestCase
{
    use ExportFieldFormatAssertionsTrait;

    protected function createField(): ExportFieldInterface
    {
        return MoneyExportField::new('total', 'Total');
    }

    public function testStoredAsCentsCanBeConfigured(): void
    {
        $field = MoneyExportField::new('total', 'Total')->storedAsCents();

        self::assertTrue($field->getAsDto()->getCustomOption(MoneyExportField::OPTION_STORED_AS_CENTS));
    }

    public function testCurrencyConfiguresAllOptions(): void
    {
        $field = MoneyExportField::new('total', 'Total')
            ->currency('$', MoneyExportField::SYMBOL_POSITION_PREFIX, false, 2, '.', ',');

        $dto = $field->getAsDto();

        self::assertSame('$', $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL));
        self::assertSame(MoneyExportField::SYMBOL_POSITION_PREFIX, $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_POSITION));
        self::assertFalse($dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_SPACING));
        self::assertSame(2, $dto->getCustomOption(MoneyExportField::OPTION_DECIMALS));
        self::assertSame('.', $dto->getCustomOption(MoneyExportField::OPTION_DECIMAL_SEPARATOR));
        self::assertSame(',', $dto->getCustomOption(MoneyExportField::OPTION_THOUSANDS_SEPARATOR));
    }

    public function testEuroShortcutAppliesExpectedConfiguration(): void
    {
        $field = MoneyExportField::new('total', 'Total')->euro();

        $dto = $field->getAsDto();

        self::assertSame('€', $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL));
        self::assertSame(MoneyExportField::SYMBOL_POSITION_SUFFIX, $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_POSITION));
        self::assertTrue($dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_SPACING));
        self::assertSame(2, $dto->getCustomOption(MoneyExportField::OPTION_DECIMALS));
        self::assertSame(',', $dto->getCustomOption(MoneyExportField::OPTION_DECIMAL_SEPARATOR));
        self::assertSame(' ', $dto->getCustomOption(MoneyExportField::OPTION_THOUSANDS_SEPARATOR));
    }

    public function testUsdShortcutAppliesExpectedConfiguration(): void
    {
        $field = MoneyExportField::new('total', 'Total')->usd();

        $dto = $field->getAsDto();

        self::assertSame('$', $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL));
        self::assertSame(MoneyExportField::SYMBOL_POSITION_PREFIX, $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_POSITION));
        self::assertFalse($dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_SPACING));
        self::assertSame(2, $dto->getCustomOption(MoneyExportField::OPTION_DECIMALS));
        self::assertSame('.', $dto->getCustomOption(MoneyExportField::OPTION_DECIMAL_SEPARATOR));
        self::assertSame(',', $dto->getCustomOption(MoneyExportField::OPTION_THOUSANDS_SEPARATOR));
    }

    public function testCurrencyOverridesPreviousConfiguration(): void
    {
        $field = MoneyExportField::new('total', 'Total')
            ->euro()
            ->currency('$', MoneyExportField::SYMBOL_POSITION_PREFIX, false, 2, '.', ',');

        $dto = $field->getAsDto();

        self::assertSame('$', $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL));
    }
}
