<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Export;

use ArrayIterator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\FieldMapping;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Event\Export\AfterExportEvent;
use JorisDugue\EasyAdminExtraBundle\Event\Export\BeforeExportEvent;
use JorisDugue\EasyAdminExtraBundle\Event\Export\BeforeExportRowEvent;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Exporter\CsvExporter;
use JorisDugue\EasyAdminExtraBundle\Factory\Export\ExportContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\EntityQueryBuilderFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationContextFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportPreviewInspector;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExporterRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use UnitEnum;

final class ExportManagerTest extends TestCase
{
    public function testPreviewAndExportShareXmlPipelineAndLabels(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new ExportManagerEntity('Alice')]);
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager);

        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $preview = $manager->preview(ExportManagerCrudController::class, ExportFormat::XML, $request);

        self::assertSame(ExportFormat::XML, $preview->format);
        self::assertSame(OperationScopeResolver::SCOPE_ALL, $preview->scope);
        self::assertSame(['Name'], $preview->headers);
        self::assertSame([['Alice']], $preview->rows);
        self::assertSame('Export XML', $preview->formatLabels[ExportFormat::XML]);

        $response = $manager->export(ExportManagerCrudController::class, ExportFormat::XML, $request);

        self::assertSame('captured-xml', $response->getContent());
        self::assertSame(1, $capturingExporter->calls);
        self::assertNotNull($capturingExporter->lastPayload);
        self::assertSame(ExportFormat::XML, $capturingExporter->lastPayload?->format);
        self::assertSame(['Name'], $capturingExporter->lastPayload?->headers);
    }

    public function testBeforeExportEventIsDispatchedBeforeResponseCreation(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new ExportManagerEntity('Alice')]);
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $eventDispatcher = new EventDispatcher();
        $events = [];
        $eventDispatcher->addListener(BeforeExportEvent::class, static function (BeforeExportEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager, $eventDispatcher);

        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $manager->export(ExportManagerCrudController::class, ExportFormat::XML, $request);

        self::assertCount(1, $events);
        self::assertSame(ExportFormat::XML, $events[0]->getContext()->format);
        self::assertSame(['Name'], $events[0]->getPayload()->headers);
    }

    public function testAfterExportEventIsDispatchedAfterResponseCreation(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new ExportManagerEntity('Alice')]);
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $eventDispatcher = new EventDispatcher();
        $events = [];
        $eventDispatcher->addListener(AfterExportEvent::class, static function (AfterExportEvent $event) use (&$events): void {
            $events[] = $event;
        });

        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager, $eventDispatcher);

        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $response = $manager->export(ExportManagerCrudController::class, ExportFormat::XML, $request);

        self::assertCount(1, $events);
        self::assertSame($response, $events[0]->getResponse());
        self::assertSame(['Name'], $events[0]->getPayload()->headers);
    }

    public function testBeforeExportRowEventCanMutateRowAndExportedOutput(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new ExportManagerEntity('alice@example.com')]);
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(BeforeExportRowEvent::class, static function (BeforeExportRowEvent $event): void {
            $emailIndex = array_search('name', $event->getProperties(), true);
            self::assertSame(0, $emailIndex);

            $row = $event->getRow();
            $row[$emailIndex] = 'masked@example.com';
            $event->setRow($row);
        });

        $manager = $this->createManager(new CsvExporter(new SpreadsheetCellSanitizerService()), $entityManager, $eventDispatcher);

        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $response = $manager->export(ExportManagerCrudController::class, ExportFormat::CSV, $request);

        self::assertInstanceOf(StreamedResponse::class, $response);

        $content = $this->getStreamedResponseContent($response);

        self::assertStringContainsString('masked@example.com', $content);
        self::assertStringNotContainsString('alice@example.com', $content);
    }

    public function testHiddenVisibleFormatAndRoleBehaviorRemainUnchangedWithEvents(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new ExportManagerEntity('Alice')]);
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $manager = $this->createManager(new CapturingExporter(ExportFormat::XML), $entityManager, new EventDispatcher());

        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $preview = $manager->preview(ExportManagerCrudController::class, ExportFormat::XML, $request);
        $response = $manager->export(ExportManagerCrudController::class, ExportFormat::XML, $request);

        self::assertSame(['Name'], $preview->headers);
        self::assertSame([['Alice']], $preview->rows);
        self::assertSame('captured-xml', $response->getContent());
    }

    public function testBatchExportRejectsEmptySelectionEarly(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager);

        $this->expectException(InvalidBatchExportException::class);

        $manager->exportBatch(ExportManagerCrudController::class, ExportFormat::XML, [], new Request());
    }

    public function testBatchExportRejectsUnsupportedFormat(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager);

        $this->expectException(InvalidExportConfigurationException::class);
        $this->expectExceptionMessage('is not enabled for CRUD');

        $manager->exportBatch(ExportManagerCrudController::class, ExportFormat::JSON, ['42'], new Request());
    }

    public function testBatchExportKeepsCurrentBehaviorForIdsFilteredOutByCrudQuery(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, []);
        $queryBuilder->method('getRootAliases')->willReturn(['entity']);
        $queryBuilder->expects(self::once())
            ->method('andWhere')
            ->with('entity.id IN (:selectedIds)')
            ->willReturnSelf();
        $queryBuilder->expects(self::once())
            ->method('setParameter')
            ->with('selectedIds', [42])
            ->willReturnSelf();
        ExportManagerCrudController::$queryBuilder = $queryBuilder;

        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager);
        $request = $this->createBatchRequestWithIndexContext();

        $response = $manager->exportBatch(ExportManagerCrudController::class, ExportFormat::XML, ['42'], $request);

        self::assertSame('captured-xml', $response->getContent());
        self::assertSame([], iterator_to_array($capturingExporter->lastPayload?->rows ?? new ArrayIterator()));
    }

    private function createManager(ExporterInterface $exporter, EntityManagerInterface $entityManager, ?EventDispatcherInterface $eventDispatcher = null): ExportManager
    {
        $crudController = new ExportManagerCrudController();

        $container = new class($crudController) implements ContainerInterface {
            public function __construct(private readonly ExportManagerCrudController $crudController) {}

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                if (ExportManagerCrudController::class === $id) {
                    return $this->crudController;
                }

                throw new InvalidArgumentException('Unknown service: ' . $id);
            }

            public function has(string $id): bool
            {
                return ExportManagerCrudController::class === $id;
            }

            public function initialized(string $id): bool
            {
                return $this->has($id);
            }

            public function set(string $id, ?object $service): void
            {
                throw new LogicException('Not needed in tests.');
            }

            public function reset(): void {}

            public function getParameter(string $name): array|bool|string|int|float|UnitEnum|null
            {
                throw new LogicException('Not needed in tests.');
            }

            public function hasParameter(string $name): bool
            {
                return false;
            }

            public function setParameter(string $name, array|bool|string|int|float|UnitEnum|null $value): void
            {
                throw new LogicException('Not needed in tests.');
            }
        };

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $securityContainer = new class($tokenStorage) implements \Psr\Container\ContainerInterface {
            public function __construct(private readonly TokenStorageInterface $tokenStorage) {}

            public function get(string $id): mixed
            {
                if ('security.token_storage' === $id) {
                    return $this->tokenStorage;
                }

                throw new InvalidArgumentException('Unknown security service: ' . $id);
            }

            public function has(string $id): bool
            {
                return 'security.token_storage' === $id;
            }
        };

        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $operationContextResolver = new OperationContextResolver(
            new CollectionFactoryCompat(),
            new FilterFactory($this->createMock(AdminContextProviderInterface::class), []),
        );
        $managerRegistry = $this->createManagerRegistry($entityManager);

        return new ExportManager(
            new CrudControllerResolver($container),
            new ExportConfigFactory(),
            new ExportContextFactory(
                new Security($securityContainer),
                new OperationScopeResolver(new ActiveIndexContextResolver()),
                new EntityMetadataResolver($managerRegistry),
                new OperationContextFactory(),
            ),
            new ExportPayloadFactory(
                new ExportFieldValueResolver(new PropertyValueReader()),
                new FilenameResolver(),
                new ExportFieldFormatResolver(),
                new class implements ExportCountResolverInterface {
                    public function count(QueryBuilder $queryBuilder, object $crudController): int
                    {
                        return 1;
                    }
                },
                $eventDispatcher ?? new EventDispatcher(),
            ),
            new EntityQueryBuilderFactory(
                $operationContextResolver,
                new EntityMetadataResolver($managerRegistry),
                new EntitySelectionResolver(),
            ),
            new ExportPreviewInspector(),
            new ExporterRegistry([$exporter]),
            new ExportSetMetadataResolver(),
            new RoleAuthorizationChecker($authorizationChecker),
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    private function createManagerRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getFieldMapping')->with('id')->willReturn(new FieldMapping('integer', 'id', 'id'));
        $entityManager->method('getClassMetadata')->with(ExportManagerEntity::class)->willReturn($metadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->with(ExportManagerEntity::class)->willReturn($entityManager);

        return $managerRegistry;
    }

    private function createBatchRequestWithIndexContext(): Request
    {
        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, 'index');

        $crudDto = new CrudDto();
        $crudDto->setControllerFqcn(ExportManagerCrudController::class);
        $crudDto->setEntityFqcn(ExportManagerEntity::class);
        $crudDto->setFiltersConfig(new FilterConfigDto());

        $metadata = $this->createMock(ClassMetadata::class);
        $entityDto = new EntityDto(ExportManagerEntity::class, $metadata);
        $searchDto = new SearchDto($request, null, null, [], [], []);

        $context = AdminContext::forTesting(crudContext: CrudContext::forTesting(
            crudDto: $crudDto,
            entityDto: $entityDto,
            searchDto: $searchDto,
        ));
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return $request;
    }

    private function getStreamedResponseContent(StreamedResponse $response): string
    {
        $callback = $response->getCallback();

        self::assertNotNull($callback);

        ob_start();
        $callback();

        return (string) ob_get_clean();
    }

    /**
     * @param list<object> $rows
     */
    private function createConfiguredQueryBuilder(EntityManagerInterface $entityManager, array $rows): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        $query->method('toIterable')->willReturn($rows);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('resetDQLPart')->willReturnSelf();
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getEntityManager')->willReturn($entityManager);
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }
}

#[AdminExport(
    filename: 'users-{format}',
    formats: [ExportFormat::CSV, ExportFormat::XML],
    previewEnabled: true,
)]
final class ExportManagerCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static QueryBuilder $queryBuilder;

    public static function getEntityFqcn(): string
    {
        return ExportManagerEntity::class;
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return [TextExportField::new('name', 'Name')];
    }

    public function configureFields(string $pageName): iterable
    {
        return ['name'];
    }

    public function createExportAllQueryBuilder(): QueryBuilder
    {
        return self::$queryBuilder;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return self::$queryBuilder;
    }
}

final readonly class ExportManagerEntity
{
    public function __construct(public string $name) {}
}

final class CapturingExporter implements ExporterInterface
{
    public int $calls = 0;
    public ?ExportPayload $lastPayload = null;

    public function __construct(private readonly string $format) {}

    public function getFormat(): string
    {
        return $this->format;
    }

    public function export(ExportPayload $payload): Response
    {
        ++$this->calls;
        $this->lastPayload = $payload;

        return new Response('captured-' . $this->format);
    }
}
