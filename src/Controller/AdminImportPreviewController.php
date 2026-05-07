<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportReadOptions;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportReaderRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Import\TemporaryImportStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminImportPreviewController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'jd_import_preview';

    public function __construct(
        private readonly ImportReaderRegistry $importReaderRegistry,
        private readonly TemporaryImportStorage $temporaryImportStorage,
        private readonly ImportConfigFactory $importConfigFactory,
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly OperationRequestMetadataResolver $operationRequestMetadataResolver,
        private readonly OperationAdminContextFactory $operationAdminContextFactory,
    ) {}

    public function __invoke(Request $request): Response
    {
        $metadata = $this->operationRequestMetadataResolver->resolveWithoutFormat($request, 'import preview');
        $this->operationAdminContextFactory->createForRequest($request, $metadata, $this->crudActionNameResolver->resolve($request));

        $selectedSeparator = $this->normalizeString($request->request->get('separator'), 'auto');
        $selectedEncoding = $this->normalizeString($request->request->get('encoding'), 'UTF-8');
        $firstRowContainsHeaders = $request->request->getBoolean('first_row_contains_headers', false);
        $reader = $this->importReaderRegistry->get(CsvPreviewReader::FORMAT_KEY);
        $preview = $reader->createEmptyPreview();
        $importConfig = null;
        $importToken = null;

        try {
            $importConfig = $this->importConfigFactory->create($metadata->crudControllerFqcn);
        } catch (InvalidImportConfigurationException $exception) {
            $preview = $reader->createErrorPreview($exception->getMessage());
        } catch (Throwable) {
            $preview = $reader->createErrorPreview('easy_admin_extra.import.preview.errors.fields_resolution_failed');
        }

        if ($request->isMethod('POST')) {
            $token = $this->normalizeString($request->request->get('_token'), '');
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
                throw $this->createAccessDeniedException('The import preview request is not valid.');
            }

            if (null !== $importConfig) {
                $uploadedFile = $this->resolveUploadedFile($request);
                $preview = $reader->read(
                    $uploadedFile,
                    ImportReadOptions::csv($selectedSeparator, $selectedEncoding, $firstRowContainsHeaders),
                    $importConfig,
                );

                if (null !== $uploadedFile && !$this->hasBlockingPreviewIssues($preview->issues)) {
                    $temporaryFile = $this->temporaryImportStorage->store(
                        $uploadedFile,
                        $metadata->crudControllerFqcn,
                        $selectedSeparator,
                        $selectedEncoding,
                        $firstRowContainsHeaders,
                        CsvPreviewReader::FORMAT_KEY,
                    );
                    $importToken = $temporaryFile->token;
                }
            }
        }

        return $this->render('@JorisDugueEasyAdminExtraBundle/import/preview.html.twig', [
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            'confirm_csrf_token_id' => 'jd_import_confirm',
            'preview' => $preview,
            'import_token' => $importToken,
            'can_confirm_import' => null !== $importToken,
            'import_preview_route' => $this->normalizeString($request->attributes->get('_jd_ea_extra_import_preview_route'), ''),
            'import_confirm_route' => $this->normalizeString($request->attributes->get('_jd_ea_extra_import_confirm_route'), ''),
            'import_result' => null,
            'selected_separator' => $selectedSeparator,
            'selected_encoding' => $selectedEncoding,
            'first_row_contains_headers' => $firstRowContainsHeaders,
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

    private function resolveUploadedFile(Request $request): ?UploadedFile
    {
        $file = $request->files->get('csv_file');

        return $file instanceof UploadedFile ? $file : null;
    }

    private function normalizeString(mixed $value, string $default): string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : $default;
    }

    /**
     * @param list<\JorisDugue\EasyAdminExtraBundle\Dto\ImportPreviewIssue> $issues
     */
    private function hasBlockingPreviewIssues(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue->isError()) {
                return true;
            }
        }

        return false;
    }
}
