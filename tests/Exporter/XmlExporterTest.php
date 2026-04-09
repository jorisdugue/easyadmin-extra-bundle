<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Exporter;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exporter\XmlExporter;
use PHPUnit\Framework\TestCase;

final class XmlExporterTest extends TestCase
{
    public function testExportBuildsXmlAttachmentAndNormalizesFieldNames(): void
    {
        $exporter = new XmlExporter();
        $payload = new ExportPayload(
            filename: 'users',
            format: ExportFormat::XML,
            headers: ['Display Name', '123 score', ''],
            properties: ['name', 'score', 'note'],
            rows: [
                ['Alice', 42, '<safe>'],
            ],
            allowSpreadsheetFormulas: false,
        );

        $response = $exporter->export($payload);

        self::assertSame('application/xml; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('users.xml', (string) $response->headers->get('Content-Disposition'));

        $xml = (string) $response->getContent();

        self::assertStringContainsString('<export>', $xml);
        self::assertStringContainsString('<item>', $xml);
        self::assertStringContainsString('<display_name>Alice</display_name>', $xml);
        self::assertStringContainsString('<field_123_score>42</field_123_score>', $xml);
        self::assertStringContainsString('<field_2>&lt;safe&gt;</field_2>', $xml);
    }
}
