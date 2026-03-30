<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class DashboardResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(string $dashboardControllerFqcn): AbstractDashboardController
    {
        if (!is_subclass_of($dashboardControllerFqcn, AbstractDashboardController::class)) {
            throw new InvalidArgumentException(\sprintf('Le contrôleur "%s" n\'est pas un Dashboard EasyAdmin valide.', $dashboardControllerFqcn));
        }

        $controller = $this->container->get($dashboardControllerFqcn);

        if (!$controller instanceof AbstractDashboardController) {
            throw new RuntimeException(\sprintf('Le service "%s" n\'est pas une instance de AbstractDashboardController.', $dashboardControllerFqcn));
        }

        return $controller;
    }
}
