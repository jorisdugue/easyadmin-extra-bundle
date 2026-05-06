<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminImportConfirmController;
use JorisDugue\EasyAdminExtraBundle\Dto\TemporaryImportFile;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportFieldHeaderResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvUploadValidator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportEntityHydrator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportManager;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportPersister;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportPreviewValidator;
use JorisDugue\EasyAdminExtraBundle\Service\Import\TemporaryImportStorage;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\Exception\TokenNotFoundException;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface as CsrfTokenStorageInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use UnitEnum;

final class AdminImportConfirmControllerTest extends TestCase
{
    public function testSuccessfulImportDisplaysResultAndPersistsRows(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name,Email\nAlice,alice@example.com\n", ImportConfirmCrudController::class);
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $controller = $this->createController($storage, ImportConfirmCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager);

        $request = $this->createConfirmRequest($token, $csrfTokenManager);
        $response = $controller($request);

        self::assertSame('rendered', $response->getContent());
        self::assertTrue($renderedParameters['import_result']->success);
        self::assertSame(1, $renderedParameters['import_result']->importedCount);
        self::assertSame('admin_import_preview', $renderedParameters['import_preview_route']);
        self::assertFalse($renderedParameters['preview']->hasRows());
        self::assertCount(1, $persistedEntities);
        self::assertSame('Alice', $persistedEntities[0]->name);
        self::assertSame('alice@example.com', $persistedEntities[0]->email);
    }

    public function testFailedImportDisplaysNoImportResultWithRowErrors(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name\nAlice\n", ImportConfirmPrivateCrudController::class);
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $controller = $this->createController($storage, ImportConfirmPrivateCrudController::class, ImportConfirmPrivateEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager);

        $request = $this->createConfirmRequest($token, $csrfTokenManager, ImportConfirmPrivateCrudController::class);
        $response = $controller($request);

        self::assertSame('rendered', $response->getContent());
        self::assertFalse($renderedParameters['import_result']->success);
        self::assertSame(1, $renderedParameters['import_result']->failedCount);
        self::assertSame([], $persistedEntities);
        self::assertSame(['Property "name" is not writable.'], $renderedParameters['import_result']->rowResults[0]->errors);
    }

    public function testMissingImportTokenDisplaysSafeError(): void
    {
        $storage = new TemporaryImportStorage();
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $requestStack = new RequestStack();
        $router = $this->createPreviewRouter();
        $controller = $this->createController($storage, ImportConfirmCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager, $requestStack, $router);

        $request = $this->createConfirmRequest('', $csrfTokenManager, ImportConfirmCrudController::class, $requestStack);
        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/stats/import/preview', $response->headers->get('Location'));
        self::assertSame(['The import confirmation request is not valid or has expired. Please upload the CSV file again.'], $request->getSession()->getFlashBag()->peek('danger'));
        self::assertSame([], $renderedParameters);
        self::assertSame([], $persistedEntities);
    }

    public function testInvalidImportTokenRedirectsToPreviewWithSafeFlash(): void
    {
        $storage = new TemporaryImportStorage();
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $requestStack = new RequestStack();
        $controller = $this->createController($storage, ImportConfirmCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager, $requestStack, $this->createPreviewRouter());

        $request = $this->createConfirmRequest('not-a-real-token', $csrfTokenManager, ImportConfirmCrudController::class, $requestStack);
        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/stats/import/preview', $response->headers->get('Location'));
        self::assertSame(['The import confirmation request is not valid or has expired. Please upload the CSV file again.'], $request->getSession()->getFlashBag()->peek('danger'));
        self::assertSame([], $renderedParameters);
        self::assertSame([], $persistedEntities);
    }

    public function testExpiredImportTokenRedirectsToPreviewWithSafeFlash(): void
    {
        $storage = new TemporaryImportStorage();
        $temporaryFile = $this->storePreviewedCsvFile($storage, "Name,Email\nAlice,alice@example.com\n", ImportConfirmCrudController::class);
        $this->expireTemporaryFile($temporaryFile);
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $requestStack = new RequestStack();
        $controller = $this->createController($storage, ImportConfirmCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager, $requestStack, $this->createPreviewRouter());

        $request = $this->createConfirmRequest($temporaryFile->token, $csrfTokenManager, ImportConfirmCrudController::class, $requestStack);
        $response = $controller($request);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/admin/stats/import/preview', $response->headers->get('Location'));
        self::assertSame(['The import confirmation request is not valid or has expired. Please upload the CSV file again.'], $request->getSession()->getFlashBag()->peek('danger'));
        self::assertSame([], $renderedParameters);
        self::assertSame([], $persistedEntities);
    }

    public function testValidTokenWithCsvValidationErrorsRendersPreviewAndPersistsNothing(): void
    {
        $storage = new TemporaryImportStorage();
        $token = $this->storePreviewedCsv($storage, "Name\nAlice\n", ImportConfirmRequiredCrudController::class);
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $controller = $this->createController($storage, ImportConfirmRequiredCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager);

        $request = $this->createConfirmRequest($token, $csrfTokenManager, ImportConfirmRequiredCrudController::class);
        $response = $controller($request);

        self::assertSame('rendered', $response->getContent());
        self::assertFalse($renderedParameters['import_result']->success);
        self::assertSame(0, $renderedParameters['import_result']->importedCount);
        self::assertNotEmpty($renderedParameters['preview']->issues);
        self::assertSame([], $persistedEntities);
    }

    public function testCsrfFailureRejectsRequest(): void
    {
        $storage = new TemporaryImportStorage();
        $persistedEntities = [];
        $renderedParameters = [];
        $csrfTokenManager = $this->createCsrfTokenManager();
        $controller = $this->createController($storage, ImportConfirmCrudController::class, ImportConfirmEntity::class, $persistedEntities, $renderedParameters, $csrfTokenManager);

        $request = $this->createConfirmRequest('invalid-token', $csrfTokenManager);
        $request->request->set('_token', 'invalid');

        $this->expectException(AccessDeniedException::class);

        $controller($request);
    }

    /**
     * @param list<object>         $persistedEntities
     * @param array<string, mixed> $renderedParameters
     */
    private function createController(
        TemporaryImportStorage $storage,
        string $crudControllerFqcn,
        string $entityFqcn,
        array &$persistedEntities,
        array &$renderedParameters,
        CsrfTokenManager $csrfTokenManager,
        ?RequestStack $requestStack = null,
        ?RouterInterface $router = null,
    ): AdminImportConfirmController {
        $requestStack ??= new RequestStack();
        $router ??= $this->createPreviewRouter();
        $entityManager = $this->createEntityManager($entityFqcn, $persistedEntities);
        $managerRegistry = $this->createManagerRegistry($entityManager);
        $serviceContainer = $this->createServiceContainer(new $crudControllerFqcn(), new ImportConfirmDashboardController(), $crudControllerFqcn);
        $operationAdminContextFactory = new OperationAdminContextFactory(
            $this->createAdminContextFactory($managerRegistry, $crudControllerFqcn, $entityFqcn),
            new CrudControllerResolver($serviceContainer),
            new DashboardResolver($serviceContainer),
        );
        $csvPreviewReader = new CsvPreviewReader(
            new ImportPreviewValidator(new ImportFieldHeaderResolver()),
            new CsvUploadValidator(),
        );

        $controller = new AdminImportConfirmController(
            new ImportManager(
                $storage,
                new ImportConfigFactory(),
                $csvPreviewReader,
                new ImportEntityHydrator(),
                new ImportPersister($managerRegistry),
            ),
            $csvPreviewReader,
            new CrudActionNameResolver(),
            new OperationRequestMetadataResolver(),
            $operationAdminContextFactory,
        );

        $twig = $this->createMock(Environment::class);
        $twig->method('render')
            ->willReturnCallback(static function (string $view, array $parameters) use (&$renderedParameters): string {
                $renderedParameters = $parameters;

                return 'rendered';
            });

        $controller->setContainer(new class($twig, $csrfTokenManager, $requestStack, $router) implements PsrContainerInterface {
            public function __construct(
                private readonly Environment $twig,
                private readonly CsrfTokenManager $csrfTokenManager,
                private readonly RequestStack $requestStack,
                private readonly RouterInterface $router,
            ) {}

            public function get(string $id): mixed
            {
                return match ($id) {
                    'twig' => $this->twig,
                    'security.csrf.token_manager' => $this->csrfTokenManager,
                    'request_stack' => $this->requestStack,
                    'router' => $this->router,
                    default => throw new LogicException('Unknown service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return \in_array($id, ['twig', 'security.csrf.token_manager', 'request_stack', 'router'], true);
            }
        });

        return $controller;
    }

    private function createConfirmRequest(
        string $importToken,
        CsrfTokenManager $csrfTokenManager,
        string $crudControllerFqcn = ImportConfirmCrudController::class,
        ?RequestStack $requestStack = null,
    ): Request {
        $request = new Request([], [
            '_token' => $csrfTokenManager->getToken('jd_import_confirm')->getValue(),
            'import_token' => $importToken,
            'separator' => 'comma',
            'encoding' => 'UTF-8',
            'first_row_contains_headers' => '1',
        ]);
        $request->setMethod('POST');
        $request->attributes->set('_jd_ea_extra_crud', $crudControllerFqcn);
        $request->attributes->set('_jd_ea_extra_dashboard', ImportConfirmDashboardController::class);
        $request->attributes->set('_jd_ea_extra_import_preview_route', 'admin_import_preview');
        $request->attributes->set('_jd_ea_extra_import_confirm_route', 'admin_import_confirm');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack?->push($request);

        return $request;
    }

    private function createPreviewRouter(): RouterInterface
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->with('admin_import_preview')
            ->willReturn('/admin/stats/import/preview');

        return $router;
    }

    private function createCsrfTokenManager(): CsrfTokenManager
    {
        return new CsrfTokenManager(new UriSafeTokenGenerator(), new class implements CsrfTokenStorageInterface {
            /**
             * @var array<string, string>
             */
            private array $tokens = [];

            public function getToken(string $tokenId): string
            {
                if (!$this->hasToken($tokenId)) {
                    throw new TokenNotFoundException('Token not found.');
                }

                return $this->tokens[$tokenId];
            }

            public function setToken(string $tokenId, string $token): void
            {
                $this->tokens[$tokenId] = $token;
            }

            public function removeToken(string $tokenId): ?string
            {
                $token = $this->tokens[$tokenId] ?? null;
                unset($this->tokens[$tokenId]);

                return $token;
            }

            public function hasToken(string $tokenId): bool
            {
                return isset($this->tokens[$tokenId]);
            }
        });
    }

    private function storePreviewedCsv(TemporaryImportStorage $storage, string $contents, string $crudControllerFqcn): string
    {
        return $this->storePreviewedCsvFile($storage, $contents, $crudControllerFqcn)->token;
    }

    private function storePreviewedCsvFile(TemporaryImportStorage $storage, string $contents, string $crudControllerFqcn): TemporaryImportFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jd_confirm_controller_');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return $storage->store(new UploadedFile($path, 'users.csv', 'text/csv', null, true), $crudControllerFqcn, 'comma', 'UTF-8', true);
    }

    private function expireTemporaryFile(TemporaryImportFile $temporaryFile): void
    {
        $metadataPath = preg_replace('/\.csv$/', '.json', $temporaryFile->path);
        self::assertIsString($metadataPath);
        $metadata = json_decode((string) file_get_contents($metadataPath), true);
        self::assertIsArray($metadata);
        $metadata['createdAt'] = time() - 3600;
        file_put_contents($metadataPath, json_encode($metadata, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<object> $persistedEntities
     */
    private function createEntityManager(string $entityFqcn, array &$persistedEntities): EntityManagerInterface
    {
        $metadata = new ClassMetadata($entityFqcn);
        $metadata->setIdentifier(['id']);
        $metadata->mapField(['fieldName' => 'id', 'type' => 'integer']);
        $metadata->setIdGeneratorType(ClassMetadata::GENERATOR_TYPE_AUTO);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getClassMetadata')->willReturn($metadata);
        $entityManager->method('wrapInTransaction')
            ->willReturnCallback(static function (callable $callback) use ($entityManager): mixed {
                return $callback($entityManager);
            });
        $entityManager->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persistedEntities): void {
                $persistedEntities[] = $entity;
            });

        return $entityManager;
    }

    private function createManagerRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry->method('getManagerForClass')->willReturn($entityManager);

        return $managerRegistry;
    }

    private function createAdminContextFactory(ManagerRegistry $managerRegistry, string $crudControllerFqcn, string $entityFqcn): AdminContextFactory
    {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        $adminControllers = $this->createMock(AdminControllerRegistryInterface::class);
        $adminControllers->method('findEntityByCrudController')->with($crudControllerFqcn)->willReturn($entityFqcn);

        $menuFactory = $this->createMock(MenuFactoryInterface::class);
        $menuFactory->method('createMainMenu')->willReturn(new MainMenuDto([]));

        $adminRouteGenerator = $this->createMock(AdminRouteGeneratorInterface::class);
        $adminRouteGenerator->method('getDashboardRoutes')->willReturn([
            ImportConfirmDashboardController::class => 'admin',
        ]);

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
            new EntityFactory($authorizationChecker, $managerRegistry, new EventDispatcher()),
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

    private function createServiceContainer(AbstractCrudController $crudController, AbstractDashboardController $dashboardController, string $crudControllerFqcn): ContainerInterface
    {
        return new class($crudController, $dashboardController, $crudControllerFqcn) implements ContainerInterface {
            public function __construct(
                private readonly AbstractCrudController $crudController,
                private readonly AbstractDashboardController $dashboardController,
                private readonly string $crudControllerFqcn,
            ) {}

            public function get(string $id, int $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE): ?object
            {
                return match ($id) {
                    $this->crudControllerFqcn => $this->crudController,
                    $this->dashboardController::class => $this->dashboardController,
                    default => throw new LogicException('Unknown service: ' . $id),
                };
            }

            public function has(string $id): bool
            {
                return \in_array($id, [$this->crudControllerFqcn, $this->dashboardController::class], true);
            }

            public function initialized(string $id): bool
            {
                return $this->has($id);
            }

            public function set(string $id, ?object $service): void {}

            public function reset(): void {}

            public function getParameter(string $name): array|bool|string|int|float|UnitEnum|null
            {
                throw new LogicException('Not needed in tests.');
            }

            public function hasParameter(string $name): bool
            {
                return false;
            }

            public function setParameter(string $name, array|bool|string|int|float|UnitEnum|null $value): void {}
        };
    }
}

final class ImportConfirmDashboardController extends AbstractDashboardController
{
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('Admin');
    }
}

#[AdminImport]
final class ImportConfirmCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return ImportConfirmEntity::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('name', 'Name'),
            TextImportField::new('email', 'Email'),
        ];
    }
}

#[AdminImport]
final class ImportConfirmPrivateCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return ImportConfirmPrivateEntity::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [TextImportField::new('name', 'Name')];
    }
}

#[AdminImport]
final class ImportConfirmRequiredCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return ImportConfirmEntity::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('name', 'Name'),
            TextImportField::new('email', 'Email')->required(),
        ];
    }
}

final class ImportConfirmEntity
{
    public ?string $name = null;
    public ?string $email = null;
}

final class ImportConfirmPrivateEntity
{
    private ?string $name = null;
}
