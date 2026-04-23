<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Dto\Operation\OperationRequestMetadata;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class OperationAdminContextFactory
{
    public function __construct(
        private AdminContextFactory $adminContextFactory,
        private CrudControllerResolver $crudControllerResolver,
        private DashboardResolver $dashboardResolver,
    ) {}

    /**
     * Builds and attaches the EasyAdmin context required by operation services.
     *
     * @return AdminContext<object>
     */
    public function createForRequest(Request $request, OperationRequestMetadata $metadata, ?string $crudAction): AdminContext
    {
        $context = $this->adminContextFactory->create(
            $request,
            $this->dashboardResolver->resolve($metadata->dashboardControllerFqcn),
            $this->crudControllerResolver->resolve($metadata->crudControllerFqcn),
            $crudAction,
        );

        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return $context;
    }
}
