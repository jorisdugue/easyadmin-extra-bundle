<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportResult;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminImportConfirmController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'jd_import_confirm';

    public function __construct(
        private readonly ImportManager $importManager,
        private readonly CsvPreviewReader $csvPreviewReader,
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly OperationRequestMetadataResolver $operationRequestMetadataResolver,
        private readonly OperationAdminContextFactory $operationAdminContextFactory,
    ) {}

    public function __invoke(Request $request): Response
    {
        $metadata = $this->operationRequestMetadataResolver->resolveWithoutFormat($request, 'import confirm');
        $this->operationAdminContextFactory->createForRequest($request, $metadata, $this->crudActionNameResolver->resolve($request));

        $token = $this->normalizeString($request->request->get('_token'), '');
        if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            throw $this->createAccessDeniedException('The import confirmation request is not valid.');
        }

        $importToken = $this->normalizeString($request->request->get('import_token'), '');
        $result = $this->importManager->confirm($importToken, $metadata->crudControllerFqcn);
        if ($this->isInvalidConfirmationResult($result)) {
            return $this->redirectToPreviewWithInvalidConfirmationMessage($request);
        }

        $preview = $result->preview ?? $this->csvPreviewReader->createEmptyPreview();
        $temporaryFile = $result->temporaryFile;

        return $this->render('@JorisDugueEasyAdminExtraBundle/import/preview.html.twig', [
            'csrf_token_id' => 'jd_import_preview',
            'confirm_csrf_token_id' => self::CSRF_TOKEN_ID,
            'preview' => $preview,
            'import_result' => $result,
            'import_token' => $result->success ? null : $temporaryFile?->token,
            'can_confirm_import' => !$result->success && null !== $temporaryFile && !$this->hasBlockingPreviewIssues($preview->issues),
            'import_preview_route' => $this->normalizeString($request->attributes->get('_jd_ea_extra_import_preview_route'), ''),
            'import_confirm_route' => $this->normalizeString($request->attributes->get('_jd_ea_extra_import_confirm_route'), ''),
            'selected_separator' => null === $temporaryFile ? 'auto' : $temporaryFile->separator,
            'selected_encoding' => null === $temporaryFile ? 'UTF-8' : $temporaryFile->encoding,
            'first_row_contains_headers' => null !== $temporaryFile && $temporaryFile->firstRowContainsHeaders,
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

    private function normalizeString(mixed $value, string $default): string
    {
        return \is_string($value) && '' !== trim($value) ? trim($value) : $default;
    }

    private function isInvalidConfirmationResult(ImportResult $result): bool
    {
        return !$result->success
            && null === $result->temporaryFile
            && [ImportManager::INVALID_CONFIRMATION_MESSAGE] === $result->errors;
    }

    private function redirectToPreviewWithInvalidConfirmationMessage(Request $request): RedirectResponse
    {
        $this->addFlash('danger', 'The import confirmation request is not valid or has expired. Please upload the CSV file again.');

        return $this->redirectToRoute($this->normalizeString($request->attributes->get('_jd_ea_extra_import_preview_route'), ''));
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
