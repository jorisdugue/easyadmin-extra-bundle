<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Routing;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportBatchController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminImportPreviewController;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Util\ControllerNaming;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamically registers EasyAdmin extra routes for CRUD controllers marked with bundle operation attributes.
 *
 * @author Joris Dugué
 */
class AdminOperationRouteLoader extends Loader
{
    /**
     * @var string
     */
    public const ROUTE_LOADER_TYPE = 'jorisdugue_easyadmin_extra.routes';
    /**
     * @var list<string>
     */
    private const ALLOWED_FORMATS = ['csv', 'xlsx', 'json', 'xml'];

    private bool $isLoaded = false;

    /**
     * @param list<string> $discoveryPaths
     */
    public function __construct(
        private readonly array $discoveryPaths,
        private readonly ExportConfigFactory $exportConfigFactory,
        private readonly ExportRouteMetadataResolver $exportRouteMetadataResolver,
    ) {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::ROUTE_LOADER_TYPE === $type;
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new RuntimeException('JorisDugue EasyAdmin Extra routes are already loaded.');
        }

        $routes = new RouteCollection();
        $dashboards = $this->discoverDashboards();
        $crudControllers = $this->discoverOperationCrudControllers();
        /**
         * @var array<string, array{dashboard:string,crud:string}> $generatedRouteNames
         */
        $generatedRouteNames = [];
        /**
         * @var array<string, array{dashboard:string,crud:string}> $generatedRoutePaths
         */
        $generatedRoutePaths = [];

        foreach ($dashboards as $dashboard) {
            foreach ($crudControllers as $crud) {
                if (null !== $crud['exportConfig']) {
                    $this->addExportRoutes($routes, $generatedRouteNames, $generatedRoutePaths, $dashboard, $crud);
                }

                if ($crud['importEnabled']) {
                    $this->addImportPreviewRoute($routes, $generatedRouteNames, $generatedRoutePaths, $dashboard, $crud);
                }
            }
        }

        $this->isLoaded = true;

        return $routes;
    }

    /**
     * @param array<string, array{dashboard:string,crud:string}> $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string}> $generatedRoutePaths
     */
    private function guardDuplicateRoute(
        array &$generatedRouteNames,
        array &$generatedRoutePaths,
        string $routeName,
        string $path,
        string $dashboardFqcn,
        string $crudFqcn,
    ): void {
        if (isset($generatedRouteNames[$routeName])) {
            throw new LogicException(\sprintf('Duplicate EasyAdmin Extra route name "%s" detected. First generated for dashboard "%s" and CRUD "%s", then again for dashboard "%s" and CRUD "%s".', $routeName, $generatedRouteNames[$routeName]['dashboard'], $generatedRouteNames[$routeName]['crud'], $dashboardFqcn, $crudFqcn));
        }

        if (isset($generatedRoutePaths[$path])) {
            throw new LogicException(\sprintf('Duplicate EasyAdmin Extra route path "%s" detected. First generated for dashboard "%s" and CRUD "%s", then again for dashboard "%s" and CRUD "%s".', $path, $generatedRoutePaths[$path]['dashboard'], $generatedRoutePaths[$path]['crud'], $dashboardFqcn, $crudFqcn));
        }

        $generatedRouteNames[$routeName] = [
            'dashboard' => $dashboardFqcn,
            'crud' => $crudFqcn,
        ];
        $generatedRoutePaths[$path] = [
            'dashboard' => $dashboardFqcn,
            'crud' => $crudFqcn,
        ];
    }

    /**
     * @param array{fqcn:string,name:string,path:string}                                                                                                $dashboard
     * @param array{fqcn:string,exportName:?string,exportPath:?string,importName:?string,importPath:?string,exportConfig:?ExportConfig,formats:list<string>,previewEnabled:bool,batchExport:bool,importEnabled:bool} $crud
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRoutePaths
     */
    private function addExportRoutes(
        RouteCollection $routes,
        array &$generatedRouteNames,
        array &$generatedRoutePaths,
        array $dashboard,
        array $crud,
    ): void {
        foreach ($crud['formats'] as $format) {
            \assert(null !== $crud['exportPath']);
            \assert(null !== $crud['exportName']);

            $path = $this->joinPaths($dashboard['path'], $crud['exportPath'], '/export/' . $format);
            $routeName = \sprintf('%s_%s_export_%s', $dashboard['name'], $crud['exportName'], $format);
            $this->guardDuplicateRoute($generatedRouteNames, $generatedRoutePaths, $routeName, $path, $dashboard['fqcn'], $crud['fqcn']);
            $routes->add($routeName, new Route(
                $path,
                [
                    '_controller' => 'JorisDugue\\EasyAdminExtraBundle\\Controller\\AdminExportController',
                    '_jd_ea_extra_crud' => $crud['fqcn'],
                    '_jd_ea_extra_dashboard' => $dashboard['fqcn'],
                    '_jd_ea_extra_format' => $format,
                    EA::CRUD_CONTROLLER_FQCN => $crud['fqcn'],
                    EA::DASHBOARD_CONTROLLER_FQCN => $dashboard['fqcn'],
                    EA::CRUD_ACTION => 'index',
                ],
                [],
                [],
                '',
                [],
                ['GET'],
            ));

            if ($crud['batchExport']) {
                $this->addBatchExportRoute($routes, $generatedRouteNames, $generatedRoutePaths, $dashboard, $crud, $format);
            }
        }

        if ($crud['previewEnabled']) {
            $this->addExportPreviewRoute($routes, $generatedRouteNames, $generatedRoutePaths, $dashboard, $crud);
        }
    }

    /**
     * @param array{fqcn:string,name:string,path:string}                                                                                                $dashboard
     * @param array{fqcn:string,exportName:?string,exportPath:?string,importName:?string,importPath:?string,exportConfig:?ExportConfig,formats:list<string>,previewEnabled:bool,batchExport:bool,importEnabled:bool} $crud
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRoutePaths
     */
    private function addBatchExportRoute(
        RouteCollection $routes,
        array &$generatedRouteNames,
        array &$generatedRoutePaths,
        array $dashboard,
        array $crud,
        string $format,
    ): void {
        \assert(null !== $crud['exportPath']);
        \assert(null !== $crud['exportName']);

        $batchPath = $this->joinPaths($dashboard['path'], $crud['exportPath'], '/export/batch/' . $format);
        $batchRouteName = \sprintf('%s_%s_export_batch_%s', $dashboard['name'], $crud['exportName'], $format);

        $this->guardDuplicateRoute(
            $generatedRouteNames,
            $generatedRoutePaths,
            $batchRouteName,
            $batchPath,
            $dashboard['fqcn'],
            $crud['fqcn'],
        );

        $routes->add($batchRouteName, new Route(
            $batchPath,
            [
                '_controller' => AdminExportBatchController::class,
                '_jd_ea_extra_crud' => $crud['fqcn'],
                '_jd_ea_extra_dashboard' => $dashboard['fqcn'],
                '_jd_ea_extra_format' => $format,
                EA::CRUD_CONTROLLER_FQCN => $crud['fqcn'],
                EA::DASHBOARD_CONTROLLER_FQCN => $dashboard['fqcn'],
                EA::CRUD_ACTION => 'index',
            ],
            [],
            [],
            '',
            [],
            ['POST'],
        ));
    }

    /**
     * @param array{fqcn:string,name:string,path:string}                                                                                                $dashboard
     * @param array{fqcn:string,exportName:?string,exportPath:?string,importName:?string,importPath:?string,exportConfig:?ExportConfig,formats:list<string>,previewEnabled:bool,batchExport:bool,importEnabled:bool} $crud
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRoutePaths
     */
    private function addExportPreviewRoute(
        RouteCollection $routes,
        array &$generatedRouteNames,
        array &$generatedRoutePaths,
        array $dashboard,
        array $crud,
    ): void {
        \assert(null !== $crud['exportPath']);
        \assert(null !== $crud['exportName']);

        $previewPath = $this->joinPaths($dashboard['path'], $crud['exportPath'], '/export/preview');
        $previewRouteName = \sprintf('%s_%s_export_preview', $dashboard['name'], $crud['exportName']);
        $this->guardDuplicateRoute($generatedRouteNames, $generatedRoutePaths, $previewRouteName, $previewPath, $dashboard['fqcn'], $crud['fqcn']);
        $routes->add($previewRouteName, new Route(
            $previewPath,
            [
                '_controller' => 'JorisDugue\\EasyAdminExtraBundle\\Controller\\AdminExportPreviewController',
                '_jd_ea_extra_crud' => $crud['fqcn'],
                '_jd_ea_extra_dashboard' => $dashboard['fqcn'],
                EA::CRUD_CONTROLLER_FQCN => $crud['fqcn'],
                EA::DASHBOARD_CONTROLLER_FQCN => $dashboard['fqcn'],
                EA::CRUD_ACTION => 'index',
            ],
            [],
            [],
            '',
            [],
            ['GET'],
        ));
    }

    /**
     * @param array{fqcn:string,name:string,path:string}                                                                                                $dashboard
     * @param array{fqcn:string,exportName:?string,exportPath:?string,importName:?string,importPath:?string,exportConfig:?ExportConfig,formats:list<string>,previewEnabled:bool,batchExport:bool,importEnabled:bool} $crud
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string}>                                                                                        $generatedRoutePaths
     */
    private function addImportPreviewRoute(
        RouteCollection $routes,
        array &$generatedRouteNames,
        array &$generatedRoutePaths,
        array $dashboard,
        array $crud,
    ): void {
        \assert(null !== $crud['importPath']);
        \assert(null !== $crud['importName']);

        $importPreviewPath = $this->joinPaths($dashboard['path'], $crud['importPath'], '/import/preview');
        $importPreviewRouteName = \sprintf('%s_%s_import_preview', $dashboard['name'], $crud['importName']);
        $this->guardDuplicateRoute($generatedRouteNames, $generatedRoutePaths, $importPreviewRouteName, $importPreviewPath, $dashboard['fqcn'], $crud['fqcn']);

        $routes->add($importPreviewRouteName, new Route(
            $importPreviewPath,
            [
                '_controller' => AdminImportPreviewController::class,
                '_jd_ea_extra_crud' => $crud['fqcn'],
                '_jd_ea_extra_dashboard' => $dashboard['fqcn'],
                EA::CRUD_CONTROLLER_FQCN => $crud['fqcn'],
                EA::DASHBOARD_CONTROLLER_FQCN => $dashboard['fqcn'],
                EA::CRUD_ACTION => 'index',
            ],
            [],
            [],
            '',
            [],
            ['GET', 'POST'],
        ));
    }

    /**
     * @return list<array{fqcn:string,name:string,path:string}>
     */
    private function discoverDashboards(): array
    {
        $classes = $this->discoverPhpClasses();
        $dashboards = [];

        foreach ($classes as $class) {
            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AdminDashboard::class);
            if ([] === $attributes) {
                continue;
            }

            /** @var object $attribute */
            $attribute = $attributes[0]->newInstance();
            $routePath = $this->readProperty($attribute, ['routePath', 'path']);
            $routeName = $this->readProperty($attribute, ['routeName', 'name']);

            if (!\is_string($routePath) || !\is_string($routeName) || '' === $routePath || '' === $routeName) {
                continue;
            }

            $dashboards[] = [
                'fqcn' => $class,
                'path' => $routePath,
                'name' => $routeName,
            ];
        }

        return $dashboards;
    }

    /**
     * @return list<array{fqcn:string,exportName:?string,exportPath:?string,importName:?string,importPath:?string,exportConfig:?ExportConfig,formats:list<string>,previewEnabled:bool,batchExport:bool,importEnabled:bool}>
     *
     * @throws InvalidArgumentException when no format is configured or when an unsupported format is found
     * @throws ReflectionException
     */
    private function discoverOperationCrudControllers(): array
    {
        $classes = $this->discoverPhpClasses();
        $controllers = [];

        foreach ($classes as $class) {
            if (!is_subclass_of($class, AbstractCrudController::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $exportAttributes = $reflection->getAttributes(AdminExport::class);
            $importAttributes = $reflection->getAttributes(AdminImport::class);
            $hasExport = [] !== $exportAttributes;
            $hasImport = [] !== $importAttributes;

            if (!$hasExport && !$hasImport) {
                continue;
            }

            $exportConfig = null;
            $formats = [];
            $previewEnabled = false;
            $batchExport = false;
            $exportName = null;
            $exportPath = null;
            $importName = null;
            $importPath = null;

            if ($hasExport) {
                $exportConfig = $this->exportConfigFactory->create($class);
                $exportName = $this->exportRouteMetadataResolver->resolveRouteName($class, $exportConfig);
                $exportPath = $this->exportRouteMetadataResolver->resolveRoutePath($class, $exportConfig);
                $formats = $this->normalizeAndValidateFormats($exportConfig->formats, $class);
                $previewEnabled = $exportConfig->previewEnabled;
                $batchExport = $exportConfig->batchExport;
            }

            if ($hasImport) {
                /** @var AdminImport $importAttribute */
                $importAttribute = $importAttributes[0]->newInstance();
                $importName = $this->resolveImportRouteName($class, $importAttribute);
                $importPath = $this->resolveImportRoutePath($class, $importAttribute);
            }

            $controllers[] = [
                'fqcn' => $class,
                'exportName' => $exportName,
                'exportPath' => $exportPath,
                'importName' => $importName,
                'importPath' => $importPath,
                'exportConfig' => $exportConfig,
                'formats' => $formats,
                'previewEnabled' => $previewEnabled,
                'batchExport' => $batchExport,
                'importEnabled' => $hasImport,
            ];
        }

        return $controllers;
    }

    /**
     * @param class-string $crudControllerFqcn
     */
    private function resolveImportRouteName(string $crudControllerFqcn, AdminImport $attribute): string
    {
        if (null !== $attribute->routeName) {
            $routeName = trim($attribute->routeName);
            if ('' !== $routeName) {
                return $routeName;
            }
        }

        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminRoute::class);

        if ([] !== $attributes) {
            /** @var AdminRoute $adminRoute */
            $adminRoute = $attributes[0]->newInstance();

            if (null !== $adminRoute->name) {
                $routeName = trim($adminRoute->name);
                if ('' !== $routeName) {
                    return $routeName;
                }
            }
        }

        return ControllerNaming::toSnakeCase($reflection->getShortName(), 'CrudController');
    }

    /**
     * @param class-string $crudControllerFqcn
     */
    private function resolveImportRoutePath(string $crudControllerFqcn, AdminImport $attribute): string
    {
        if (null !== $attribute->routePath) {
            $routePath = trim($attribute->routePath);
            if ('' !== $routePath) {
                return '/' . ltrim($routePath, '/');
            }
        }

        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminRoute::class);

        if ([] !== $attributes) {
            /** @var AdminRoute $adminRoute */
            $adminRoute = $attributes[0]->newInstance();

            if (null !== $adminRoute->path) {
                $routePath = trim($adminRoute->path);
                if ('' !== $routePath) {
                    return '/' . ltrim($routePath, '/');
                }
            }
        }

        return '/' . ControllerNaming::toKebabCase($reflection->getShortName(), 'CrudController');
    }

    /**
     * @return list<class-string>
     */
    private function discoverPhpClasses(): array
    {
        $existingDirs = array_values(array_filter(
            $this->discoveryPaths,
            static fn (string $dir): bool => is_dir($dir),
        ));

        if ([] === $existingDirs) {
            return [];
        }

        $finder = new Finder();
        $finder->files()->in($existingDirs)->name('*.php');
        $classes = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();
            $namespace = $this->extractNamespace($contents);
            $className = $this->extractClassName($contents);

            if (null === $namespace || null === $className) {
                continue;
            }

            $fqcn = $namespace . '\\' . $className;
            if (!class_exists($fqcn)) {
                continue;
            }

            $classes[] = $fqcn;
        }

        return array_values(array_unique($classes));
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^(?:final\s+|abstract\s+)?class\s+(\w+)/m', $contents, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @param list<string> $names
     */
    private function readProperty(object $attribute, array $names): mixed
    {
        foreach ($names as $name) {
            if (property_exists($attribute, $name)) {
                return $attribute->{$name};
            }
        }

        return null;
    }

    /**
     * @param list<string> $formats
     *
     * @return list<string>
     */
    private function normalizeAndValidateFormats(array $formats, string $crudFqcn): array
    {
        $normalizedFormats = array_values(array_unique(array_filter(
            array_map(static fn (string $f): string => strtolower(trim($f)), $formats),
            static fn (string $f): bool => '' !== $f,
        )));

        if ([] === $normalizedFormats) {
            throw new InvalidArgumentException(\sprintf('No export formats configured on CRUD "%s". At least one format is required. Allowed formats are: [%s].', $crudFqcn, implode(', ', self::ALLOWED_FORMATS)));
        }

        $invalidFormats = array_values(array_filter(
            $normalizedFormats,
            static fn (string $f): bool => !\in_array($f, self::ALLOWED_FORMATS, true),
        ));

        if ([] !== $invalidFormats) {
            throw new InvalidArgumentException(\sprintf('Invalid export format(s) [%s] configured on CRUD "%s". Allowed formats are: [%s].', implode(', ', $invalidFormats), $crudFqcn, implode(', ', self::ALLOWED_FORMATS)));
        }

        return $normalizedFormats;
    }

    private function joinPaths(string ...$parts): string
    {
        $normalized = [];
        foreach ($parts as $part) {
            if ('' === trim($part)) {
                continue;
            }

            $normalized[] = trim($part, '/');
        }

        return '/' . implode('/', $normalized);
    }
}
