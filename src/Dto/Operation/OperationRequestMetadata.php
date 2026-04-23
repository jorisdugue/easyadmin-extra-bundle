<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;

/**
 * Canonical route metadata used by operation controllers.
 */
final readonly class OperationRequestMetadata
{
    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     * @param class-string<DashboardControllerInterface> $dashboardControllerFqcn
     */
    public function __construct(
        public string $crudControllerFqcn,
        public string $dashboardControllerFqcn,
        public ?string $format = null
    ) {}
}
