<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service;

use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use PHPUnit\Framework\TestCase;

final class SpreadsheetCellSanitizerServiceTest extends TestCase
{
    private SpreadsheetCellSanitizerService $service;

    protected function setUp(): void
    {
        $this->service = new SpreadsheetCellSanitizerService();
    }

    public function testSanitizeValuePrefixesPotentialSpreadsheetFormulas(): void
    {
        self::assertSame("'=2+2", $this->service->sanitizeValue('=2+2'));
        self::assertSame("'+SUM(A1:A2)", $this->service->sanitizeValue('+SUM(A1:A2)'));
        self::assertSame("'-10", $this->service->sanitizeValue('-10'));
        self::assertSame("'@cmd", $this->service->sanitizeValue('@cmd'));
    }

    public function testSanitizeValueLeavesSafeStringsUntouched(): void
    {
        self::assertSame('hello', $this->service->sanitizeValue('hello'));
        self::assertSame('  hello', $this->service->sanitizeValue('  hello'));
        self::assertSame('123', $this->service->sanitizeValue('123'));
    }

    public function testSanitizeValueReturnsEmptyStringForNullValues(): void
    {
        self::assertSame('', $this->service->sanitizeValue(null));
    }

    public function testSanitizeValueCanAllowSpreadsheetFormulas(): void
    {
        self::assertSame('=2+2', $this->service->sanitizeValue('=2+2', true));
    }

    public function testSanitizeRowSanitizesEachValue(): void
    {
        self::assertSame(
            ["'=1+1", 'safe', '', "'@test"],
            $this->service->sanitizeRow(['=1+1', 'safe', null, '@test'])
        );
    }
}
