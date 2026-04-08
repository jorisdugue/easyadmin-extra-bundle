<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Exception\EasyAdminExtraException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;

/**
 * Builds a QueryBuilder scoped to a specific set of entity IDs.
 *
 * This resolver is used by bulk export to produce a WHERE id IN (:ids) query
 * from the list of IDs submitted by EasyAdmin's batch action form.
 */
final class BatchIdsQueryBuilderResolver
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Returns a QueryBuilder selecting only the entities matching the given IDs.
     *
     * @param AbstractCrudController<object> $crudController
     * @param list<int|string>               $ids
     *
     * @throws EasyAdminExtraException  when the entity class has no single scalar identifier
     * @throws MappingException
     */
    public function resolve(AbstractCrudController $crudController, array $ids): QueryBuilder
    {
        /** @var class-string<object> $entityFqcn */
        $entityFqcn = $crudController::getEntityFqcn();
        $metadata = $this->entityManager->getClassMetadata($entityFqcn);

        $identifierFieldNames = $metadata->getIdentifierFieldNames();

        if (1 !== \count($identifierFieldNames)) {
            throw InvalidBatchExportException::compositeIdentifiersNotSupported($entityFqcn);
        }

        $identifierField = $identifierFieldNames[0];
        $rootAlias = 'entity';

        return $this->entityManager
            ->createQueryBuilder()
            ->select($rootAlias)
            ->from($entityFqcn, $rootAlias)
            ->where(\sprintf('%s.%s IN (:batchIds)', $rootAlias, $identifierField))
            ->setParameter('batchIds', $this->castIds($ids, $entityFqcn, $identifierField));
    }

    /**
     * Casts raw string IDs to the correct PHP type based on Doctrine metadata.
     *
     * EasyAdmin submits all IDs as strings via the POST body.
     * This method converts them to int or keeps them as string depending on the
     * actual identifier type declared on the entity.
     *
     * @param list<int|string> $ids
     * @param class-string<object> $entityFqcn
     *
     * @return list<int|string>
     *
     * @throws MappingException
     */
    private function castIds(array $ids, string $entityFqcn, string $identifierField): array
    {
        $mapping = $this->entityManager
            ->getClassMetadata($entityFqcn)
            ->getFieldMapping($identifierField);

        $type = $mapping['type'] ?? 'string';

        return array_values(array_map(
            static fn (int|string $id): int|string => \in_array($type, ['integer', 'smallint', 'bigint'], true)
                ? (int) $id
                : (string) $id,
            $ids,
        ));
    }
}
