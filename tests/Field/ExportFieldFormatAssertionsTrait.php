<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

trait ExportFieldFormatAssertionsTrait
{
    abstract protected function createField(): ExportFieldInterface;

    public function testOnlyOnFormatStoresNormalizedVisibleFormats(): void
    {
        $field = $this->createField();
        $field->onlyOnFormat(' CSV ');

        self::assertSame(
            ['csv'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS)
        );
    }

    public function testOnlyOnFormatsStoresNormalizedVisibleFormats(): void
    {
        $field = $this->createField();
        $field->onlyOnFormats([' csv ', 'JSON', 'csv']);

        self::assertSame(
            ['csv', 'json'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS)
        );
    }

    public function testHideOnFormatStoresNormalizedHiddenFormats(): void
    {
        $field = $this->createField();
        $field->hideOnFormat(' JSON ');

        self::assertSame(
            ['json'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::HIDDEN_FORMATS)
        );
    }

    public function testHideOnFormatsStoresNormalizedHiddenFormats(): void
    {
        $field = $this->createField();
        $field->hideOnFormats([' json ', 'XLSX', 'json']);

        self::assertSame(
            ['json', 'xlsx'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::HIDDEN_FORMATS)
        );
    }

    public function testShowOnFormatStoresVisibleFormat(): void
    {
        $field = $this->createField();
        $field->showOnFormat('xlsx');

        self::assertSame(
            ['xlsx'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::VISIBLE_FORMATS)
        );
    }

    public function testSetLabelForFormatStoresNormalizedFormatLabel(): void
    {
        $field = $this->createField();
        $field->setLabelForFormat(' CSV ', 'Montant TTC');

        self::assertSame(
            ['csv' => 'Montant TTC'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS)
        );
    }

    public function testSetLabelsForFormatsStoresNormalizedFormatLabels(): void
    {
        $field = $this->createField();
        $field->setLabelsForFormats([
            ' CSV ' => 'Montant TTC',
            'json' => 'total_ttc',
        ]);

        self::assertSame(
            [
                'csv' => 'Montant TTC',
                'json' => 'total_ttc',
            ],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS)
        );
    }

    public function testSetLabelsForFormatsMergesWithExistingLabels(): void
    {
        $field = $this->createField();
        $field
            ->setLabelForFormat('csv', 'A')
            ->setLabelsForFormats(['json' => 'B']);

        self::assertSame(
            [
                'csv' => 'A',
                'json' => 'B',
            ],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS)
        );
    }

    public function testSetLabelForFormatOverridesExistingLabelForSameFormat(): void
    {
        $field = $this->createField();
        $field
            ->setLabelsForFormats(['csv' => 'A'])
            ->setLabelForFormat('CSV', 'B');

        self::assertSame(
            ['csv' => 'B'],
            $field->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS)
        );
    }

    public function testOnlyOnFormatThrowsWhenFormatIsEmpty(): void
    {
        $field = $this->createField();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->onlyOnFormat('   ');
    }

    public function testHideOnFormatThrowsWhenFormatIsEmpty(): void
    {
        $field = $this->createField();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->hideOnFormat('');
    }

    public function testSetLabelForFormatThrowsWhenFormatIsEmpty(): void
    {
        $field = $this->createField();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export format cannot be empty.');

        $field->setLabelForFormat(' ', 'Label');
    }

    public function testPositionStoresValue(): void
    {
        $field = $this->createField();
        $field->position(10);

        self::assertSame(10, $field->getAsDto()->getPosition());
    }

    public function testPositionCanBeOverridden(): void
    {
        $field = $this->createField();
        $field
            ->position(10)
            ->position(20);

        self::assertSame(20, $field->getAsDto()->getPosition());
    }

    public function testPositionCanBeResetToNull(): void
    {
        $field = $this->createField();
        $field
            ->position(10)
            ->position(null);

        self::assertNull($field->getAsDto()->getPosition());
    }

    public function testPositionThrowsWhenNegative(): void
    {
        $field = $this->createField();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Export field position must be greater than or equal to 0.');

        $field->position(-1);
    }
}
