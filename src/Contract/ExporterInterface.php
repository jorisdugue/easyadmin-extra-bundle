<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defines a concrete export format writer.
 *
 * Each exporter is responsible for transforming an ExportPayload into
 * a downloadable HTTP response for a single format.
 */
interface ExporterInterface
{
    /**
     * Returns the format handled by this exporter.
     *
     * Example: "csv", "xlsx", "json"
     */
    public function getFormat(): string;

    /**
     * Converts the export payload into a Response.
     */
    public function export(ExportPayload $payload): Response;
}
