<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Exporter;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exporter\XlsxExporter;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class XlsxExporterTest extends TestCase
{
    public function testExportBuildsAnXlsxDownloadWithExpectedCellValues(): void
    {
        $exporter = new XlsxExporter();
        $payload = new ExportPayload(
            filename: 'users',
            format: 'xlsx',
            headers: ['Name', 'Formula'],
            properties: ['name', 'formula'],
            rows: [
                ['Alice', '=1+1'],
            ],
            allowSpreadsheetFormulas: false,
        );

        $response = $exporter->export($payload);

        self::assertInstanceOf(BinaryFileResponse::class, $response);
        self::assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $response->headers->get('Content-Type')
        );
        self::assertStringContainsString('users.xlsx', (string) $response->headers->get('Content-Disposition'));

        $file = $response->getFile();
        self::assertNotNull($file);
        self::assertFileExists($file->getPathname());

        $spreadsheet = IOFactory::load($file->getPathname());
        $sheet = $spreadsheet->getActiveSheet();

        self::assertSame('Name', (string) $sheet->getCell('A1')->getValue());
        self::assertSame('Formula', (string) $sheet->getCell('B1')->getValue());
        self::assertSame('Alice', (string) $sheet->getCell('A2')->getValue());
        self::assertSame('=1+1', (string) $sheet->getCell('B2')->getValue());

        $spreadsheet->disconnectWorksheets();
        @unlink($file->getPathname());
    }
}
