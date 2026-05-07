<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportRowResult;
use JorisDugue\EasyAdminExtraBundle\Exception\ImportPersistenceValidationException;
use ReflectionClass;
use RuntimeException;

final readonly class ImportPersister
{
    private const BATCH_SIZE = 50;

    public function __construct(private ManagerRegistry $managerRegistry) {}

    /**
     * @param class-string $entityFqcn
     * @param list<object> $entities
     */
    public function persistAll(string $entityFqcn, array $entities): void
    {
        $manager = $this->managerRegistry->getManagerForClass($entityFqcn);
        if (!$manager instanceof EntityManagerInterface) {
            throw new RuntimeException(\sprintf('No Doctrine entity manager is available for "%s".', $entityFqcn));
        }

        $metadata = $manager->getClassMetadata($entityFqcn);
        $this->validateRequiredFields($metadata, $entities);

        $manager->wrapInTransaction(static function (EntityManagerInterface $entityManager) use ($entities): void {
            $count = 0;
            foreach ($entities as $entity) {
                $entityManager->persist($entity);
                ++$count;

                if (0 === $count % self::BATCH_SIZE) {
                    $entityManager->flush();
                    $entityManager->clear();
                }
            }

            $entityManager->flush();
            $entityManager->clear();
        });
    }

    /**
     * @param ClassMetadata<object> $metadata
     * @param list<object>          $entities
     */
    private function validateRequiredFields(ClassMetadata $metadata, array $entities): void
    {
        $failedRows = [];

        foreach ($entities as $index => $entity) {
            $errors = [];

            foreach ($metadata->getFieldNames() as $fieldName) {
                if ($this->canBeMissingBeforeFlush($metadata, $fieldName)) {
                    continue;
                }

                if (null === $this->readFieldValue($entity, $fieldName)) {
                    $errors[] = \sprintf('Required Doctrine field "%s" is missing.', $fieldName);
                }
            }

            if ([] !== $errors) {
                $failedRows[] = new ImportRowResult($index + 1, false, $errors);
            }
        }

        if ([] !== $failedRows) {
            throw new ImportPersistenceValidationException($failedRows);
        }
    }

    /**
     * @param ClassMetadata<object> $metadata
     */
    private function canBeMissingBeforeFlush(ClassMetadata $metadata, string $fieldName): bool
    {
        if ($metadata->isNullable($fieldName)) {
            return true;
        }

        return $metadata->isIdentifier($fieldName) && $metadata->usesIdGenerator();
    }

    private function readFieldValue(object $entity, string $fieldName): mixed
    {
        $reflection = new ReflectionClass($entity);
        if (!$reflection->hasProperty($fieldName)) {
            return null;
        }

        return $reflection->getProperty($fieldName)->getValue($entity);
    }
}
