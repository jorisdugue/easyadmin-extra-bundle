<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use PHPUnit\Framework\TestCase;

final class ExportFieldFormatResolverTest extends TestCase
{
    private ExportFieldFormatResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ExportFieldFormatResolver();
    }

    public function testIsVisibleReturnsTrueByDefault(): void
    {
        $dto = new ExportFieldDto();

        self::assertTrue($this->resolver->isVisible($dto, 'csv'));
        self::assertTrue($this->resolver->isVisible($dto, 'json'));
        self::assertTrue($this->resolver->isVisible($dto, 'xlsx'));
    }

    public function testIsVisibleUsesVisibleFormatsWhenDefined(): void
    {
        $dto = new ExportFieldDto();
        $dto->setCustomOption(ExportFieldOption::VISIBLE_FORMATS, ['csv', 'json']);

        self::assertTrue($this->resolver->isVisible($dto, 'csv'));
        self::assertTrue($this->resolver->isVisible($dto, 'json'));
        self::assertFalse($this->resolver->isVisible($dto, 'xlsx'));
    }

    public function testIsVisibleUsesHiddenFormatsWhenDefined(): void
    {
        $dto = new ExportFieldDto();
        $dto->setCustomOption(ExportFieldOption::HIDDEN_FORMATS, ['json']);

        self::assertTrue($this->resolver->isVisible($dto, 'csv'));
        self::assertFalse($this->resolver->isVisible($dto, 'json'));
        self::assertTrue($this->resolver->isVisible($dto, 'xlsx'));
    }

    public function testIsVisiblePrefersHiddenFormatsOverVisibleFormats(): void
    {
        $dto = new ExportFieldDto();
        $dto->setCustomOption(ExportFieldOption::VISIBLE_FORMATS, ['csv']);
        $dto->setCustomOption(ExportFieldOption::HIDDEN_FORMATS, ['csv', 'json']);

        self::assertFalse($this->resolver->isVisible($dto, 'csv'));
        self::assertFalse($this->resolver->isVisible($dto, 'json'));
        self::assertFalse($this->resolver->isVisible($dto, 'xlsx'));
    }

    public function testIsVisibleNormalizesFormat(): void
    {
        $dto = new ExportFieldDto();
        $dto->setCustomOption(ExportFieldOption::VISIBLE_FORMATS, ['csv']);

        self::assertTrue($this->resolver->isVisible($dto, ' CSV '));
    }

    public function testIsVisibleThrowsWhenFormatIsEmpty(): void
    {
        $dto = new ExportFieldDto();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $this->resolver->isVisible($dto, '   ');
    }

    public function testResolveHeaderUsesFormatSpecificLabel(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('total');
        $dto->setLabel('Total');
        $dto->setCustomOption(ExportFieldOption::FORMAT_LABELS, [
            'csv' => 'Montant TTC',
            'json' => 'total_ttc',
        ]);

        self::assertSame('Montant TTC', $this->resolver->resolveHeader($dto, 'csv'));
        self::assertSame('total_ttc', $this->resolver->resolveHeader($dto, 'json'));
    }

    public function testResolveHeaderFallsBackToDefaultLabel(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel('Email');

        self::assertSame('Email', $this->resolver->resolveHeader($dto, 'csv'));
    }

    public function testResolveHeaderFallsBackToPropertyWhenLabelIsNull(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel(null);

        self::assertSame('email', $this->resolver->resolveHeader($dto, 'csv'));
    }

    public function testResolveHeaderFallsBackToPropertyWhenLabelIsFalse(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel(false);

        self::assertSame('email', $this->resolver->resolveHeader($dto, 'csv'));
    }

    public function testResolveHeaderFallsBackToPropertyWhenLabelIsEmptyString(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel('');

        self::assertSame('email', $this->resolver->resolveHeader($dto, 'csv'));
    }

    public function testResolveHeaderIgnoresEmptyFormatSpecificLabelAndFallsBackToDefaultLabel(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel('Email');
        $dto->setCustomOption(ExportFieldOption::FORMAT_LABELS, [
            'csv' => '   ',
        ]);

        self::assertSame('Email', $this->resolver->resolveHeader($dto, 'csv'));
    }

    public function testResolveHeaderNormalizesFormat(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel('Email');
        $dto->setCustomOption(ExportFieldOption::FORMAT_LABELS, [
            'csv' => 'Adresse e-mail',
        ]);

        self::assertSame('Adresse e-mail', $this->resolver->resolveHeader($dto, ' CSV '));
    }

    public function testResolveHeaderThrowsWhenFormatIsEmpty(): void
    {
        $dto = new ExportFieldDto();
        $dto->setProperty('email');
        $dto->setLabel('Email');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $this->resolver->resolveHeader($dto, '');
    }
}
