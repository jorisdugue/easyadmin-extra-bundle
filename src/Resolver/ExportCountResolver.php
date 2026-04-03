<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use Doctrine\ORM\QueryBuilder;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportCountQueryBuilderInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Exception\UncountableExportQueryException;

/**
 * Resolves the total number of rows that would be exported for a given query.
 *
 * Resolution strategy:
 * 1. If the CRUD controller provides a custom count query builder via
 *    CustomExportCountQueryBuilderInterface, it is always used.
 * 2. Otherwise, the resolver computes a default count based on the export query:
 *    - grouped queries are rejected because the default strategy cannot guarantee
 *      a reliable row count;
 *    - only a single root entity with a single-field scalar identifier is supported;
 *    - the count is performed using COUNT(DISTINCT rootAlias.identifier).
 *
 * This resolver is intentionally strict because the resolved count is used to
 * enforce export limits such as maxRows. Returning an unreliable count could
 * silently allow oversized exports or incorrectly block valid ones.
 */
final class ExportCountResolver implements ExportCountResolverInterface
{
    /**
     * Counts the number of rows that would be exported.
     *
     * If the CRUD controller provides a custom count query builder, that custom
     * strategy is used. Otherwise, a strict default strategy is applied.
     *
     * @param QueryBuilder $queryBuilder the query builder used for the export
     * @param object $crudController the current CRUD controller instance
     *
     * @throws UncountableExportQueryException when the default count strategy
     *                                         cannot guarantee a reliable result
     */
    public function count(QueryBuilder $queryBuilder, object $crudController): int
    {
        if ($crudController instanceof CustomExportCountQueryBuilderInterface) {
            return $this->countWithCustomQueryBuilder($crudController);
        }

        return $this->countWithDefaultStrategy($queryBuilder);
    }

    /**
     * Counts rows using the custom query builder provided by the CRUD controller.
     *
     * This path is intended for advanced or complex queries where the default
     * counting strategy is not reliable enough.
     */
    private function countWithCustomQueryBuilder(
        CustomExportCountQueryBuilderInterface $crudController,
    ): int {
        $countQb = $crudController->createExportCountQueryBuilder();

        return (int) $countQb->getQuery()->getSingleScalarResult();
    }

    /**
     * Counts rows using the default built-in strategy.
     *
     * The default strategy is intentionally conservative:
     * - grouped queries are rejected;
     * - only a single root entity is supported;
     * - only a single-field, non-association identifier is supported;
     * - the final count uses COUNT(DISTINCT rootAlias.identifier).
     *
     * @throws UncountableExportQueryException when the export query is too complex
     *                                         for the default strategy
     */
    private function countWithDefaultStrategy(QueryBuilder $queryBuilder): int
    {
        if ($this->hasGroupBy($queryBuilder)) {
            throw new UncountableExportQueryException('Unable to compute a reliable export row count for grouped queries. Implement CustomExportCountQueryBuilderInterface on the CRUD controller.');
        }

        $identifierExpression = $this->getSingleRootIdentifierExpression($queryBuilder);

        if (null === $identifierExpression) {
            throw new UncountableExportQueryException('Unable to compute a reliable export row count. The default strategy requires a single root entity with a single-field identifier. Implement CustomExportCountQueryBuilderInterface on the CRUD controller.');
        }

        $countQb = $this->createBaseCountQueryBuilder($queryBuilder);

        return (int) $countQb
            ->select(\sprintf('COUNT(DISTINCT %s)', $identifierExpression))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Creates a cloned query builder suitable for count queries.
     *
     * The original export query builder may contain sorting or pagination settings
     * that must not affect the total number of matching rows. This method removes:
     * - ORDER BY clauses
     * - first result offset
     * - max results limit
     */
    private function createBaseCountQueryBuilder(QueryBuilder $queryBuilder): QueryBuilder
    {
        $countQb = clone $queryBuilder;
        $countQb->resetDQLPart('orderBy');
        $countQb->setFirstResult(null);
        $countQb->setMaxResults(null);

        return $countQb;
    }

    /**
     * Returns whether the query builder contains a GROUP BY clause.
     *
     * Grouped queries are considered ambiguous for the default count strategy,
     * because the resulting row count may no longer match a simple entity count.
     */
    private function hasGroupBy(QueryBuilder $queryBuilder): bool
    {
        $groupByParts = $queryBuilder->getDQLPart('groupBy');

        if (!\is_array($groupByParts)) {
            return null !== $groupByParts;
        }

        return [] !== $groupByParts;
    }

    /**
     * Resolves the DQL expression of the root entity identifier used for DISTINCT counting.
     *
     * Expected shape:
     * - exactly one root alias;
     * - exactly one root entity;
     * - exactly one identifier field;
     * - identifier must be scalar, not an association.
     *
     * Example returned value:
     * - "entity.id"
     * - "stats.uuid"
     *
     * @return string|null The DQL expression to use in COUNT(DISTINCT ...), or
     *                     null when the default strategy cannot resolve one safely.
     *
     * @throws UncountableExportQueryException when the identifier exists but is an
     *                                         association-based identifier
     */
    private function getSingleRootIdentifierExpression(QueryBuilder $queryBuilder): ?string
    {
        /** @var list<string> $rootAliases */
        $rootAliases = $queryBuilder->getRootAliases();

        /** @var list<class-string> $rootEntities */
        $rootEntities = $queryBuilder->getRootEntities();

        if (1 !== \count($rootAliases) || 1 !== \count($rootEntities)) {
            return null;
        }

        $rootAlias = $rootAliases[0];
        $rootEntity = $rootEntities[0];

        if ('' === trim($rootAlias) || '' === trim($rootEntity)) {
            return null;
        }

        $entityManager = $queryBuilder->getEntityManager();
        $metadata = $entityManager->getClassMetadata($rootEntity);

        /** @var list<string> $identifierFieldNames */
        $identifierFieldNames = $metadata->getIdentifierFieldNames();

        if (1 !== \count($identifierFieldNames)) {
            return null;
        }

        $identifierFieldName = $identifierFieldNames[0];

        if ('' === trim($identifierFieldName)) {
            return null;
        }

        if ($metadata->hasAssociation($identifierFieldName)) {
            throw new UncountableExportQueryException(\sprintf('Unable to compute a reliable export row count for "%s". Association-based identifiers are not supported by the default count strategy. Implement CustomExportCountQueryBuilderInterface on the CRUD controller.', $rootEntity));
        }

        return \sprintf('%s.%s', $rootAlias, $identifierFieldName);
    }
}
