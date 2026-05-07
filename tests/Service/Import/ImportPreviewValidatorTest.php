<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use DateTime;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceImportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateImportField;
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportFieldHeaderResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportPreviewValidator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ImportPreviewValidatorTest extends TestCase
{
    private ImportPreviewValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ImportPreviewValidator(new ImportFieldHeaderResolver());
    }

    public function testItReportsUnknownCsvColumns(): void
    {
        $issues = [];

        $this->validator->validate(
            ['Name', 'Ignored'],
            [['Alice', 'value']],
            new ImportConfig([TextImportField::new('name', 'Name')]),
            true,
            $issues,
        );

        self::assertIssueMessagesContain($issues, 'Unknown CSV column "Ignored" was ignored.');
    }

    public function testItDoesNotReportUnknownCsvColumnsWithoutHeaders(): void
    {
        $issues = [];

        [, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3', 'Column 4'],
            [['id', 'uuid', 'typeAction', 'lang']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
            false,
            $issues,
        );

        self::assertSame([['uuid', 'typeAction']], $rows);
        self::assertIssueMessagesDoNotContain($issues, 'Unknown CSV column');
        self::assertIssueMessagesDoNotContain($issues, 'id');
        self::assertIssueMessagesDoNotContain($issues, 'lang');
    }

    public function testItReportsExtraCsvColumnsByPositionWithoutHeaders(): void
    {
        $issues = [];

        $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3'],
            [['Alice', 'alice@example.com', 'extra']],
            new ImportConfig([
                TextImportField::new('name', 'Name'),
                TextImportField::new('email', 'Email'),
            ]),
            false,
            $issues,
        );

        self::assertIssueMessagesContain($issues, 'Extra CSV column at position 3 was ignored.');
    }

    public function testItMapsColumnsByConfiguredOrderWithoutHeaders(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2'],
            [['Alice', 'alice@example.com']],
            new ImportConfig([
                TextImportField::new('name', 'Name'),
                TextImportField::new('email', 'Email'),
            ]),
            false,
            $issues,
        );

        self::assertSame(['Name', 'Email'], $headers);
        self::assertSame([['Alice', 'alice@example.com']], $rows);
        self::assertSame([], $issues);
    }

    public function testItMapsColumnsByExplicitPositionsWithoutHeaders(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3'],
            [['ignored-id', 'abc-123', 'create']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
            false,
            $issues,
        );

        self::assertSame(['UUID', 'Type action'], $headers);
        self::assertSame([['abc-123', 'create']], $rows);
        self::assertSame([], $issues);
    }

    public function testExplicitModeMapsConfiguredPositionsAndImplicitlySkipsUnmappedColumns(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3', 'Column 4', 'Column 5', 'Column 6'],
            [['id-1', 'abc-123', 'create', 'en', 'web', '2026-05-06']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                ChoiceImportField::new('typeAction', 'Type action')->setChoices(['create' => 'Create'])->position(3),
                ChoiceImportField::new('lang', 'Language')->setChoices(['en' => 'English'])->position(4),
                DateImportField::new('createdAt', 'Created at')->position(6),
            ]),
            false,
            $issues,
        );

        self::assertSame(['UUID', 'Type action', 'Language', 'Created at'], $headers);
        self::assertInstanceOf(DateTime::class, $rows[0][3]);
        self::assertSame('2026-05-06', $rows[0][3]->format('Y-m-d'));
        self::assertSame([], $issues);
    }

    public function testNoHeaderModeMapsByCsvIndexOnlyEvenWhenValuesLookLikeFieldNames(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3'],
            [['id', 'uuid', 'typeAction']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
            false,
            $issues,
        );

        self::assertSame(['UUID', 'Type action'], $headers);
        self::assertSame([['uuid', 'typeAction']], $rows);
        self::assertIssueMessagesDoNotContain($issues, 'Unknown CSV column');
    }

    public function testMixedExplicitAndUnpositionedImportableFieldsIsConfigurationError(): void
    {
        $issues = [];

        $this->expectException(InvalidImportConfigurationException::class);
        $this->expectExceptionMessage('Mixed import mapping is ambiguous. When using position(), every importable field must define an explicit CSV column position.');

        $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3'],
            [['ignored-id', 'abc-123', 'create']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('id', 'ID'),
                TextImportField::new('typeAction', 'Type action'),
            ]),
            false,
            $issues,
        );
    }

    public function testItRejectsDuplicateConfiguredPositionsBeforeRowValidation(): void
    {
        $issues = [];

        $this->expectException(InvalidImportConfigurationException::class);
        $this->expectExceptionMessage('Duplicate import CSV column position 1 configured for fields "uuid" and "externalUuid".');

        $this->validator->validate(
            ['Column 1'],
            [['abc-123']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(1),
                TextImportField::new('externalUuid', 'External UUID')->position(1),
            ]),
            false,
            $issues,
        );
    }

    public function testIgnoredImportFieldReservesColumnAndStaysHiddenFromPreview(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Column 1', 'Column 2', 'Column 3'],
            [['ignored-id', 'abc-123', 'create']],
            new ImportConfig([
                IgnoredImportField::new('id')->position(1),
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
            false,
            $issues,
        );

        self::assertSame(['UUID', 'Type action'], $headers);
        self::assertSame([['abc-123', 'create']], $rows);
        self::assertSame([], $issues);
    }

    public function testExplicitPositionsTakePrecedenceOverHeaderMatching(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['External ID', 'External UUID', 'External Type'],
            [['ignored-id', 'abc-123', 'create']],
            new ImportConfig([
                IgnoredImportField::new('id')->position(1),
                TextImportField::new('uuid', 'UUID')->position(2),
                TextImportField::new('typeAction', 'Type action')->position(3),
            ]),
            true,
            $issues,
        );

        self::assertSame(['UUID', 'Type action'], $headers);
        self::assertSame([['abc-123', 'create']], $rows);
        self::assertSame([], $issues);
    }

    public function testItReportsMissingRequiredPositionedValues(): void
    {
        $issues = [];

        [, $rows] = $this->validator->validate(
            ['Column 1'],
            [['abc-123']],
            new ImportConfig([
                TextImportField::new('uuid', 'UUID')->position(1),
                TextImportField::new('typeAction', 'Type action')->position(2)->required(),
            ]),
            false,
            $issues,
        );

        self::assertSame([['abc-123', null]], $rows);
        self::assertIssueMessagesContain($issues, 'Row 1, field "Type action": This value is required.');
    }

    public function testItReportsMissingRequiredColumns(): void
    {
        $issues = [];

        $this->validator->validate(
            ['Name'],
            [['Alice']],
            new ImportConfig([TextImportField::new('email', 'Email')->required()]),
            true,
            $issues,
        );

        self::assertIssueMessagesContain($issues, 'Required CSV column "Email" is missing.');
    }

    public function testItValidatesChoiceValuesAgainstKeys(): void
    {
        $issues = [];
        $field = ChoiceImportField::new('status', 'Status')->setChoices([
            'draft' => 'Draft',
            'published' => 'Published',
        ]);

        [, $rows] = $this->validator->validate(
            ['Status'],
            [['Draft'], ['draft']],
            new ImportConfig([$field]),
            true,
            $issues,
        );

        self::assertSame([['Draft'], ['draft']], $rows);
        self::assertIssueMessagesContain($issues, 'Row 1, field "Status": The selected value is not valid.');
        self::assertCount(1, $issues);
    }

    public function testItValidatesDateValueAgainstConfiguredFormat(): void
    {
        $issues = [];
        $field = DateImportField::new('publishedAt', 'Published at')->setFormat('d/m/Y');

        [, $rows] = $this->validator->validate(
            ['Published at'],
            [['06/05/2026'], ['2026-05-06']],
            new ImportConfig([$field]),
            true,
            $issues,
        );

        self::assertInstanceOf(DateTime::class, $rows[0][0]);
        self::assertSame('06/05/2026', $rows[0][0]->format('d/m/Y'));
        self::assertNull($rows[1][0]);
        self::assertIssueMessagesContain($issues, 'Row 2, field "Published at": The date value is not valid.');
        self::assertCount(1, $issues);
    }

    public function testItAcceptsDateTimeInterfaceReturnedByTransformer(): void
    {
        $issues = [];
        $field = DateImportField::new('publishedAt', 'Published at')
            ->setFormat('Y-m-d')
            ->transformUsing(static fn (?string $value): DateTime => new DateTime((string) $value));

        [, $rows] = $this->validator->validate(
            ['Published at'],
            [['2026-05-06']],
            new ImportConfig([$field]),
            true,
            $issues,
        );

        self::assertInstanceOf(DateTime::class, $rows[0][0]);
        self::assertSame('2026-05-06', $rows[0][0]->format('Y-m-d'));
        self::assertSame([], $issues);
    }

    public function testItReportsTransformerExceptionsAsFieldErrors(): void
    {
        $issues = [];
        $field = TextImportField::new('name', 'Name')
            ->transformUsing(static fn (): string => throw new RuntimeException('Internal detail'));

        [, $rows] = $this->validator->validate(
            ['Name'],
            [['Alice']],
            new ImportConfig([$field]),
            true,
            $issues,
        );

        self::assertSame([[null]], $rows);
        self::assertIssueMessagesContain($issues, 'Row 1, field "Name": The value could not be transformed.');
        self::assertStringNotContainsString('Internal detail', $issues[0]->message);
    }

    public function testItDisplaysTransformedRowsInConfiguredFieldOrder(): void
    {
        $issues = [];

        [$headers, $rows] = $this->validator->validate(
            ['Email', 'Name', 'Status'],
            [[' ALICE@EXAMPLE.COM ', 'Alice', 'DRAFT']],
            new ImportConfig([
                TextImportField::new('name', 'Name'),
                TextImportField::new('email', 'Email')->transformUsing(static fn (?string $value): string => strtolower(trim((string) $value))),
                ChoiceImportField::new('status', 'Status')->setChoices(['draft' => 'Draft'])->transformUsing(static fn (?string $value): string => strtolower((string) $value)),
            ]),
            true,
            $issues,
        );

        self::assertSame(['Name', 'Email', 'Status'], $headers);
        self::assertSame([['Alice', 'alice@example.com', 'draft']], $rows);
        self::assertSame([], $issues);
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private static function assertIssueMessagesContain(array $issues, string $expectedMessage): void
    {
        self::assertContains(
            $expectedMessage,
            array_map(static fn (ImportPreviewIssue $issue): string => $issue->message, $issues),
        );
    }

    /**
     * @param list<ImportPreviewIssue> $issues
     */
    private static function assertIssueMessagesDoNotContain(array $issues, string $unexpectedMessagePart): void
    {
        foreach ($issues as $issue) {
            self::assertStringNotContainsString($unexpectedMessagePart, $issue->message);
        }
    }
}
