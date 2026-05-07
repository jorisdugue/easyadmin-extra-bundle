<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportReadOptions;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportResult;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportRowResult;
use JorisDugue\EasyAdminExtraBundle\Exception\ImportPersistenceValidationException;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Throwable;

final readonly class ImportManager
{
    public const INVALID_CONFIRMATION_MESSAGE = 'The import confirmation token is missing, expired, or invalid.';
    public const NO_MANAGER_ERROR = 'easy_admin_extra.import.preview.errors.no_doctrine_manager';
    public const PERSISTENCE_ERROR = 'easy_admin_extra.import.preview.errors.persistence_failed';

    public function __construct(
        private TemporaryImportStorage $temporaryImportStorage,
        private ImportConfigFactory $importConfigFactory,
        private ImportReaderRegistry $importReaderRegistry,
        private ImportEntityHydrator $entityHydrator,
        private ImportPersister $persister,
        private ?LoggerInterface $logger = null,
    ) {}

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    public function confirm(string $token, string $crudControllerFqcn): ImportResult
    {
        $temporaryFile = $this->temporaryImportStorage->resolve($token, $crudControllerFqcn);
        if (null === $temporaryFile) {
            return new ImportResult(false, errors: [self::INVALID_CONFIRMATION_MESSAGE]);
        }

        $config = $this->importConfigFactory->create($crudControllerFqcn);
        $uploadedFile = new UploadedFile($temporaryFile->path, $temporaryFile->clientFilename, 'text/csv', null, true);
        $reader = $this->importReaderRegistry->get($temporaryFile->format);
        $preview = $reader->read(
            $uploadedFile,
            new ImportReadOptions(
                $temporaryFile->format,
                $temporaryFile->separator,
                $temporaryFile->encoding,
                $temporaryFile->firstRowContainsHeaders,
            ),
            $config,
        );

        if ($preview->hasIssues() && $this->hasBlockingPreviewIssues($preview->issues)) {
            return new ImportResult(false, failedCount: \count($preview->rows), preview: $preview, temporaryFile: $temporaryFile);
        }

        /** @var class-string $entityFqcn */
        $entityFqcn = $crudControllerFqcn::getEntityFqcn();
        $entities = [];
        $rowResults = [];

        foreach ($preview->rows as $index => $row) {
            [$entity, $rowResult] = $this->entityHydrator->hydrate($entityFqcn, $config, $row, $index + 1);
            $rowResults[] = $rowResult;

            if (null !== $entity) {
                $entities[] = $entity;
            }
        }

        $failedRows = array_values(array_filter($rowResults, static fn (ImportRowResult $result): bool => !$result->success));
        if ([] !== $failedRows) {
            return new ImportResult(false, failedCount: \count($failedRows), rowResults: $rowResults, preview: $preview, temporaryFile: $temporaryFile);
        }

        try {
            $this->persister->persistAll($entityFqcn, $entities);
        } catch (ImportPersistenceValidationException $exception) {
            return new ImportResult(false, failedCount: \count($exception->rowResults), rowResults: $exception->rowResults, preview: $preview, temporaryFile: $temporaryFile);
        } catch (Throwable $exception) {
            $this->logPersistenceException($exception, $entityFqcn);

            return new ImportResult(false, failedCount: \count($preview->rows), rowResults: $rowResults, errors: [$this->resolvePersistenceErrorMessage($exception)], preview: $preview, temporaryFile: $temporaryFile);
        }

        $this->temporaryImportStorage->delete($temporaryFile->token);

        return new ImportResult(true, importedCount: \count($entities), rowResults: $rowResults);
    }

    /**
     * @param class-string $entityFqcn
     */
    private function logPersistenceException(Throwable $exception, string $entityFqcn): void
    {
        $this->logger?->error('Unable to persist imported rows.', [
            'entity_class' => $entityFqcn,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    private function resolvePersistenceErrorMessage(Throwable $exception): string
    {
        if ($exception instanceof RuntimeException && str_starts_with($exception->getMessage(), 'No Doctrine entity manager is available')) {
            return self::NO_MANAGER_ERROR;
        }

        return self::PERSISTENCE_ERROR;
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
