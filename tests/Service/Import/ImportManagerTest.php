<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportReaderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreview;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportReadOptions;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportFieldHeaderResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvUploadValidator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportEntityHydrator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportManager;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportPersister;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportPreviewValidator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportReaderRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Import\TemporaryImportStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportManagerTest extends TestCase
{
    public function testNoManagerForEntityClassReturnsClearConfigurationErrorAndLogsException(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name\nAlice\n");
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Unable to persist imported rows.',
                self::callback(static fn (array $context): bool => ImportManagerEntity::class === $context['entity_class']
                    && RuntimeException::class === $context['exception_class']
                    && str_contains((string) $context['exception_message'], 'No Doctrine entity manager is available')
                    && $context['exception'] instanceof RuntimeException),
            );

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(ImportManagerEntity::class)->willReturn(null);

        $result = $this->createManager($storage, $managerRegistry, $logger)->confirm($token, ImportManagerCrudController::class);

        self::assertFalse($result->success);
        self::assertSame([ImportManager::NO_MANAGER_ERROR], $result->errors);
    }

    public function testPersistenceExceptionIsLoggedAndReturnedAsSafeImportResultIssue(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name\nAlice\n");
        $persistenceException = new RuntimeException('SQLSTATE[23000]: private database detail');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Unable to persist imported rows.',
                self::callback(static fn (array $context): bool => ImportManagerEntity::class === $context['entity_class']
                    && RuntimeException::class === $context['exception_class']
                    && 'SQLSTATE[23000]: private database detail' === $context['exception_message']
                    && $context['exception'] === $persistenceException),
            );

        $entityManager = $this->createEntityManager();
        $entityManager->method('wrapInTransaction')->willThrowException($persistenceException);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(ImportManagerEntity::class)->willReturn($entityManager);

        $result = $this->createManager($storage, $managerRegistry, $logger)->confirm($token, ImportManagerCrudController::class);

        self::assertFalse($result->success);
        self::assertSame([ImportManager::PERSISTENCE_ERROR], $result->errors);
        self::assertStringNotContainsString('SQLSTATE', $result->errors[0]);
    }

    public function testRequiredDoctrineFieldMissingReturnsRowLevelErrorBeforeFlush(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name\nAlice\n", ImportManagerRequiredCrudController::class);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $entityManager = $this->createEntityManager(ImportManagerRequiredEntity::class, [['fieldName' => 'email', 'type' => 'string', 'nullable' => false]]);
        $entityManager->expects(self::never())->method('wrapInTransaction');
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(ImportManagerRequiredEntity::class)->willReturn($entityManager);

        $result = $this->createManager($storage, $managerRegistry, $logger)->confirm($token, ImportManagerRequiredCrudController::class);

        self::assertFalse($result->success);
        self::assertSame(1, $result->failedCount);
        self::assertSame(['Required Doctrine field "email" is missing.'], $result->rowResults[0]->errors);
        self::assertSame([], $result->errors);
    }

    public function testConfirmReusesStoredFormatAndReadOptions(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "ignored by fake reader\n", ImportManagerCrudController::class, 'semicolon', 'Windows-1252', false);
        $reader = new RecordingImportReader(new ImportPreview('users.csv', 'CSV', null, ['Name'], [['Alice']], []));
        $persistedEntities = [];
        $entityManager = $this->createEntityManager();
        $entityManager->method('wrapInTransaction')
            ->willReturnCallback(static function (callable $callback) use ($entityManager): mixed {
                return $callback($entityManager);
            });
        $entityManager->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(ImportManagerEntity::class)->willReturn($entityManager);

        $result = new ImportManager(
            $storage,
            new ImportConfigFactory(),
            new ImportReaderRegistry([$reader]),
            new ImportEntityHydrator(),
            new ImportPersister($managerRegistry),
        )->confirm($token, ImportManagerCrudController::class);

        self::assertTrue($result->success);
        self::assertNotNull($reader->receivedOptions);
        self::assertSame('csv', $reader->receivedOptions->format);
        self::assertSame('semicolon', $reader->receivedOptions->separator);
        self::assertSame('Windows-1252', $reader->receivedOptions->encoding);
        self::assertFalse($reader->receivedOptions->firstRowContainsHeaders);
        self::assertCount(1, $persistedEntities);
    }

    private function createManager(TemporaryImportStorage $storage, ManagerRegistry $managerRegistry, LoggerInterface $logger): ImportManager
    {
        $csvPreviewReader = new CsvPreviewReader(new ImportPreviewValidator(new ImportFieldHeaderResolver()), new CsvUploadValidator());

        return new ImportManager(
            $storage,
            new ImportConfigFactory(),
            new ImportReaderRegistry([$csvPreviewReader]),
            new ImportEntityHydrator(),
            new ImportPersister($managerRegistry),
            $logger,
        );
    }

    private function storePreviewedCsv(
        TemporaryImportStorage $storage,
        string $contents,
        string $crudControllerFqcn = ImportManagerCrudController::class,
        string $separator = 'comma',
        string $encoding = 'UTF-8',
        bool $firstRowContainsHeaders = true,
    ): string {
        $path = tempnam(sys_get_temp_dir(), 'jd_import_manager_');
        self::assertIsString($path);
        file_put_contents($path, $contents);
        $temporaryFile = $storage->store(new UploadedFile($path, 'users.csv', 'text/csv', null, true), $crudControllerFqcn, $separator, $encoding, $firstRowContainsHeaders);

        return $temporaryFile->token;
    }

    /**
     * @param list<array<string, mixed>> $extraFields
     */
    private function createEntityManager(string $entityFqcn = ImportManagerEntity::class, array $extraFields = []): EntityManagerInterface
    {
        $metadata = new \Doctrine\ORM\Mapping\ClassMetadata($entityFqcn);
        $metadata->setIdentifier(['id']);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer']);
        $metadata->setIdGeneratorType(\Doctrine\ORM\Mapping\ClassMetadata::GENERATOR_TYPE_AUTO);

        foreach ($extraFields as $field) {
            $metadata->mapField($field);
        }

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return $entityManager;
    }
}

#[AdminImport]
final class ImportManagerCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return ImportManagerEntity::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [TextImportField::new('name', 'Name')];
    }
}

final class ImportManagerEntity
{
    public ?string $name = null;
}

#[AdminImport]
final class ImportManagerRequiredCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return ImportManagerRequiredEntity::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [TextImportField::new('name', 'Name')];
    }
}

final class ImportManagerRequiredEntity
{
    public ?int $id = null;
    public ?string $name = null;
    public ?string $email = null;
}

final class RecordingImportReader implements ImportReaderInterface
{
    public ?ImportReadOptions $receivedOptions = null;

    public function __construct(private readonly ImportPreview $preview) {}

    public function getFormat(): string
    {
        return 'csv';
    }

    public function supports(string $format): bool
    {
        return 'csv' === strtolower(trim($format));
    }

    public function createEmptyPreview(): ImportPreview
    {
        return new ImportPreview(null, 'CSV', null, [], [], []);
    }

    public function createErrorPreview(string $message): ImportPreview
    {
        return new ImportPreview(null, 'CSV', null, [], [], []);
    }

    public function read(?UploadedFile $file, ImportReadOptions $options, ?ImportConfig $importConfig = null): ImportPreview
    {
        $this->receivedOptions = $options;

        return $this->preview;
    }
}
