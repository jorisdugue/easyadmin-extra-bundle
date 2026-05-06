<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Util\ControllerNaming;
use ReflectionClass;
use ReflectionException;

final class ImportRouteMetadataResolver
{
    /**
     * @param class-string $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function resolveRouteName(string $crudControllerFqcn, AdminImport $attribute): string
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
}
