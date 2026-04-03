<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use Doctrine\ORM\QueryBuilder;
use JorisDugue\EasyAdminExtraBundle\Exception\UncountableExportQueryException;

/**
 * Resolves the total number of rows that would be exported for a given query.
 */
interface ExportCountResolverInterface
{
    /**
     * @param QueryBuilder $queryBuilder the query builder used for the export
     * @param object $crudController the current CRUD controller instance
     *
     * @throws UncountableExportQueryException when the resolver cannot compute a
     *                                         reliable count using the default strategy
     */
    public function count(QueryBuilder $queryBuilder, object $crudController): int;
}
