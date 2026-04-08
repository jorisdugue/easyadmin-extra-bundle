<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Trait;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\BooleanExportField;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;
use JorisDugue\EasyAdminExtraBundle\Field\IntegerExportField;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\NumberExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ExportFieldFormatTraitTest extends TestCase
{
    #[DataProvider('fieldProvider')]
    public function testOnlyOnFormatStoresNormalizedVisibleFormats(ExportFieldInterface $field): void
    {
        $field->onlyOnFormat(' CSV ');

        self::assertSame(
            ['csv'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testOnlyOnFormatsNormalizesAndDeduplicatesFormats(ExportFieldInterface $field): void
    {
        $field->onlyOnFormats([' csv ', 'JSON', 'csv', ' json ']);

        self::assertSame(
            ['csv', 'json'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testHideOnFormatStoresNormalizedHiddenFormats(ExportFieldInterface $field): void
    {
        $field->hideOnFormat(' JSON ');

        self::assertSame(
            ['json'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::HIDDEN_FORMATS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testHideOnFormatsNormalizesAndDeduplicatesFormats(ExportFieldInterface $field): void
    {
        $field->hideOnFormats([' json ', 'XLSX', 'json', ' xlsx ']);

        self::assertSame(
            ['json', 'xlsx'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::HIDDEN_FORMATS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testShowOnFormatIsAliasOfOnlyOnFormat(ExportFieldInterface $field): void
    {
        $field->showOnFormat('xlsx');

        self::assertSame(
            ['xlsx'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testSetLabelForFormatStoresNormalizedFormatLabel(ExportFieldInterface $field): void
    {
        $field->setLabelForFormat(' CSV ', 'Montant TTC');

        self::assertSame(
            ['csv' => 'Montant TTC'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testSetLabelsForFormatsStoresNormalizedFormatLabels(ExportFieldInterface $field): void
    {
        $field->setLabelsForFormats([
            ' CSV ' => 'Montant TTC',
            'json' => 'total_ttc',
        ]);

        self::assertSame(
            [
                'csv' => 'Montant TTC',
                'json' => 'total_ttc',
            ],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testSetLabelsForFormatsMergesWithExistingFormatLabels(ExportFieldInterface $field): void
    {
        $field
            ->setLabelForFormat('csv', 'A')
            ->setLabelsForFormats(['json' => 'B']);

        self::assertSame(
            [
                'csv' => 'A',
                'json' => 'B',
            ],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testSetLabelForFormatOverridesExistingLabelForSameFormat(ExportFieldInterface $field): void
    {
        $field
            ->setLabelsForFormats(['csv' => 'A'])
            ->setLabelForFormat('CSV', 'B');

        self::assertSame(
            ['csv' => 'B'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS),
        );
    }

    #[DataProvider('fieldProvider')]
    public function testOnlyOnFormatThrowsWhenFormatIsEmpty(ExportFieldInterface $field): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->onlyOnFormat('   ');
    }

    #[DataProvider('fieldProvider')]
    public function testHideOnFormatThrowsWhenFormatIsEmpty(ExportFieldInterface $field): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->hideOnFormat('');
    }

    #[DataProvider('fieldProvider')]
    public function testSetLabelForFormatThrowsWhenFormatIsEmpty(ExportFieldInterface $field): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->setLabelForFormat(' ', 'Label');
    }

    /**
     * @return iterable<string, array{0: ExportFieldInterface}>
     */
    public static function fieldProvider(): iterable
    {
        yield 'text' => [TextExportField::new('name', 'Name')];
        yield 'boolean' => [BooleanExportField::new('enabled', 'Enabled')];
        yield 'choice' => [ChoiceExportField::new('status', 'Status')];
        yield 'date' => [DateExportField::new('publishedAt', 'Published at')];
        yield 'datetime' => [DateTimeExportField::new('createdAt', 'Created at')];
        yield 'integer' => [IntegerExportField::new('count', 'Count')];
        yield 'number' => [NumberExportField::new('ratio', 'Ratio')];
        yield 'money' => [MoneyExportField::new('total', 'Total')];
    }
}
