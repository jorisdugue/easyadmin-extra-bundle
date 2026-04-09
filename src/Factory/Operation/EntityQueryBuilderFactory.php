<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory\Operation;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use Symfony\Component\HttpFoundation\Request;

final readonly class EntityQueryBuilderFactory
{
    public function __construct(
        private OperationContextResolver $operationContextResolver,
        private EntityMetadataResolver $entityMetadataResolver,
        private EntitySelectionResolver $entitySelectionResolver,
    ) {}

    /**
     * @param AbstractCrudController<object> $crudController
     * @param list<int|string>               $selectedIds
     */
    public function createQueryBuilderForScope(
        AbstractCrudController $crudController,
        Request $request,
        string $scope,
        ExportConfig $config,
        array $selectedIds = [],
    ): QueryBuilder {
        return match ($scope) {
            OperationScopeResolver::SCOPE_CONTEXT => $this->createForContext($crudController, $request),
            OperationScopeResolver::SCOPE_SELECTION => $this->createForSelection($crudController, $request, $selectedIds),
            default => $this->createForAll($crudController, $request),
        };
    }

    /**
     * @param AbstractCrudController<object> $crudController
     */
    public function createForAll(
        AbstractCrudController $crudController,
        Request $request
    ): QueryBuilder {
        if (method_exists($crudController, 'createExportAllQueryBuilder')) {
            $queryBuilder = $crudController->createExportAllQueryBuilder();

            if (!$queryBuilder instanceof QueryBuilder) {
                throw InvalidExportConfigurationException::invalidExportAllQueryBuilderReturnType($crudController::class, QueryBuilder::class, get_debug_type($queryBuilder));
            }

            $queryBuilder->resetDQLPart('orderBy');

            return $this->stripPagination($queryBuilder);
        }

        $data = $this->operationContextResolver->resolveIndexContext($crudController, $request);

        $queryBuilder = $crudController->createIndexQueryBuilder(
            $this->operationContextResolver->createEmptySearchDto($data->search, $request),
            $data->context->getEntity(),
            $data->fields,
            $data->filters,
        );

        $queryBuilder->resetDQLPart('orderBy');

        return $this->stripPagination($queryBuilder);
    }

    /**
     * @param AbstractCrudController<object> $crudController
     */
    public function createForContext(AbstractCrudController $crudController, Request $request): QueryBuilder
    {
        $data = $this->operationContextResolver->resolveIndexContext($crudController, $request);

        $queryBuilder = $crudController->createIndexQueryBuilder(
            $data->search,
            $data->context->getEntity(),
            $data->fields,
            $data->filters,
        );

        return $this->stripPagination($queryBuilder);
    }

    /**
     * @param AbstractCrudController<object> $crudController
     * @param list<int|string>               $selectedIds
     */
    public function createForSelection(
        AbstractCrudController $crudController,
        Request $request,
        array $selectedIds,
    ): QueryBuilder {
        $selection = $this->entitySelectionResolver->resolve($selectedIds);
        $data = $this->operationContextResolver->resolveIndexContext($crudController, $request);

        $queryBuilder = $crudController->createIndexQueryBuilder(
            $data->search,
            $data->context->getEntity(),
            $data->fields,
            $data->filters,
        );

        $queryBuilder = $this->stripPagination($queryBuilder);

        $rootAliases = $queryBuilder->getRootAliases();
        $rootAlias = $rootAliases[0] ?? null;

        if (null === $rootAlias || '' === trim($rootAlias)) {
            throw InvalidBatchExportException::missingRootAlias();
        }

        /** @var class-string<object> $entityFqcn */
        $entityFqcn = $crudController::getEntityFqcn();
        $identifierField = $this->entityMetadataResolver->getSingleIdentifierField($entityFqcn);
        $identifierType = $this->entityMetadataResolver->getIdentifierType($entityFqcn, $identifierField);

        $queryBuilder
            ->andWhere(\sprintf('%s.%s IN (:selectedIds)', $rootAlias, $identifierField))
            ->setParameter('selectedIds', $this->entitySelectionResolver->castIds($selection->ids, $identifierType));

        return $queryBuilder;
    }

    private function stripPagination(QueryBuilder $queryBuilder): QueryBuilder
    {
        $queryBuilder->setFirstResult(null);
        $queryBuilder->setMaxResults(null);

        return $queryBuilder;
    }
}
