<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\DashboardContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\DashboardDto;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\EasyAdmin\ImportActionExtension;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportRouteMetadataResolver;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

final class ImportActionExtensionTest extends TestCase
{
    public function testSupportsImportCrudOnIndexPage(): void
    {
        $extension = $this->createExtension();

        self::assertTrue($extension->supports($this->createAdminContext(ImportActionCrudController::class)));
    }

    public function testDoesNotSupportNonIndexPage(): void
    {
        $extension = $this->createExtension();

        self::assertFalse($extension->supports($this->createAdminContext(ImportActionCrudController::class, Crud::PAGE_DETAIL)));
    }

    public function testDoesNotSupportCrudWithoutImportAttribute(): void
    {
        $extension = $this->createExtension();

        self::assertFalse($extension->supports($this->createAdminContext(PlainImportActionCrudController::class)));
    }

    public function testExtendAddsGlobalImportActionUsingAdminImportRouteName(): void
    {
        $extension = $this->createExtension();
        $actions = Actions::new();

        $extension->extend($actions, $this->createAdminContext(ImportActionCrudController::class));

        $action = $actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, 'jdImportPreview');

        self::assertNotNull($action);
        self::assertSame(Action::TYPE_GLOBAL, $action->getType());
        self::assertSame('Import', $action->getLabel());
        self::assertSame('fa fa-upload', $action->getIcon());
        self::assertIsCallable($action->getUrl());
        self::assertSame('admin_custom_product_import_preview', ($action->getUrl())());
    }

    public function testExtendFallsBackToEasyAdminRouteName(): void
    {
        $extension = $this->createExtension();
        $actions = Actions::new();

        $extension->extend($actions, $this->createAdminContext(AdminRouteImportActionCrudController::class));

        $action = $actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, 'jdImportPreview');

        self::assertNotNull($action);
        self::assertIsCallable($action->getUrl());
        self::assertSame('admin_easy_admin_product_import_preview', ($action->getUrl())());
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    private function createAdminContext(string $crudControllerFqcn, string $pageName = Crud::PAGE_INDEX): AdminContext
    {
        $crudDto = new CrudDto();
        $crudDto->setPageName($pageName);
        $crudDto->setControllerFqcn($crudControllerFqcn);

        $dashboardDto = new DashboardDto();
        $dashboardDto->setRouteName('admin');

        return AdminContext::forTesting(
            RequestContext::forTesting(new Request()),
            CrudContext::forTesting($crudDto),
            DashboardContext::forTesting($dashboardDto),
        );
    }

    private function createExtension(): ImportActionExtension
    {
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')
            ->willReturnCallback(static fn (string $routeName): string => $routeName);

        return new ImportActionExtension(
            $router,
            new ImportRouteMetadataResolver(),
        );
    }
}

#[AdminImport(routeName: 'custom_product')]
final class ImportActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }
}

final class PlainImportActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }
}

#[AdminImport]
#[AdminRoute(path: '/easy-admin-product', name: 'easy_admin_product')]
final class AdminRouteImportActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }
}
