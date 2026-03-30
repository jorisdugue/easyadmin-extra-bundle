<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\IntegerExportField;
use PHPUnit\Framework\TestCase;

final class IntegerExportFieldTest extends TestCase
{
    use ExportFieldFormatAssertionsTrait;

    protected function createField(): ExportFieldInterface
    {
        return IntegerExportField::new('count', 'Count');
    }
}
