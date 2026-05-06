<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AdminImportPreviewController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'jd_import_preview';

    public function __construct(
        private readonly CsvPreviewReader $csvPreviewReader,
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
        $preview = $this->csvPreviewReader->createEmptyPreview();
        $importConfig = null;

        try {
            $importConfig = $this->importConfigFactory->create($metadata->crudControllerFqcn);
        } catch (InvalidImportConfigurationException $exception) {
            $preview = $this->csvPreviewReader->createErrorPreview($exception->getMessage());
        } catch (Throwable) {
            $preview = $this->csvPreviewReader->createErrorPreview('The import fields could not be resolved. Please check the import configuration.');
        }

        if ($request->isMethod('POST')) {
            $token = $this->normalizeString($request->request->get('_token'), '');
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
                throw $this->createAccessDeniedException('The import preview request is not valid.');
            }

            if (null !== $importConfig) {
                $preview = $this->csvPreviewReader->preview(
                    $this->resolveUploadedFile($request),
                    $selectedSeparator,
                    $selectedEncoding,
                    $firstRowContainsHeaders,
                    $importConfig,
                );
            }
        }

        return $this->render('@JorisDugueEasyAdminExtraBundle/import/preview.html.twig', [
            'csrf_token_id' => self::CSRF_TOKEN_ID,
            'preview' => $preview,
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
}
