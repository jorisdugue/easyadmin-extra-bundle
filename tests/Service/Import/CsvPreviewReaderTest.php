<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CsvPreviewReaderTest extends TestCase
{
    public function testItBuildsPreviewWithHeaders(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Email\nAlice,alice@example.com\n");

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertSame('users.csv', $preview->filename);
        self::assertSame('CSV', $preview->format);
        self::assertSame(['Name', 'Email'], $preview->headers);
        self::assertSame([['Alice', 'alice@example.com']], $preview->rows);
        self::assertSame([], $preview->issues);
    }

    public function testItBuildsPreviewWithoutHeaders(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Alice,alice@example.com\nBob,bob@example.com\n");

        $preview = $reader->preview($file, 'comma', 'UTF-8', false);

        self::assertSame(['Column 1', 'Column 2'], $preview->headers);
        self::assertSame([
            ['Alice', 'alice@example.com'],
            ['Bob', 'bob@example.com'],
        ], $preview->rows);
        self::assertSame([], $preview->issues);
    }

    public function testItRejectsNonCsvExtension(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Email\nAlice,alice@example.com\n", 'users.txt', 'text/plain');

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame(ImportPreviewIssue::ERROR, $preview->issues[0]->severity);
        self::assertSame('Only CSV files are accepted.', $preview->issues[0]->message);
    }

    public function testItRejectsUnsupportedMimeType(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("GIF89a\n", 'users.csv', 'image/gif');

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame(ImportPreviewIssue::ERROR, $preview->issues[0]->severity);
        self::assertSame('The uploaded file type is not accepted as CSV.', $preview->issues[0]->message);
    }

    public function testItRejectsInvalidSeparator(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Email\nAlice,alice@example.com\n");

        $preview = $reader->preview($file, 'pipe', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame(ImportPreviewIssue::ERROR, $preview->issues[0]->severity);
        self::assertSame('The selected separator is not supported.', $preview->issues[0]->message);
    }

    public function testItRejectsUnsupportedEncoding(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Email\nAlice,alice@example.com\n");

        $preview = $reader->preview($file, 'comma', 'KOI8-R', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame(ImportPreviewIssue::ERROR, $preview->issues[0]->severity);
        self::assertSame('The selected encoding is not supported.', $preview->issues[0]->message);
    }

    public function testItAutoDetectsSeparatorFromBoundedSample(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name;Email\nAlice;alice@example.com\n");

        $preview = $reader->preview($file, 'auto', 'UTF-8', true);

        self::assertSame(['Name', 'Email'], $preview->headers);
        self::assertSame([['Alice', 'alice@example.com']], $preview->rows);
        self::assertSame([], $preview->issues);
    }

    public function testItKeepsHtmlLikeValuesAsPlainValues(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Comment\nAlice,\"<strong>Hello</strong>\"\n");

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertSame([['Alice', '<strong>Hello</strong>']], $preview->rows);
    }

    public function testItLimitsPreviewRowsAndColumns(): void
    {
        $reader = new CsvPreviewReader();
        $headers = [];

        for ($i = 1; $i <= 55; ++$i) {
            $headers[] = 'Header ' . $i;
        }

        $lines = [implode(',', $headers)];
        for ($row = 1; $row <= 25; ++$row) {
            $values = [];
            for ($column = 1; $column <= 55; ++$column) {
                $values[] = 'R' . $row . 'C' . $column;
            }
            $lines[] = implode(',', $values);
        }

        $file = $this->createUploadedFile(implode("\n", $lines) . "\n");
        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertCount(50, $preview->headers);
        self::assertCount(20, $preview->rows);
        self::assertCount(50, $preview->rows[0]);
        self::assertTrue($preview->hasIssues());
        self::assertContains('Only the first 50 columns are shown in the preview.', array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues));
        self::assertContains('Only the first 20 rows are shown in the preview.', array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues));
    }

    public function testItRejectsOversizedUpload(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile(str_repeat('a', 2_097_153));

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame('The CSV file must be 2 MB or smaller.', $preview->issues[0]->message);
    }

    private function createUploadedFile(string $contents, string $clientName = 'users.csv', string $mimeType = 'text/csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jd_csv_preview_');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $clientName, $mimeType, null, true);
    }
}
