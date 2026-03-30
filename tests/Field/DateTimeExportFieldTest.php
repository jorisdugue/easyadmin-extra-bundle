<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Field;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use PHPUnit\Framework\TestCase;

final class DateTimeExportFieldTest extends TestCase
{
    use ExportFieldFormatAssertionsTrait;

    protected function createField(): ExportFieldInterface
    {
        return DateTimeExportField::new('createdAt', 'Created at');
    }
}
