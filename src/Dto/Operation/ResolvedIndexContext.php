<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;

/**
 * Carries the EasyAdmin index context required to rebuild an index QueryBuilder
 */
final readonly class ResolvedIndexContext
{
    /**
     * @param AdminContext<object> $context
     */
    public function __construct(
        public AdminContext $context,
        public SearchDto $search,
        public FieldCollection $fields,
        public FilterCollection $filters
    ) {}
}
