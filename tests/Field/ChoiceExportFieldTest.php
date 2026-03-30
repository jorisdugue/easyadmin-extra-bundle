<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceExportField;
use PHPUnit\Framework\TestCase;

final class ChoiceExportFieldTest extends TestCase
{
    use ExportFieldFormatAssertionsTrait;

    protected function createField(): ExportFieldInterface
    {
        return ChoiceExportField::new('status', 'Status');
    }
}
