<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Export;

use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Central registry for all available exportes.
 *
 * Responsibilities:
 * - Index exporters by format
 * - Prevent duplicate format registrations
 * - Resolve exporters in constant time
 */
final readonly class ExporterRegistry
{
    /**
     * @var array<string, ExporterInterface>
     */
    private array $exportersByFormat;

    /**
     * @param iterable<ExporterInterface> $exporters
     */
    public function __construct(iterable $exporters)
    {
        $map = [];

        foreach ($exporters as $exporter) {
            $format = $exporter->getFormat();

            if (isset($map[$format])) {
                throw new LogicException(\sprintf('Duplicate exporter detected for format "%s". Conflicting exporters: "%s" and "%s".', $format, get_debug_type($map[$format]), get_debug_type($exporter)));
            }

            $map[$format] = $exporter;
        }

        $this->exportersByFormat = $map;
    }

    /**
     * Resolves the exporter matching the requested format.
     *
     * @throws InvalidExportConfigurationException when no exporter is registered for the format
     */
    public function get(string $format): ExporterInterface
    {
        if (!isset($this->exportersByFormat[$format])) {
            throw InvalidExportConfigurationException::unsupportedFormat($format, array_keys($this->exportersByFormat));
        }

        return $this->exportersByFormat[$format];
    }

    /**
     * Exports the payload using the exporter registered for the given format.
     *
     * @throws InvalidExportConfigurationException when no exporter is registered for the format
     */
    public function export(string $format, ExportPayload $payload): Response
    {
        return $this->get($format)->export($payload);
    }

    /**
     * @return list<string>
     */
    public function getSupportedFormats(): array
    {
        return array_keys($this->exportersByFormat);
    }
}
