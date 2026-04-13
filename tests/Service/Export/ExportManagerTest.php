<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Export;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
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
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExporterRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use LogicException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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

    public function testBatchExportRejectsEmptySelectionEarly(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $capturingExporter = new CapturingExporter(ExportFormat::XML);
        $manager = $this->createManager($capturingExporter, $entityManager);

        $this->expectException(InvalidBatchExportException::class);

        $manager->exportBatch(ExportManagerCrudController::class, ExportFormat::XML, [], new Request());
    }

    private function createManager(CapturingExporter $exporter, EntityManagerInterface $entityManager): ExportManager
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

        return new ExportManager(
            new CrudControllerResolver($container),
            new ExportConfigFactory(),
            new ExportContextFactory(
                new Security($securityContainer),
                new OperationScopeResolver(),
                new EntityMetadataResolver($entityManager),
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
            ),
            new EntityQueryBuilderFactory(
                $operationContextResolver,
                new EntityMetadataResolver($entityManager),
                new EntitySelectionResolver(),
            ),
            new ExportPreviewInspector(),
            new ExporterRegistry([$exporter]),
            $authorizationChecker,
            new ExportSetMetadataResolver(),
        );
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

    public function createExportAllQueryBuilder(): QueryBuilder
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
