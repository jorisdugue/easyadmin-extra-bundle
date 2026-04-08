<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

final readonly class DashboardResolver
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function resolve(string $dashboardControllerFqcn): AbstractDashboardController
    {
        if (!is_subclass_of($dashboardControllerFqcn, AbstractDashboardController::class)) {
            throw new InvalidArgumentException(\sprintf('The controller "%s" is not a valid EasyAdmin dashboard controller.', $dashboardControllerFqcn));
        }

        $controller = $this->container->get($dashboardControllerFqcn);

        if (!$controller instanceof AbstractDashboardController) {
            throw InvalidExportConfigurationException::invalidDashboardControllerService($dashboardControllerFqcn, AbstractDashboardController::class);
        }

        return $controller;
    }
}
