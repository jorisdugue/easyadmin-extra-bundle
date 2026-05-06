<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
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

    public function testItUsesFirstRowAsHeadersWithImportConfig(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("Name,Email,Ignored\nAlice,alice@example.com,value\n");

        $preview = $reader->preview(
            $file,
            'comma',
            'UTF-8',
            true,
            new ImportConfig([
                TextImportField::new('name', 'Name'),
                TextImportField::new('email', 'Email'),
            ]),
        );

        self::assertSame(['Name', 'Email'], $preview->headers);
        self::assertSame([['Alice', 'alice@example.com']], $preview->rows);
        self::assertContains('Unknown CSV column "Ignored" was ignored.', array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues));
    }

    public function testItTreatsFirstRowAsDataWithImportConfigWhenHeadersAreDisabled(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("id,uuid,typeAction\n1,abc,create\n");

        $preview = $reader->preview(
            $file,
            'comma',
            'UTF-8',
            false,
            new ImportConfig([
                TextImportField::new('id', 'ID'),
                TextImportField::new('uuid', 'UUID'),
                TextImportField::new('typeAction', 'Type action'),
            ]),
        );

        self::assertSame(['ID', 'UUID', 'Type action'], $preview->headers);
        self::assertSame([
            ['id', 'uuid', 'typeAction'],
            ['1', 'abc', 'create'],
        ], $preview->rows);
        self::assertSame([], $preview->issues);
    }

    public function testItDoesNotCompareHeaderLookingFirstRowValuesWhenHeadersAreDisabled(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile("id,uuid,typeAction,lang\n1,abc,create,en\n");

        $preview = $reader->preview(
            $file,
            'comma',
            'UTF-8',
            false,
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
        );

        self::assertSame(['UUID', 'Type action'], $preview->headers);
        self::assertSame([
            ['uuid', 'typeAction'],
            ['abc', 'create'],
        ], $preview->rows);
        self::assertNotContains('Unknown CSV column "id" was ignored.', array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues));
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

    public function testItRejectsPdfContentRenamedAsCsv(): void
    {
        $this->assertCsvContentRejected("%PDF-1.7\n1 0 obj\n", 'pdf.csv');
    }

    public function testItRejectsZipContentRenamedAsCsv(): void
    {
        $this->assertCsvContentRejected("PK\x03\x04binary zip data", 'archive.csv');
    }

    public function testItRejectsPhpContentRenamedAsCsv(): void
    {
        $this->assertCsvContentRejected("<?php echo 'not csv';\n", 'script.csv');
    }

    public function testItRejectsHtmlContentRenamedAsCsv(): void
    {
        $this->assertCsvContentRejected("<!doctype html>\n<html><body>not csv</body></html>\n", 'page.csv');
    }

    public function testItRejectsBinaryNullByteContentRenamedAsCsv(): void
    {
        $this->assertCsvContentRejected("Name,Email\nAlice\0,alice@example.com\n", 'binary.csv');
    }

    public function testItRejectsEmptyCsvFiles(): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile('');

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertSame(ImportPreviewIssue::ERROR, $preview->issues[0]->severity);
        self::assertSame('The CSV file is empty.', $preview->issues[0]->message);
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

    public function testItLimitsPreviewRowsAndRejectsTooManyColumns(): void
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

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertContains('The uploaded file is not a valid CSV file.', array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues));
    }

    public function testItLimitsPreviewRows(): void
    {
        $reader = new CsvPreviewReader();
        $lines = ['Name,Email'];
        for ($row = 1; $row <= 25; ++$row) {
            $lines[] = 'User ' . $row . ',user' . $row . '@example.com';
        }

        $file = $this->createUploadedFile(implode("\n", $lines) . "\n");
        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertCount(20, $preview->rows);
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

    private function assertCsvContentRejected(string $contents, string $clientName): void
    {
        $reader = new CsvPreviewReader();
        $file = $this->createUploadedFile($contents, $clientName, 'text/plain', true);

        $preview = $reader->preview($file, 'comma', 'UTF-8', true);

        self::assertFalse($preview->hasRows());
        self::assertTrue($preview->hasIssues());
        self::assertContains(
            'The uploaded file is not a valid CSV file.',
            array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $preview->issues),
        );
    }

    private function createUploadedFile(string $contents, string $clientName = 'users.csv', string $mimeType = 'text/csv', bool $forceMimeType = false): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jd_csv_preview_');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        if ($forceMimeType) {
            return new ForcedMimeUploadedFile($path, $clientName, $mimeType, null, true);
        }

        return new UploadedFile($path, $clientName, $mimeType, null, true);
    }
}

final class ForcedMimeUploadedFile extends UploadedFile
{
    public function getMimeType(): ?string
    {
        return $this->getClientMimeType();
    }
}
