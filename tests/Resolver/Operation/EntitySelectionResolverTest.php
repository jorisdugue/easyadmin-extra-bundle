<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use PHPUnit\Framework\TestCase;

final class EntitySelectionResolverTest extends TestCase
{
    public function testResolveRejectsEmptySelection(): void
    {
        $resolver = new EntitySelectionResolver();

        $this->expectException(InvalidBatchExportException::class);

        $resolver->resolve([]);
    }

    public function testCastIdsCastsNumericIdentifiersToIntegers(): void
    {
        $resolver = new EntitySelectionResolver();

        self::assertSame([42, 7, 1, 0], $resolver->castIds(['42', '007', '1', '0'], 'integer'));
        self::assertSame([42, 7], $resolver->castIds(['42', '007'], 'smallint'));
        self::assertSame([42, 7], $resolver->castIds(['42', '007'], 'bigint'));
    }

    public function testCastIdsKeepsStringIdentifiersForNonNumericTypes(): void
    {
        $resolver = new EntitySelectionResolver();

        self::assertSame(['42', '007', 'abc-123'], $resolver->castIds(['42', '007', 'abc-123'], 'uuid'));
    }
}
