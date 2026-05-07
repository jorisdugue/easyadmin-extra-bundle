<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\MappingException;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use ReflectionClass;
use ReflectionException;

final readonly class EntityMetadataResolver
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {}

    /**
     * @param AbstractCrudController<object> $crudController
     *
     * @throws ReflectionException
     */
    public function guessEntityName(AbstractCrudController $crudController): string
    {
        $short = (new ReflectionClass($crudController::getEntityFqcn()))->getShortName();
        $short = preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? $short;

        return strtolower($short);
    }

    /**
     * @param class-string<object> $entityFqcn
     *
     * @return ClassMetadata<object>
     */
    public function getClassMetadata(string $entityFqcn): ClassMetadata
    {
        return $this->getEntityManagerForClass($entityFqcn)->getClassMetadata($entityFqcn);
    }

    /**
     * @param class-string<object> $entityFqcn
     */
    public function getSingleIdentifierField(string $entityFqcn): string
    {
        $metadata = $this->getClassMetadata($entityFqcn);
        $identifierFieldNames = $metadata->getIdentifierFieldNames();

        if (1 !== \count($identifierFieldNames)) {
            throw InvalidBatchExportException::compositeIdentifiersNotSupported($entityFqcn);
        }

        return $identifierFieldNames[0];
    }

    /**
     * @param class-string<object> $entityFqcn
     *
     * @throws MappingException
     */
    public function getIdentifierType(string $entityFqcn, string $identifierField): string
    {
        $metadata = $this->getClassMetadata($entityFqcn);
        $mappings = $metadata->getFieldMapping($identifierField);
        $type = $mappings['type'] ?? 'string';

        return \is_string($type) ? $type : 'string';
    }

    /**
     * @param class-string<object> $entityFqcn
     */
    private function getEntityManagerForClass(string $entityFqcn): EntityManagerInterface
    {
        $manager = $this->managerRegistry->getManagerForClass($entityFqcn);

        if (!$manager instanceof EntityManagerInterface) {
            throw InvalidBatchExportException::missingEntityManager($entityFqcn);
        }

        return $manager;
    }
}
