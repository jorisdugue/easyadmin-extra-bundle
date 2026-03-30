<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class CrudControllerResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(string $crudControllerFqcn): AbstractCrudController
    {
        if (!is_subclass_of($crudControllerFqcn, AbstractCrudController::class)) {
            throw new InvalidArgumentException(\sprintf('Le contrôleur "%s" n\'est pas un CRUD EasyAdmin valide.', $crudControllerFqcn));
        }

        $controller = $this->container->get($crudControllerFqcn);

        if (!$controller instanceof AbstractCrudController) {
            throw new RuntimeException(\sprintf('Le service "%s" n\'est pas une instance de AbstractCrudController.', $crudControllerFqcn));
        }

        return $controller;
    }
}
