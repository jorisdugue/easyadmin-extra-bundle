<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Export;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExporterRegistry;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class ExporterRegistryTest extends TestCase
{
    public function testItResolvesAndExportsUsingRegisteredXmlExporter(): void
    {
        $xmlResponse = new Response('xml-content');
        $xmlExporter = new StubExporter(ExportFormat::XML, $xmlResponse);

        $registry = new ExporterRegistry([$xmlExporter]);

        self::assertSame([ExportFormat::XML], $registry->getSupportedFormats());
        self::assertSame($xmlExporter, $registry->get(ExportFormat::XML));

        $payload = new ExportPayload('users', ExportFormat::XML, ['Name'], ['name'], [['Alice']], false);

        self::assertSame($xmlResponse, $registry->export(ExportFormat::XML, $payload));
        self::assertSame(1, $xmlExporter->calls);
    }

    public function testItRejectsDuplicateExportersForTheSameFormat(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Duplicate exporter detected for format "xml".');

        new ExporterRegistry([
            new StubExporter(ExportFormat::XML, new Response()),
            new StubExporter(ExportFormat::XML, new Response()),
        ]);
    }

    public function testItFailsOnUnknownFormat(): void
    {
        $registry = new ExporterRegistry([
            new StubExporter(ExportFormat::CSV, new Response()),
        ]);

        $this->expectException(InvalidExportConfigurationException::class);

        $registry->get(ExportFormat::XML);
    }
}

final class StubExporter implements ExporterInterface
{
    public int $calls = 0;

    public function __construct(
        private readonly string $format,
        private readonly Response $response,
    ) {}

    public function getFormat(): string
    {
        return $this->format;
    }

    public function export(ExportPayload $payload): Response
    {
        ++$this->calls;

        return $this->response;
    }
}
