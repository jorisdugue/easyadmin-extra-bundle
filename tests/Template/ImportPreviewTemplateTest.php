<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Template;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreview;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportResult;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

final class ImportPreviewTemplateTest extends TestCase
{
    public function testSuccessfulImportDoesNotRenderUploadForm(): void
    {
        $html = $this->renderImportPreview([
            'import_result' => new ImportResult(true, importedCount: 2),
            'preview' => new ImportPreview(null, 'CSV', null, [], [], []),
        ]);

        self::assertStringContainsString('2 row(s) imported successfully.', $html);
        self::assertStringNotContainsString('name="csv_file"', $html);
        self::assertStringNotContainsString('Upload CSV file', $html);
        self::assertStringNotContainsString('Validation summary', $html);
        self::assertStringNotContainsString('File metadata', $html);
        self::assertStringNotContainsString('Only the first rows are displayed.', $html);
    }

    public function testSuccessfulImportShowsImportAnotherCsvLink(): void
    {
        $html = $this->renderImportPreview([
            'import_result' => new ImportResult(true, importedCount: 1),
            'preview' => new ImportPreview(null, 'CSV', null, [], [], []),
        ]);

        self::assertStringContainsString('href="/admin/stats/import/preview"', $html);
        self::assertStringContainsString('Import another CSV file', $html);
    }

    public function testFailedImportStillRendersUploadFormAndErrors(): void
    {
        $html = $this->renderImportPreview([
            'import_result' => new ImportResult(false, errors: ['No rows were imported.']),
            'preview' => new ImportPreview('users.csv', 'CSV', null, [], [], [
                new ImportPreviewIssue(ImportPreviewIssue::ERROR, 'Email is required.'),
            ]),
        ]);

        self::assertStringContainsString('name="csv_file"', $html);
        self::assertStringContainsString('Upload CSV file', $html);
        self::assertStringContainsString('Validation summary', $html);
        self::assertStringContainsString('Email is required.', $html);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function renderImportPreview(array $parameters): string
    {
        $templatePath = \dirname(__DIR__, 2) . '/templates/import/preview.html.twig';
        $template = file_get_contents($templatePath);
        self::assertIsString($template);

        $twig = new Environment(new ArrayLoader([
            '@EasyAdmin/page/content.html.twig' => '{% block content_title %}{% endblock %}{% block main %}{% endblock %}',
            '@JorisDugueEasyAdminExtraBundle/import/preview.html.twig' => $template,
        ]));
        $twig->addExtension(new TranslationExtension(new TestTranslator()));
        $twig->addFunction(new TwigFunction('csrf_token', static fn (string $tokenId): string => $tokenId . '_value'));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => 'admin_import_preview' === $route ? '/admin/stats/import/preview' : '#'));

        return $twig->render('@JorisDugueEasyAdminExtraBundle/import/preview.html.twig', $parameters + [
            'csrf_token_id' => 'jd_import_preview',
            'confirm_csrf_token_id' => 'jd_import_confirm',
            'preview' => new ImportPreview(null, 'CSV', null, [], [], []),
            'import_result' => null,
            'import_preview_route' => 'admin_import_preview',
            'import_confirm_route' => 'admin_import_confirm',
            'import_token' => null,
            'can_confirm_import' => false,
            'selected_separator' => 'auto',
            'selected_encoding' => 'UTF-8',
            'first_row_contains_headers' => true,
            'separator_options' => [
                'auto' => 'Auto',
                'comma' => 'Comma',
                'semicolon' => 'Semicolon',
                'tab' => 'Tab',
            ],
            'encoding_options' => [
                'UTF-8',
                'ISO-8859-1',
                'Windows-1252',
            ],
        ]);
    }
}

final class TestTranslator implements TranslatorInterface
{
    /**
     * @param array<string, mixed> $parameters
     */
    public function trans(?string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $message = match ($id) {
            'easy_admin_extra.import.preview.title' => 'Import preview',
            'easy_admin_extra.import.preview.description' => 'Upload a CSV file to preview its content before importing.',
            'easy_admin_extra.import.preview.notice_no_persistence' => 'No data is persisted during preview.',
            'easy_admin_extra.import.preview.validation_summary' => 'Validation summary',
            'easy_admin_extra.import.preview.metadata.title' => 'File metadata',
            'easy_admin_extra.import.preview.result.helper' => 'Only the first rows are displayed.',
            'easy_admin_extra.import.preview.import_result.title' => 'Import result',
            'easy_admin_extra.import.preview.import_result.success' => '%count% row(s) imported successfully.',
            'easy_admin_extra.import.preview.import_result.failure' => 'No rows were imported.',
            'easy_admin_extra.import.preview.import_result.import_another' => 'Import another CSV file',
            'easy_admin_extra.import.preview.form.title' => 'Upload CSV file',
            'easy_admin_extra.import.preview.form.file' => 'CSV file',
            'easy_admin_extra.import.preview.form.separator' => 'Separator',
            'easy_admin_extra.import.preview.form.encoding' => 'Encoding',
            'easy_admin_extra.import.preview.form.headers' => 'Headers',
            'easy_admin_extra.import.preview.form.first_row_contains_headers' => 'First row contains headers',
            'easy_admin_extra.import.preview.form.submit' => 'Preview CSV',
            default => (string) $id,
        };

        foreach ($parameters as $key => $value) {
            $message = str_replace($key, (string) $value, $message);
        }

        return $message;
    }

    public function getLocale(): string
    {
        return 'en';
    }
}
