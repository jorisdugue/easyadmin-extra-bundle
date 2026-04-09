<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto\Operation;

use DateTimeImmutable;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Generic runtime context shared by bundle operations.
 */
final readonly class OperationContext
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public string $scope,
        public DateTimeImmutable $generatedAt,
        public ?UserInterface $user,
        public string $entityName,
        public array $roles,
    ) {}
}
