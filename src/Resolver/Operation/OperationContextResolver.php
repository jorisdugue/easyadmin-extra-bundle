<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use JorisDugue\EasyAdminExtraBundle\Dto\Operation\ResolvedIndexContext;
use JorisDugue\EasyAdminExtraBundle\Exception\MissingExportContextException;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use Symfony\Component\HttpFoundation\Request;

final readonly class OperationContextResolver
{
    public function __construct(
        private CollectionFactoryCompat $collectionFactoryCompat,
        private FilterFactory $filterFactory,
    ) {}

    /**
     * Resolves the EasyAdmin context required to rebuild an index QueryBuilder.
     *
     * @param AbstractCrudController<object> $crudController
     */
    public function resolveIndexContext(
        AbstractCrudController $crudController,
        Request $request,
    ): ResolvedIndexContext {
        /** @var AdminContext<object>|null $context */
        $context = $request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (null === $context) {
            throw MissingExportContextException::missingRequest();
        }

        $search = $context->getSearch();
        if (!$search instanceof SearchDto) {
            throw MissingExportContextException::missingSearchDto();
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            throw MissingExportContextException::missingCrudContext();
        }

        $fields = $this->collectionFactoryCompat->createFieldCollection(
            $crudController->configureFields(Crud::PAGE_INDEX),
        );

        $filters = $this->filterFactory->create(
            $crud->getFiltersConfig(),
            $fields,
            $context->getEntity(),
        );

        return new ResolvedIndexContext(
            context: $context,
            search: $search,
            fields: $fields,
            filters: $filters,
        );
    }

    public function createEmptySearchDto(SearchDto $searchDto, Request $request): SearchDto
    {
        return new SearchDto(
            $request,
            $searchDto->getSearchableProperties(),
            null,
            [],
            [],
            [],
            $searchDto->getSearchMode(),
        );
    }
}
