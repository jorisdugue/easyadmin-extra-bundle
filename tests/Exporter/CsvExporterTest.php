<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Exporter;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exporter\CsvExporter;
use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvExporterTest extends TestCase
{
    public function testExportBuildsAStreamedCsvResponseWithExpectedHeadersAndContent(): void
    {
        $exporter = new CsvExporter(new SpreadsheetCellSanitizerService());
        $payload = new ExportPayload(
            filename: 'users',
            format: 'csv',
            headers: ['Name', 'Formula'],
            properties: ['name', 'formula'],
            rows: [
                ['Alice', '=1+1'],
                ['Bob', 'safe'],
            ],
            allowSpreadsheetFormulas: false,
        );

        $response = $exporter->export($payload);

        self::assertSame('text/csv; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('users.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $this->getStreamedResponseContent($response);

        self::assertStringStartsWith("\xEF\xBB\xBF", $content);
        self::assertStringContainsString('Name;Formula', $content);
        self::assertStringContainsString("Alice;'=1+1", $content);
        self::assertStringContainsString('Bob;safe', $content);
    }

    public function testExportCanKeepSpreadsheetFormulasWhenAllowed(): void
    {
        $exporter = new CsvExporter(new SpreadsheetCellSanitizerService());
        $payload = new ExportPayload(
            filename: 'users.csv',
            format: 'csv',
            headers: ['Formula'],
            properties: ['formula'],
            rows: [['=SUM(A1:A2)']],
            allowSpreadsheetFormulas: true,
        );

        $response = $exporter->export($payload);

        $content = $this->getStreamedResponseContent($response);

        self::assertStringContainsString('=SUM(A1:A2)', $content);
        self::assertStringNotContainsString("'=SUM(A1:A2)", $content);
    }

    private function getStreamedResponseContent(StreamedResponse $response): string
    {
        $callback = $response->getCallback();

        self::assertNotNull($callback);

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }
}
