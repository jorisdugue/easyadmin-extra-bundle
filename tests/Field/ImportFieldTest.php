<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use PHPUnit\Framework\TestCase;

final class ImportFieldTest extends TestCase
{
    public function testPositionIsOneBasedForImportFields(): void
    {
        $field = TextImportField::new('uuid', 'UUID')->position(1);

        self::assertSame(1, $field->getAsDto()->getPosition());
    }

    public function testPositionZeroIsInvalidForImportFields(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Import field position must be greater than or equal to 1.');

        TextImportField::new('uuid', 'UUID')->position(0);
    }

    public function testIgnoredImportFieldUsesImportFieldApi(): void
    {
        $field = IgnoredImportField::new('id')->position(1);

        self::assertSame('id', $field->getAsDto()->getProperty());
        self::assertSame(1, $field->getAsDto()->getPosition());
    }
}
