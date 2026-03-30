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
     * @throws ReflectionException
     */
    public function resolveRouteName(string $crudControllerFqcn, ExportConfig $config): string
    {
        if (null !== $config->routeName && '' !== trim($config->routeName)) {
            return trim($config->routeName);
        }

        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminRoute::class);

        if ([] !== $attributes) {
            /** @var AdminRoute $adminRoute */
            $adminRoute = $attributes[0]->newInstance();

            if ('' !== trim($adminRoute->name)) {
                return trim($adminRoute->name);
            }
        }

        return ControllerNaming::toSnakeCase($reflection->getShortName(), 'CrudController');
    }

    /**
     * @throws ReflectionException
     */
    public function resolveRoutePath(string $crudControllerFqcn, ExportConfig $config): string
    {
        if (null !== $config->routePath && '' !== trim($config->routePath)) {
            return '/' . ltrim(trim($config->routePath), '/');
        }

        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminRoute::class);

        if ([] !== $attributes) {
            /** @var AdminRoute $adminRoute */
            $adminRoute = $attributes[0]->newInstance();

            if ('' !== trim($adminRoute->path)) {
                return '/' . ltrim(trim($adminRoute->path), '/');
            }
        }

        return '/' . ControllerNaming::toKebabCase($reflection->getShortName(), 'CrudController');
    }
}
