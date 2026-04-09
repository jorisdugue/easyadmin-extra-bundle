<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory\Export;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

final readonly class ExportContextFactory
{
    public function __construct(
        private Security $security,
        private OperationScopeResolver $operationScopeResolver,
        private EntityMetadataResolver $entityMetadataResolver,
        private OperationContextFactory $operationContextFactory,
    ) {}

    /**
     * @param AbstractCrudController<object> $crudController
     *
     * @throws ReflectionException
     */
    public function create(
        AbstractCrudController $crudController,
        Request $request,
        ExportConfig $config,
        string $format,
        ?string $scope = null,
    ): ExportContext {
        $user = $this->security->getUser();
        $resolvedScope = $scope ?? $this->operationScopeResolver->resolveForExport($request, $config);

        $operationContext = $this->operationContextFactory->create(
            scope: $resolvedScope,
            entityName: $this->entityMetadataResolver->guessEntityName($crudController),
            user: $user,
            roles: $this->operationContextFactory->resolveUserRoles($user),
        );

        return new ExportContext(
            format: $format,
            scope: $operationContext->scope,
            generatedAt: $operationContext->generatedAt,
            user: $operationContext->user,
            entityName: $operationContext->entityName,
            roles: $operationContext->roles,
        );
    }
}
