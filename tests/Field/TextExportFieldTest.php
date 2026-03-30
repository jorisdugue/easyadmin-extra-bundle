<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use PHPUnit\Framework\TestCase;

final class TextExportFieldTest extends TestCase
{
    use ExportFieldFormatAssertionsTrait;

    protected function createField(): ExportFieldInterface
    {
        return TextExportField::new('name', 'Name');
    }
}
