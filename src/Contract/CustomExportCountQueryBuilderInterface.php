<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use Doctrine\ORM\QueryBuilder;

/**
 * Optional contract for CRUD controllers that need a custom row-count query
 * before export.
 *
 * This is useful when the default counting strategy based on cloning the export
 * QueryBuilder is not reliable enough, for example with complex joins, GROUP BY,
 * or HAVING clauses.
 */
interface CustomExportCountQueryBuilderInterface
{
    /**
     * Must return a Doctrine QueryBuilder whose query resolves to a single scalar count.
     */
    public function createExportCountQueryBuilder(): QueryBuilder;
}
