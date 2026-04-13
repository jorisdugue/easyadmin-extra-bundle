<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Routing;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Routing\AdminExportRouteLoader;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AdminExportRouteLoaderTest extends TestCase
{
    public function testSupportsRecognizesTheBundleRouteLoaderType(): void
    {
        $loader = new AdminExportRouteLoader(
            [__DIR__],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        self::assertTrue($loader->supports(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE));
        self::assertFalse($loader->supports(null, 'something_else'));
    }

    public function testLoadBuildsOneRoutePerDashboardCrudAndFormat(): void
    {
        [$projectDir, $namespace] = $this->createProjectWithControllers('load_success');

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(2, $routes);
        self::assertNotNull($routes->get('admin_product_export_csv'));
        self::assertNotNull($routes->get('admin_product_export_json'));
        self::assertSame('/admin/product/export/csv', $routes->get('admin_product_export_csv')?->getPath());
    }

    public function testLoadThrowsWhenRoutesAreLoadedTwice(): void
    {
        [$projectDir, $namespace] = $this->createProjectWithControllers('double_load');

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JorisDugue EasyAdmin Extra routes are already loaded.');

        $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);
    }

    public function testLoadRejectsUnsupportedFormats(): void
    {
        [$projectDir, $namespace] = $this->createProjectWithControllers('invalid_format', ['coffee']);

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid export format(s) [coffee]');

        $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);
    }

    /**
     * @param list<string> $formats
     *
     * @return array{0: string, 1: string}
     */
    private function createProjectWithControllers(
        string $name,
        array $formats = ['csv', 'json'],
        bool $batchExport = false
    ): array {
        $projectDir = sys_get_temp_dir() . '/jd_ea_extra_' . $name . '_' . uniqid('', true);
        $controllerDir = $projectDir . '/src/Controller';

        @mkdir($controllerDir, 0o777, true);

        $namespace = 'JorisDugue\\EasyAdminExtraBundle\\Tests\\Routing\\Fixtures\\' . str_replace('.', '_', uniqid('Scenario', true));

        file_put_contents($controllerDir . '/AdminDashboardController.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
            use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

            #[AdminDashboard(routePath: '/admin', routeName: 'admin')]
            final class AdminDashboardController extends AbstractDashboardController
            {
            }
            PHP);

        $formatsCode = var_export($formats, true);
        $batchExportCode = $batchExport ? 'true' : 'false';

        file_put_contents($controllerDir . '/ProductCrudController.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
            use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
            use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
            use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

            #[AdminExport(formats: {$formatsCode}, batchExport: {$batchExportCode})]
            final class ProductCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
            {
                public static function getEntityFqcn(): string
                {
                    return ProductEntity::class;
                }

                public static function getExportFields(?string \$exportSet = null): array
                {
                    return [TextExportField::new('name', 'Name')];
                }
            }

            final class ProductEntity
            {
            }
            PHP);

        return [$projectDir, $namespace];
    }

    public function testLoadBuildsRoutesFromCustomDiscoveryDirectory(): void
    {
        $projectDir = sys_get_temp_dir() . '/jd_ea_extra_custom_dir_' . uniqid('', true);
        $controllerDir = $projectDir . '/src/Admin';

        @mkdir($controllerDir, 0o777, true);

        $namespace = 'JorisDugue\\EasyAdminExtraBundle\\Tests\\Routing\\Fixtures\\' . str_replace('.', '_', uniqid('Scenario', true));

        file_put_contents($controllerDir . '/AdminDashboardController.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
            use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;

            #[AdminDashboard(routePath: '/admin', routeName: 'admin')]
            final class AdminDashboardController extends AbstractDashboardController
            {
            }
            PHP);

        file_put_contents($controllerDir . '/ProductCrudController.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace {$namespace};

            use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
            use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
            use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
            use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

            #[AdminExport(formats: ['csv'], batchExport: false)]
            final class ProductCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
            {
                public static function getEntityFqcn(): string
                {
                    return ProductEntity::class;
                }

                public static function getExportFields(?string \$exportSet = null): array
                {
                    return [TextExportField::new('name', 'Name')];
                }
            }

            final class ProductEntity
            {
            }
            PHP);

        require_once $controllerDir . '/AdminDashboardController.php';
        require_once $controllerDir . '/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$controllerDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(1, $routes);
        self::assertNotNull($routes->get('admin_product_export_csv'));
        self::assertSame('/admin/product/export/csv', $routes->get('admin_product_export_csv')?->getPath());
    }

    public function testLoadIgnoresNonExistingDiscoveryPaths(): void
    {
        [$projectDir] = $this->createProjectWithControllers('ignore_missing_path');

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [
                $projectDir . '/does-not-exist',
                $projectDir,
            ],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(2, $routes);
        self::assertNotNull($routes->get('admin_product_export_csv'));
        self::assertNotNull($routes->get('admin_product_export_json'));
    }

    public function testLoadReturnsEmptyCollectionWhenNoDiscoveryPathExists(): void
    {
        $loader = new AdminExportRouteLoader(
            [sys_get_temp_dir() . '/jd_ea_extra_missing_' . uniqid('', true)],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(0, $routes);
    }

    public function testLoadSupportsMultipleDiscoveryPaths(): void
    {
        [$projectDir] = $this->createProjectWithControllers('multiple_paths');

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $emptyDir = $projectDir . '/src/Other';
        @mkdir($emptyDir, 0o777, true);

        $loader = new AdminExportRouteLoader(
            [$emptyDir, $projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(2, $routes);
        self::assertNotNull($routes->get('admin_product_export_csv'));
    }

    public function testLoadBuildsBatchRoutesWhenBatchExportIsEnabled(): void
    {
        [$projectDir] = $this->createProjectWithControllers('batch_enabled', ['csv', 'json'], true);

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(4, $routes);

        self::assertNotNull($routes->get('admin_product_export_csv'));
        self::assertNotNull($routes->get('admin_product_export_json'));
        self::assertNotNull($routes->get('admin_product_export_batch_csv'));
        self::assertNotNull($routes->get('admin_product_export_batch_json'));
    }

    public function testLoadBuildsBatchRoutePathsWhenBatchExportIsEnabled(): void
    {
        [$projectDir] = $this->createProjectWithControllers('batch_paths', ['csv'], true);

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(2, $routes);
        self::assertSame('/admin/product/export/csv', $routes->get('admin_product_export_csv')?->getPath());
        self::assertSame('/admin/product/export/batch/csv', $routes->get('admin_product_export_batch_csv')?->getPath());
    }

    public function testBatchRoutesOnlyAllowPostMethod(): void
    {
        [$projectDir] = $this->createProjectWithControllers('batch_methods', ['csv'], true);

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        $route = $routes->get('admin_product_export_batch_csv');

        self::assertNotNull($route);
        self::assertSame(['POST'], $route->getMethods());
    }

    public function testLoadDoesNotBuildBatchRoutesWhenBatchExportIsDisabled(): void
    {
        [$projectDir] = $this->createProjectWithControllers('batch_disabled', ['csv', 'json'], false);

        require_once $projectDir . '/src/Controller/AdminDashboardController.php';
        require_once $projectDir . '/src/Controller/ProductCrudController.php';

        $loader = new AdminExportRouteLoader(
            [$projectDir],
            new ExportConfigFactory(),
            new ExportRouteMetadataResolver(),
        );

        $routes = $loader->load(null, AdminExportRouteLoader::ROUTE_LOADER_TYPE);

        self::assertCount(2, $routes);
        self::assertNotNull($routes->get('admin_product_export_csv'));
        self::assertNotNull($routes->get('admin_product_export_json'));
        self::assertNull($routes->get('admin_product_export_batch_csv'));
        self::assertNull($routes->get('admin_product_export_batch_json'));
    }
}
