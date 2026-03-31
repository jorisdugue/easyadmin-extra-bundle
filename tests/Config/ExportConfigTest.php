<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Config;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;
use PHPUnit\Framework\TestCase;

final class ExportConfigTest extends TestCase
{
    public function testUseDropdownReturnsTrueWhenActionDisplayIsDropdown(): void
    {
        $config = new ExportConfig(
            filename: 'test',
            fields: [],
            actionDisplay: ExportActionDisplay::DROPDOWN,
        );

        self::assertTrue($config->useDropdown());
    }

    public function testUseDropdownReturnsFalseWhenActionDisplayIsButtons(): void
    {
        $config = new ExportConfig(
            filename: 'test',
            fields: [],
            actionDisplay: ExportActionDisplay::BUTTONS,
        );

        self::assertFalse($config->useDropdown());
    }
}
