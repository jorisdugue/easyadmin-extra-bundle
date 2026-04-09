<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exporter;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Throwable;

/**
 * Exports data to an XLSX file using PhpSpreadsheet.
 *
 * Important:
 * - XLSX generation is not fully streamed
 * - Memory usage depends on PhpSpreadsheet internals
 * - This exporter is best suited for moderate export sizes
 *
 * Security:
 * - Headers are always written as explicit strings
 * - When formulas are disabled, row values are also written as explicit strings
 * - When formulas are enabled, values are written as-is and may be interpreted as formulas
 */
final readonly class XlsxExporter implements ExporterInterface
{
    /**
     * Exports data as an XLSX file download.
     *
     * @param ExportPayload $payload contains headers, rows, filename, and export options
     */
    public function export(ExportPayload $payload): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        try {
            $columnIndex = 1;
            foreach ($payload->headers as $header) {
                $sheet->setCellValueExplicit([$columnIndex, 1], $header, DataType::TYPE_STRING);
                ++$columnIndex;
            }

            $rowIndex = 2;
            foreach ($payload->rows as $row) {
                $columnIndex = 1;
                foreach ($row as $value) {
                    if ($payload->allowSpreadsheetFormulas) {
                        $sheet->setCellValue([$columnIndex, $rowIndex], $value);
                    } else {
                        $sheet->setCellValueExplicit([$columnIndex, $rowIndex], $value, DataType::TYPE_STRING);
                    }
                    ++$columnIndex;
                }
                ++$rowIndex;
            }
            $tmpFile = tempnam(sys_get_temp_dir(), 'ea_extra_');

            if (false === $tmpFile) {
                throw new RuntimeException('Unable to create a temporary XLSX file.');
            }

            try {
                (new Xlsx($spreadsheet))->save($tmpFile);
            } catch (Throwable $e) {
                if (is_file($tmpFile)) {
                    @unlink($tmpFile);
                }

                throw new RuntimeException('Unable to write the XLSX export file.', 0, $e);
            }

            $response = new BinaryFileResponse($tmpFile);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $payload->filename . '.xlsx',
            );
            $response->headers->set(
                'Content-Type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
            $response->deleteFileAfterSend(true);

            return $response;
        } finally {
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);
        }
    }

    public function getFormat(): string
    {
        return ExportFormat::XLSX;
    }
}
