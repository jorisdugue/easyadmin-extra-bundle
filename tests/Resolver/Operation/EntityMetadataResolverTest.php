<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use PHPUnit\Framework\TestCase;

final class EntityMetadataResolverTest extends TestCase
{
    public function testGetClassMetadataUsesManagerForEntityClass(): void
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getClassMetadata')
            ->with(EntityMetadataResolverEntity::class)
            ->willReturn($metadata);

        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->expects(self::once())
            ->method('getManagerForClass')
            ->with(EntityMetadataResolverEntity::class)
            ->willReturn($entityManager);

        self::assertSame($metadata, (new EntityMetadataResolver($managerRegistry))->getClassMetadata(EntityMetadataResolverEntity::class));
    }

    public function testGetClassMetadataRejectsEntityWithoutManager(): void
    {
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        $managerRegistry
            ->method('getManagerForClass')
            ->with(EntityMetadataResolverEntity::class)
            ->willReturn(null);

        $this->expectException(InvalidBatchExportException::class);
        $this->expectExceptionMessage('no Doctrine entity manager handles this class');

        (new EntityMetadataResolver($managerRegistry))->getClassMetadata(EntityMetadataResolverEntity::class);
    }
}

final class EntityMetadataResolverEntity {}
