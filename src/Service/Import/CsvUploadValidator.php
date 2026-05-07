<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CsvUploadValidator
{
    public const MAX_FILE_SIZE_BYTES = 2_097_152;
    public const MAX_COLUMNS = 50;

    private const MAX_SAMPLE_BYTES = 16_384;
    private const MAX_SAMPLE_ROWS = 25;
    private const MAX_LINE_LENGTH_BYTES = 8_192;
    private const ALLOWED_EXTENSIONS = ['csv'];
    private const ALLOWED_MIME_TYPES = [
        'application/csv',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'text/x-csv',
    ];
    private const FORBIDDEN_PREFIXES = [
        "PK\x03\x04",
        "PK\x05\x06",
        "PK\x07\x08",
        '%PDF',
        'MZ',
        "\x7FELF",
    ];
    private const FORBIDDEN_TEXT_MARKERS = [
        '<?php',
        '<!doctype html',
        '<html',
    ];

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    public function validateUpload(UploadedFile $file, array &$issues): void
    {
        if (!$file->isValid()) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The uploaded file is not valid.');

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'Only CSV files are accepted.');
        }

        $size = $file->getSize();
        if (null === $size || 0 === $size) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The CSV file is empty.');
        } elseif ($size > self::MAX_FILE_SIZE_BYTES) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('The CSV file must be %s MB or smaller.', (string) (self::MAX_FILE_SIZE_BYTES / 1_048_576)));
        }

        $mimeType = $file->getMimeType();
        if (!\is_string($mimeType) || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The uploaded file type is not accepted as CSV.');
        }
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    public function validateContent(UploadedFile $file, string $encoding, string $separator, array &$issues): void
    {
        $path = $file->getPathname();
        $sample = @file_get_contents($path, false, null, 0, self::MAX_SAMPLE_BYTES);
        if (!\is_string($sample) || '' === $sample) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The CSV file could not be read.');

            return;
        }

        if (
            $this->hasForbiddenSignature($sample)
            || str_contains($sample, "\0")
            || $this->hasTooManyControlCharacters($sample)
            || !$this->isValidEncoding($sample, $encoding)
            || $this->hasOverlongSampleLine($sample)
            || !$this->hasUsableCsvRows($path, $separator, $issues)
        ) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The uploaded file is not a valid CSV file.');
        }
    }

    private function hasForbiddenSignature(string $sample): bool
    {
        foreach (self::FORBIDDEN_PREFIXES as $prefix) {
            if (str_starts_with($sample, $prefix)) {
                return true;
            }
        }

        $normalized = strtolower(ltrim(substr($sample, 0, 512)));
        foreach (self::FORBIDDEN_TEXT_MARKERS as $marker) {
            if (str_starts_with($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function hasTooManyControlCharacters(string $sample): bool
    {
        $length = \strlen($sample);
        if (0 === $length) {
            return true;
        }

        $controlCharacters = 0;
        for ($i = 0; $i < $length; ++$i) {
            $byte = \ord($sample[$i]);
            if ($byte < 32 && !\in_array($byte, [9, 10, 13], true)) {
                ++$controlCharacters;
            }
        }

        return $controlCharacters > 0 && ($controlCharacters / $length) > 0.02;
    }

    private function isValidEncoding(string $sample, string $encoding): bool
    {
        if ('UTF-8' === $encoding && \function_exists('mb_check_encoding')) {
            return mb_check_encoding($sample, 'UTF-8');
        }

        if (\function_exists('mb_convert_encoding')) {
            return \is_string(@mb_convert_encoding($sample, 'UTF-8', $encoding));
        }

        return false !== @iconv($encoding, 'UTF-8//IGNORE', $sample);
    }

    private function hasOverlongSampleLine(string $sample): bool
    {
        $lines = preg_split('/\r\n|\r|\n/', $sample);
        if (!\is_array($lines)) {
            return true;
        }

        foreach ($lines as $line) {
            if (\strlen($line) > self::MAX_LINE_LENGTH_BYTES) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function hasUsableCsvRows(string $path, string $separator, array &$issues): bool
    {
        $handle = @fopen($path, 'r');
        if (!\is_resource($handle)) {
            return false;
        }

        $usableRows = 0;
        $sampledRows = 0;
        $columnCounts = [];

        try {
            while ($sampledRows < self::MAX_SAMPLE_ROWS && ($row = fgetcsv($handle, self::MAX_LINE_LENGTH_BYTES + 1, $separator, '"', '')) !== false) {
                ++$sampledRows;
                if ($this->isEmptyCsvRow($row)) {
                    continue;
                }

                $columnCount = \count($row);
                if ($columnCount > self::MAX_COLUMNS) {
                    return false;
                }

                ++$usableRows;
                $columnCounts[] = $columnCount;
            }
        } finally {
            fclose($handle);
        }

        if (0 === $usableRows) {
            return false;
        }

        $uniqueColumnCounts = array_values(array_unique($columnCounts));
        if (\count($uniqueColumnCounts) > 2) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, 'Some CSV rows have inconsistent column counts.');
        }

        return true;
    }

    /**
     * @param list<string|null> $row
     */
    private function isEmptyCsvRow(array $row): bool
    {
        foreach ($row as $value) {
            if (null !== $value && '' !== trim($value)) {
                return false;
            }
        }

        return true;
    }
}
