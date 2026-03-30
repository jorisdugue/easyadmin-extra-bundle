<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Util\ControllerNaming;
use ReflectionClass;
use ReflectionException;

final class ExportRouteMetadataResolver
{
    /**
     * @param class-string $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function resolveRouteName(string $crudControllerFqcn, ExportConfig $config): string
    {
        if (null !== $config->routeName) {
            $routeName = trim($config->routeName);
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
     *
     * @throws ReflectionException
     */
    public function resolveRoutePath(string $crudControllerFqcn, ExportConfig $config): string
    {
        if (null !== $config->routePath) {
            $routePath = trim($config->routePath);
            if ('' !== $routePath) {
                return '/' . ltrim($routePath, '/');
            }
        }

        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminRoute::class);

        if ([] !== $attributes) {
            /** @var AdminRoute $adminRoute */
            $adminRoute = $attributes[0]->newInstance();
            if (null !== $adminRoute->name) {
                $routePath = trim($adminRoute->name);
                if ('' !== $routePath) {
                    return '/' . ltrim(trim($routePath), '/');
                }
            }
        }

        return '/' . ControllerNaming::toKebabCase($reflection->getShortName(), 'CrudController');
    }
}
