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

    /**
     * Resolves a CRUD controller from its FQCN using the Symfony container.
     *
     * @return AbstractCrudController<object>
     */
    public function resolve(string $crudControllerFqcn): AbstractCrudController
    {
        if (!is_subclass_of($crudControllerFqcn, AbstractCrudController::class)) {
            throw new InvalidArgumentException(\sprintf('The controller "%s" is not a valid EasyAdmin CRUD controller.', $crudControllerFqcn));
        }

        $controller = $this->container->get($crudControllerFqcn);

        if (!$controller instanceof AbstractCrudController) {
            throw new RuntimeException(\sprintf('The service "%s" is not an instance of AbstractCrudController.', $crudControllerFqcn));
        }

        return $controller;
    }
}
