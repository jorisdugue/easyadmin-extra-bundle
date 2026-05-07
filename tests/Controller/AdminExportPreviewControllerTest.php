<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Factory\MenuFactoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\MainMenuDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\ActionFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\EntityFactory;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportSetMetadataProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportPreviewController;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use JorisDugue\EasyAdminExtraBundle\Factory\Export\ExportContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\EntityQueryBuilderFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationContextFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportPreviewInspector;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExporterRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use UnitEnum;

final class AdminExportPreviewControllerTest extends TestCase
{
    public function testRequestedExportSetIsUsedForRenderedPreviewMetadata(): void
    {
        $entityManager = $this->createEntityManager();
        $managerRegistry = $this->createManagerRegistry($entityManager);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new PreviewControllerEntity('Alice', 'alice@example.com')]);
        PreviewControllerCrudController::$queryBuilder = $queryBuilder;

        $renderedParameters = [];
        $controller = $this->createController($managerRegistry, true, $renderedParameters);

        $request = new Request(['exportSet' => 'gdpr', 'format' => ExportFormat::CSV]);
        $request->attributes->set('_jd_ea_extra_crud', PreviewControllerCrudController::class);
        $request->attributes->set('_jd_ea_extra_dashboard', PreviewControllerDashboardController::class);

        $response = $controller($request);

        self::assertSame('rendered', $response->getContent());
        self::assertSame([ExportFormat::CSV, ExportFormat::XML], $renderedParameters['available_formats']);
        self::assertSame(['Email'], $renderedParameters['preview']->headers);
        self::assertSame([['alice@example.com']], $renderedParameters['preview']->rows);
        self::assertSame('Export CSV', $renderedParameters['preview']->formatLabels[ExportFormat::CSV]);
        self::assertSame('admin_preview_controller_export_preview?exportSet=gdpr&format=csv', $renderedParameters['preview_urls'][ExportFormat::CSV]);
        self::assertSame('admin_preview_controller_export_csv?exportSet=gdpr', $renderedParameters['download_urls'][ExportFormat::CSV]);
    }

    public function testUnauthorizedExportSetDoesNotRenderPreviewMetadata(): void
    {
        $entityManager = $this->createEntityManager();
        $managerRegistry = $this->createManagerRegistry($entityManager);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new PreviewControllerEntity('Alice', 'alice@example.com')], false);
        PreviewControllerCrudController::$queryBuilder = $queryBuilder;

        $renderedParameters = [];
        $controller = $this->createController($managerRegistry, false, $renderedParameters);

        $request = new Request(['exportSet' => 'restricted', 'format' => ExportFormat::CSV]);
        $request->attributes->set('_jd_ea_extra_crud', PreviewControllerCrudController::class);
        $request->attributes->set('_jd_ea_extra_dashboard', PreviewControllerDashboardController::class);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        try {
            $controller($request);
        } finally {
            self::assertSame([], $renderedParameters);
        }
    }

    public function testUnsupportedPreviewFormatThrowsNotFound(): void
    {
        $entityManager = $this->createEntityManager();
        $managerRegistry = $this->createManagerRegistry($entityManager);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new PreviewControllerEntity('Alice', 'alice@example.com')], false);
        PreviewControllerCrudController::$queryBuilder = $queryBuilder;

        $renderedParameters = [];
        $controller = $this->createController($managerRegistry, true, $renderedParameters);

        $request = new Request(['exportSet' => 'gdpr', 'format' => ExportFormat::JSON]);
        $request->attributes->set('_jd_ea_extra_crud', PreviewControllerCrudController::class);
        $request->attributes->set('_jd_ea_extra_dashboard', PreviewControllerDashboardController::class);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('The export format "json" is not enabled for preview.');

        try {
            $controller($request);
        } finally {
            self::assertSame([], $renderedParameters);
        }
    }

    public function testUnauthorizedExportSetWithUnsupportedFormatDoesNotExposeFormatMetadata(): void
    {
        $entityManager = $this->createEntityManager();
        $managerRegistry = $this->createManagerRegistry($entityManager);
        $queryBuilder = $this->createConfiguredQueryBuilder($entityManager, [new PreviewControllerEntity('Alice', 'alice@example.com')], false);
        PreviewControllerCrudController::$queryBuilder = $queryBuilder;

        $renderedParameters = [];
        $controller = $this->createController($managerRegistry, false, $renderedParameters);

        $request = new Request(['exportSet' => 'restricted', 'format' => ExportFormat::JSON]);
        $request->attributes->set('_jd_ea_extra_crud', PreviewControllerCrudController::class);
        $request->attributes->set('_jd_ea_extra_dashboard', PreviewControllerDashboardController::class);

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        try {
            $controller($request);
        } finally {
            self::assertSame([], $renderedParameters);
        }
    }

    /**
     * @param array<string, mixed> $renderedParameters
     */
    private function createController(ManagerRegistry $managerRegistry, bool $isGranted, array &$renderedParameters): AdminExportPreviewController
    {
        $crudController = new PreviewControllerCrudController();
        $dashboardController = new PreviewControllerDashboardController();
        $serviceContainer = $this->createServiceContainer($crudController, $dashboardController);
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($isGranted);
        $eventDispatcher = new EventDispatcher();

        $adminContextFactory = $this->createAdminContextFactory($managerRegistry, $authorizationChecker, $eventDispatcher);
        $operationAdminContextFactory = new OperationAdminContextFactory(
            $adminContextFactory,
            new CrudControllerResolver($serviceContainer),
            new DashboardResolver($serviceContainer),
        );

        $controller = new AdminExportPreviewController(
            new CrudActionNameResolver(),
            $this->createExportManager($managerRegistry, $authorizationChecker, $eventDispatcher, $serviceContainer),
            new ExportRouteMetadataResolver(),
            new OperationRequestMetadataResolver(),
            $operationAdminContextFactory,
            $this->createRouter(),
        );

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $view, array $parameters) use (&$renderedParameters): string {
                $renderedParameters = $parameters;

                return 'rendered';
            });

        $controller->setContainer(new class($twig) implements PsrContainerInterface {
            public function __construct(private readonly Environment $twig) {}

            public function get(string $id): mixed
            {
                if ('twig' === $id) {
                    return $this->twig;
                }

                throw new LogicException('Unknown service: ' . $id);
            }

            public function has(string $id): bool
            {
                return 'twig' === $id;
            }
        });

        return $controller;
    }

    private function createAdminContextFactory(
        ManagerRegistry $managerRegistry,
        AuthorizationCheckerInterface $authorizationChecker,
        EventDispatcherInterface $eventDispatcher,
    ): AdminContextFactory {
        $adminControllers = $this->createMock(AdminControllerRegistryInterface::class);
        $adminControllers->method('findEntityByCrudController')->willReturn(PreviewControllerEntity::class);

        $menuFactory = $this->createMock(MenuFactoryInterface::class);
        $menuFactory->method('createMainMenu')->willReturn(new MainMenuDto([]));

        $adminRouteGenerator = $this->createMock(AdminRouteGeneratorInterface::class);
        $adminRouteGenerator->method('getDashboardRoutes')->willReturn([
            PreviewControllerDashboardController::class => 'admin',
        ]);
        $adminRouteGenerator->method('generateAll')->willReturn(new RouteCollection());

        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAllExcept')->willReturnSelf();
        $adminUrlGenerator->method('setAll')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('#');

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new AdminContextFactory(
            null,
            $menuFactory,
            $adminControllers,
            new EntityFactory($authorizationChecker, $managerRegistry, $eventDispatcher),
            $adminRouteGenerator,
            new ActionFactory(
                $this->createMock(AdminContextProviderInterface::class),
                $authorizationChecker,
                $adminUrlGenerator,
            ),
            null,
            $translator,
        );
    }

    private function createExportManager(
        ManagerRegistry $managerRegistry,
        AuthorizationCheckerInterface $authorizationChecker,
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $serviceContainer,
    ): ExportManager {
        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn(null);

        $securityContainer = new class($tokenStorage) implements PsrContainerInterface {
            public function __construct(private readonly TokenStorageInterface $tokenStorage) {}

            public function get(string $id): mixed
            {
                if ('security.token_storage' === $id) {
                    return $this->tokenStorage;
                }

                throw new LogicException('Unknown service: ' . $id);
            }

            public function has(string $id): bool
            {
                return 'security.token_storage' === $id;
            }
        };

        $operationContextResolver = new OperationContextResolver(
            new CollectionFactoryCompat(),
            new FilterFactory($this->createMock(AdminContextProviderInterface::class), []),
        );

        return new ExportManager(
            new CrudControllerResolver($serviceContainer),
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
                $eventDispatcher,
            ),
            new EntityQueryBuilderFactory(
                $operationContextResolver,
                new EntityMetadataResolver($managerRegistry),
                new EntitySelectionResolver(),
            ),
            new ExportPreviewInspector(),
            new ExporterRegistry([new PreviewControllerExporter(ExportFormat::CSV)]),
            new ExportSetMetadataResolver(),
            new RoleAuthorizationChecker($authorizationChecker),
            $eventDispatcher,
        );
    }

    private function createRouter(): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(static function (string $name, array $parameters = []): string {
                $query = http_build_query($parameters);

                return '' === $query ? $name : $name . '?' . $query;
            });

        return $router;
    }

    private function createEntityManager(): EntityManagerInterface
    {
        $metadata = new ClassMetadata(PreviewControllerEntity::class);
        $metadata->setIdentifier(['id']);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);

        return $entityManager;
    }

    private function createManagerRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($entityManager);

        return $managerRegistry;
    }

    private function createServiceContainer(AbstractCrudController $crudController, AbstractDashboardController $dashboardController): ContainerInterface
    {
        return new class($crudController, $dashboardController) implements ContainerInterface {
            public function __construct(
                private readonly AbstractCrudController $crudController,
                private readonly AbstractDashboardController $dashboardController,
            ) {}

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                return match ($id) {
                    $this->crudController::class => $this->crudController,
                    $this->dashboardController::class => $this->dashboardController,
                    default => throw new LogicException('Unknown service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return \in_array($id, [$this->crudController::class, $this->dashboardController::class], true);
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
    }

    /**
     * @param list<object> $rows
     */
    private function createConfiguredQueryBuilder(EntityManagerInterface $entityManager, array $rows, bool $expectRowsRead = true): QueryBuilder
    {
        $query = $this->createMock(Query::class);
        if ($expectRowsRead) {
            $query->method('toIterable')->willReturn($rows);
        } else {
            $query->expects(self::never())->method('toIterable');
        }

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('resetDQLPart')->willReturnSelf();
        $queryBuilder->method('setFirstResult')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getEntityManager')->willReturn($entityManager);
        $queryBuilder->method('getQuery')->willReturn($query);

        return $queryBuilder;
    }
}

final class PreviewControllerDashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Admin');
    }
}

#[AdminExport(
    filename: 'users-{format}',
    formats: [ExportFormat::CSV, ExportFormat::XML],
    previewEnabled: true,
)]
final class PreviewControllerCrudController extends AbstractCrudController implements ExportFieldsProviderInterface, ExportSetMetadataProviderInterface
{
    public static QueryBuilder $queryBuilder;

    public static function getEntityFqcn(): string
    {
        return PreviewControllerEntity::class;
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return match ($exportSet) {
            'gdpr', 'restricted' => [TextExportField::new('email', 'Email')],
            default => [TextExportField::new('name', 'Name')],
        };
    }

    public static function getExportSetMetadata(): array
    {
        return [
            new ExportSetMetadata('default', 'Default export'),
            new ExportSetMetadata('gdpr', 'GDPR export'),
            new ExportSetMetadata('restricted', 'Restricted export', 'ROLE_ADMIN'),
        ];
    }

    public function createExportAllQueryBuilder(): QueryBuilder
    {
        return self::$queryBuilder;
    }
}

final readonly class PreviewControllerEntity
{
    public function __construct(public string $name, public string $email, public int $id = 1) {}
}

final class PreviewControllerExporter implements ExporterInterface
{
    public function __construct(private readonly string $format) {}

    public function getFormat(): string
    {
        return $this->format;
    }

    public function export(ExportPayload $payload): Response
    {
        return new Response('not used');
    }
}
