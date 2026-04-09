<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exporter;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use RuntimeException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams CSV exports directly to the HTTP response output.
 *
 * This exporter is designed to be memory-efficient:
 * - Rows are consumed as an iterable (typically a Generator)
 * - No full dataset is ever stored in memory
 * - Each row is written immediately to the output stream
 *
 * Security:
 * - Values are sanitized to prevent CSV/Excel formula injection
 * - Headers are always sanitized with formulas disabled
 *
 * Performance:
 * - Uses php://output for direct streaming
 * - Optional periodic flushing to reduce output buffering
 */
final readonly class CsvExporter implements ExporterInterface
{
    public function __construct(
        private SpreadsheetCellSanitizerService $sanitizerService,
    ) {}

    /**
     * Exports data as a streamed CSV response.
     *
     * @param ExportPayload $payload Contains headers, rows (iterable), filename, and options
     */
    public function export(ExportPayload $payload): StreamedResponse
    {
        $filename = str_ends_with($payload->filename, '.csv')
            ? $payload->filename
            : $payload->filename . '.csv';

        $response = new StreamedResponse(function () use ($payload): void {
            $handle = fopen('php://output', 'w');

            if (false === $handle) {
                throw new RuntimeException('Unable to open php://output.');
            }

            try {
                // Write UTF-8 BOM for Excel compatibility
                if (false === fwrite($handle, "\xEF\xBB\xBF")) {
                    throw new RuntimeException('Unable to write UTF-8 BOM to output stream.');
                }

                if (false === fputcsv(
                    $handle,
                    $this->sanitizerService->sanitizeRow($payload->headers, false),
                    ';',
                    '"',
                    '',
                )) {
                    throw new RuntimeException('Unable to write CSV header.');
                }

                $index = 0;

                foreach ($payload->rows as $row) {
                    if (false === fputcsv(
                        $handle,
                        $this->sanitizerService->sanitizeRow($row, $payload->allowSpreadsheetFormulas),
                        ';',
                        '"',
                        '',
                    )) {
                        throw new RuntimeException('Unable to write CSV row.');
                    }

                    // Periodically flush output buffers (best-effort)
                    if (0 === (++$index % 100)) {
                        if (\function_exists('ob_flush')) {
                            @ob_flush();
                        }

                        flush();
                    }
                }
            } finally {
                fclose($handle);
            }
        });

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function getFormat(): string
    {
        return ExportFormat::CSV;
    }
}
