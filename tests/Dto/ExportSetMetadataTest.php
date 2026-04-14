<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Dto;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use PHPUnit\Framework\TestCase;

final class ExportSetMetadataTest extends TestCase
{
    public function testAcceptsSingleRoleAsString(): void
    {
        $metadata = new ExportSetMetadata('support', 'Support export', 'ROLE_USER');

        self::assertSame(['ROLE_USER'], $metadata->getRequiredRoles());
    }

    public function testThrowsWhenRoleIsEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ExportSetMetadata('support', 'Support export', '');
    }
}
