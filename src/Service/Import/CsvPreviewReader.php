<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreview;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportFieldHeaderResolver;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CsvPreviewReader
{
    private const FORMAT = 'CSV';
    private const MAX_FILE_SIZE_BYTES = 2_097_152;
    private const MAX_PREVIEW_ROWS = 20;
    private const MAX_PREVIEW_COLUMNS = 50;
    private const ALLOWED_EXTENSIONS = ['csv'];
    private const ALLOWED_MIME_TYPES = [
        'application/csv',
        'application/vnd.ms-excel',
        'text/csv',
        'text/plain',
        'text/x-csv',
    ];
    private const ALLOWED_ENCODINGS = [
        'UTF-8',
        'ISO-8859-1',
        'Windows-1252',
    ];
    private const SEPARATORS = [
        'comma' => ',',
        'semicolon' => ';',
        'tab' => "\t",
    ];

    public function __construct(private ?ImportPreviewValidator $importPreviewValidator = null) {}

    public function createEmptyPreview(): ImportPreview
    {
        return new ImportPreview(null, self::FORMAT, null, [], [], []);
    }

    public function createErrorPreview(string $message): ImportPreview
    {
        return new ImportPreview(null, self::FORMAT, null, [], [], [
            new ImportPreviewIssue(ImportPreviewIssue::ERROR, $message),
        ]);
    }

    public function preview(
        ?UploadedFile $file,
        string $separatorOption,
        string $encoding,
        bool $firstRowContainsHeaders,
        ?ImportConfig $importConfig = null,
    ): ImportPreview {
        $issues = [];

        if (null === $file) {
            return $this->createErrorPreview('Please choose a CSV file to preview.');
        }

        $filename = $this->sanitizeFilename($file->getClientOriginalName());
        $encoding = $this->resolveEncoding($encoding, $issues);

        $this->validateUpload($file, $issues);
        if ($this->hasErrors($issues)) {
            return new ImportPreview($filename, self::FORMAT, null, [], [], $issues);
        }

        if (null === $encoding) {
            return new ImportPreview($filename, self::FORMAT, null, [], [], $issues);
        }

        $separator = $this->resolveSeparator($file, $separatorOption, $issues);
        if (null === $separator) {
            return new ImportPreview($filename, self::FORMAT, null, [], [], $issues);
        }

        $handle = @fopen($file->getPathname(), 'r');
        if (!\is_resource($handle)) {
            return new ImportPreview($filename, self::FORMAT, null, [], [], [
                new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The CSV file could not be read.'),
            ]);
        }

        $headers = [];
        $rows = [];
        $physicalRow = 0;

        while (($line = fgetcsv($handle, 0, $separator, '"', '')) !== false) {
            ++$physicalRow;
            $line = $this->normalizeRow($line, $encoding, $issues);

            if ($physicalRow > self::MAX_PREVIEW_ROWS + ($firstRowContainsHeaders ? 1 : 0)) {
                $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, \sprintf('Only the first %d rows are shown in the preview.', self::MAX_PREVIEW_ROWS));
                break;
            }

            if ($firstRowContainsHeaders && 1 === $physicalRow) {
                $headers = $this->normalizeHeaders($this->limitColumns($line, $issues));
                continue;
            }

            if ([] === $headers) {
                $headers = $this->buildDefaultHeaders(\count($line));
            }

            $rows[] = $this->limitColumns($line, $issues);
        }

        fclose($handle);

        if ([] === $headers && [] === $rows && !$this->hasErrors($issues)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, 'The CSV file does not contain any previewable rows.');
        }

        if (null !== $importConfig && [] !== $headers) {
            [$headers, $rows] = $this->getImportPreviewValidator()->validate($headers, $rows, $importConfig, $firstRowContainsHeaders, $issues);
        }

        return new ImportPreview($filename, self::FORMAT, null, $headers, $rows, $this->uniqueIssues($issues));
    }

    private function getImportPreviewValidator(): ImportPreviewValidator
    {
        return $this->importPreviewValidator ??= new ImportPreviewValidator(new ImportFieldHeaderResolver());
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function validateUpload(UploadedFile $file, array &$issues): void
    {
        if (!$file->isValid()) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The uploaded file is not valid.');

            return;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!\in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'Only CSV files are accepted.');
        }

        $mimeType = $file->getMimeType();
        if (!\is_string($mimeType) || !\in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The uploaded file type is not accepted as CSV.');
        }

        $size = $file->getSize();
        if (null === $size || $size > self::MAX_FILE_SIZE_BYTES) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, \sprintf('The CSV file must be %s MB or smaller.', (string) (self::MAX_FILE_SIZE_BYTES / 1_048_576)));
        }
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function resolveSeparator(UploadedFile $file, string $separatorOption, array &$issues): ?string
    {
        $separatorOption = strtolower(trim($separatorOption));

        if (isset(self::SEPARATORS[$separatorOption])) {
            return self::SEPARATORS[$separatorOption];
        }

        if ('auto' !== $separatorOption) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The selected separator is not supported.');

            return null;
        }

        $sample = @file_get_contents($file->getPathname(), false, null, 0, 4096);
        if (!\is_string($sample)) {
            $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The CSV file could not be read.');

            return null;
        }

        $scores = [];

        foreach (self::SEPARATORS as $name => $separator) {
            $scores[$name] = substr_count($sample, $separator);
        }

        arsort($scores);
        $detected = array_key_first($scores);

        return \is_string($detected) && $scores[$detected] > 0 ? self::SEPARATORS[$detected] : self::SEPARATORS['comma'];
    }

    /**
     * @param list<string|null> $headers
     *
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(static fn (?string $header): string => null === $header ? '' : $header, $headers);
    }

    /**
     * @param list<string|null>        $row
     * @param list<ImportPreviewIssue> $issues
     *
     * @return list<string|null>
     */
    private function limitColumns(array $row, array &$issues): array
    {
        if (\count($row) <= self::MAX_PREVIEW_COLUMNS) {
            return $row;
        }

        $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, \sprintf('Only the first %d columns are shown in the preview.', self::MAX_PREVIEW_COLUMNS));

        return \array_slice($row, 0, self::MAX_PREVIEW_COLUMNS);
    }

    /**
     * @param list<string|null>        $row
     * @param list<ImportPreviewIssue> $issues
     *
     * @return list<string|null>
     */
    private function normalizeRow(array $row, string $encoding, array &$issues): array
    {
        return array_map(
            function (?string $value) use ($encoding, &$issues): ?string {
                if (null === $value || 'UTF-8' === $encoding) {
                    return $value;
                }

                $converted = $this->convertEncoding($value, $encoding);
                if (null === $converted) {
                    $issues[] = new ImportPreviewIssue(ImportPreviewIssue::WARNING, 'Some values could not be converted to UTF-8 and were shown unchanged.');

                    return $value;
                }

                return $converted;
            },
            $row,
        );
    }

    private function convertEncoding(string $value, string $encoding): ?string
    {
        if (\function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($value, 'UTF-8', $encoding);

            return \is_string($converted) ? $converted : null;
        }

        $converted = @iconv($encoding, 'UTF-8//IGNORE', $value);

        return \is_string($converted) ? $converted : null;
    }

    /**
     * @return list<string>
     */
    private function buildDefaultHeaders(int $count): array
    {
        $count = min($count, self::MAX_PREVIEW_COLUMNS);
        $headers = [];

        for ($i = 1; $i <= $count; ++$i) {
            $headers[] = 'Column ' . $i;
        }

        return $headers;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function resolveEncoding(string $encoding, array &$issues): ?string
    {
        foreach (self::ALLOWED_ENCODINGS as $allowedEncoding) {
            if (0 === strcasecmp($allowedEncoding, trim($encoding))) {
                return $allowedEncoding;
            }
        }

        $issues[] = new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'The selected encoding is not supported.');

        return null;
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        return '' === trim($filename) ? 'uploaded.csv' : $filename;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private function hasErrors(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     *
     * @return list<ImportPreviewIssue>
     */
    private function uniqueIssues(array $issues): array
    {
        $unique = [];

        foreach ($issues as $issue) {
            $key = $issue->severity . ':' . $issue->message;
            $unique[$key] = $issue;
        }

        return array_values($unique);
    }
}
