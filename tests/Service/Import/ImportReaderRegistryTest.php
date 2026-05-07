<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportReaderRegistry;
use PHPUnit\Framework\TestCase;

final class ImportReaderRegistryTest extends TestCase
{
    public function testItResolvesCsvReader(): void
    {
        $reader = new CsvPreviewReader();
        $registry = new ImportReaderRegistry([$reader]);

        self::assertSame($reader, $registry->get('csv'));
        self::assertSame($reader, $registry->get('CSV'));
    }

    public function testItRejectsUnsupportedFormat(): void
    {
        $registry = new ImportReaderRegistry([new CsvPreviewReader()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported import format "xlsx".');

        $registry->get('xlsx');
    }
}
