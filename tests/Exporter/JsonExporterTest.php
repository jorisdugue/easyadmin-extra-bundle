<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Exporter;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exporter\JsonExporter;
use PHPUnit\Framework\TestCase;

final class JsonExporterTest extends TestCase
{
    public function testExportBuildsAStreamedJsonResponse(): void
    {
        $exporter = new JsonExporter();
        $payload = new ExportPayload(
            filename: 'users',
            format: 'json',
            headers: ['Name', 'Email'],
            properties: ['name', 'email'],
            rows: [
                ['Alice', 'alice@example.com'],
                ['Bob', 'bob@example.com'],
            ],
            allowSpreadsheetFormulas: false,
        );

        $response = $exporter->export($payload);

        self::assertSame('application/json; charset=UTF-8', (string) $response->headers->get('Content-Type'));
        self::assertStringContainsString('users.json', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        self::assertJson($content);
        self::assertSame(
            [
                ['name' => 'Alice', 'email' => 'alice@example.com'],
                ['name' => 'Bob', 'email' => 'bob@example.com'],
            ],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testExportPadsMissingColumnsWithNull(): void
    {
        $exporter = new JsonExporter();
        $payload = new ExportPayload(
            filename: 'users.json',
            format: 'json',
            headers: ['Name', 'Email'],
            properties: ['name', 'email'],
            rows: [
                ['Alice'],
            ],
            allowSpreadsheetFormulas: false,
        );

        $response = $exporter->export($payload);

        ob_start();
        $response->sendContent();
        $content = (string) ob_get_clean();

        self::assertSame(
            [['name' => 'Alice', 'email' => null]],
            json_decode($content, true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
