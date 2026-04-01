<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Routing;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use LogicException;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Dynamically registers export routes for EasyAdmin CRUD controllers marked with #[AdminExport].
 *
 * Discovery strategy:
 * - scans the application's src/Controller directory
 * - discovers dashboards using #[AdminDashboard]
 * - discovers CRUD controllers extending AbstractCrudController and marked with #[AdminExport]
 * - reuses #[AdminRoute] path/name metadata when available
 * - otherwise infers route path/name from the CRUD controller class name
 *
 * Route generation strategy:
 * - generates one GET route per dashboard × CRUD × export format
 * - generated routes point to the bundle's AdminExportController
 *
 * Safety checks:
 * - throws if routes are loaded more than once
 * - throws on duplicate generated route names
 * - throws on duplicate generated route paths
 * - throws on unsupported or empty export formats
 *
 * @author Joris Dugué
 */
final class AdminExportRouteLoader extends Loader
{
    public const string ROUTE_LOADER_TYPE = 'jorisdugue_easyadmin_extra.routes';

    private const array ALLOWED_FORMATS = ['csv', 'xlsx', 'json'];

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

    /**
     * @param array<string, array{dashboard:string,crud:string} $generatedRouteNames
     * @param array<string, array{dashboard:string,crud:string} $generatedRoutePaths
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
            throw new LogicException(\sprintf('Duplicate export route name "%s" detected. First generated for dashboard "%s" and CRUD "%s", then again for dashboard "%s" and CRUD "%s".', $routeName, $generatedRouteNames[$routeName]['dashboard'], $generatedRouteNames[$routeName]['crud'], $dashboardFqcn, $crudFqcn));
        }

        if (isset($generatedRoutePaths[$path])) {
            throw new LogicException(\sprintf('Duplicate export route path "%s" detected. First generated for dashboard "%s" and CRUD "%s", then again for dashboard "%s" and CRUD "%s".', $path, $generatedRoutePaths[$path]['dashboard'], $generatedRoutePaths[$path]['crud'], $dashboardFqcn, $crudFqcn));
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

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->isLoaded) {
            throw new RuntimeException('JorisDugue EasyAdmin Extra routes are already loaded.');
        }

        $routes = new RouteCollection();
        $dashboards = $this->discoverDashboards();
        $crudControllers = $this->discoverExportableCrudControllers();
        /**
         * @var array<string, array{dashboard:string,crud:string} $generatedRouteNames
         */
        $generatedRouteNames = [];
        /**
         * @var array<string, array{dashboard:string,crud:string} $generatedRoutePaths
         */
        $generatedRoutePaths = [];

        foreach ($dashboards as $dashboard) {
            foreach ($crudControllers as $crud) {
                foreach ($crud['formats'] as $format) {
                    $path = $this->joinPaths($dashboard['path'], $crud['path'], '/export/' . $format);
                    $routeName = \sprintf('%s_%s_export_%s', $dashboard['name'], $crud['name'], $format);
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
                        ['GET']
                    ));
                }

                if (!$crud['previewEnabled']) {
                    continue;
                }

                $previewPath = $this->joinPaths($dashboard['path'], $crud['path'], '/export/preview');
                $previewRouteName = \sprintf('%s_%s_export_preview', $dashboard['name'], $crud['name']);
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
        }

        $this->isLoaded = true;

        return $routes;
    }

    /**
     * Discovers EasyAdmin dashboards declared in src/Controller using #[AdminDashboard].
     *
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
     * Discovers exportable EasyAdmin CRUD controllers declared in src/Controller using #[AdminExport].
     *
     * Route metadata priority:
     * - AdminExport(routeName, routePath)
     * - AdminRoute(name, path)
     * - inferred from the CRUD controller class name
     *
     * @return list<array{fqcn:string,name:string,path:string,formats:list<string>,previewEnabled:bool}>
     *
     * @throws InvalidArgumentException when no format is configured or when an unsupported format is found
     * @throws ReflectionException
     */
    private function discoverExportableCrudControllers(): array
    {
        $classes = $this->discoverPhpClasses();
        $controllers = [];

        foreach ($classes as $class) {
            if (!is_subclass_of($class, AbstractCrudController::class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            $attributes = $reflection->getAttributes(AdminExport::class);
            if ([] === $attributes) {
                continue;
            }
            $config = $this->exportConfigFactory->create($class);

            $controllers[] = [
                'fqcn' => $class,
                'path' => $this->exportRouteMetadataResolver->resolveRoutePath($class, $config),
                'name' => $this->exportRouteMetadataResolver->resolveRouteName($class, $config),
                'formats' => $this->normalizeAndValidateFormats($config->formats, $class),
                'previewEnabled' => $config->previewEnabled,
            ];
        }

        return $controllers;
    }

    /**
     * Scans src/Controller and attempts to resolve PHP classes declared in matching files.
     *
     * @return list<class-string>
     */
    private function discoverPhpClasses(): array
    {
        $existingDirs = array_values(array_filter(
            $this->discoveryPaths,
            static fn (string $dir): bool => is_dir($dir)
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
     * Reads the first existing public property value from an attribute instance.
     *
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
            static fn (string $f): bool => '' !== $f
        )));

        if ([] === $normalizedFormats) {
            throw new InvalidArgumentException(\sprintf('No export formats configured on CRUD "%s". At least one format is required. Allowed formats are: [%s].', $crudFqcn, implode(', ', self::ALLOWED_FORMATS)));
        }

        $invalidFormats = array_values(array_filter(
            $normalizedFormats,
            static fn (string $f): bool => !\in_array($f, self::ALLOWED_FORMATS, true)
        ));

        if ([] !== $invalidFormats) {
            throw new InvalidArgumentException(\sprintf('Invalid export format(s) [%s] configured on CRUD "%s". Allowed formats are: [%s].', implode(', ', $invalidFormats), $crudFqcn, implode(', ', self::ALLOWED_FORMATS)));
        }

        return $normalizedFormats;
    }

    /**
     * Joins path fragments into a normalized absolute route path.
     *
     * Empty fragments are ignored and leading/trailing slashes are normalized.
     */
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
