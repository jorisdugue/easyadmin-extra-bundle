<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory\Operation;

use DateTimeImmutable;
use JorisDugue\EasyAdminExtraBundle\Dto\Operation\OperationContext;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class OperationContextFactory
{
    /**
     * @param list<string> $roles
     */
    public function create(
        string $scope,
        string $entityName,
        ?UserInterface $user,
        array $roles,
    ): OperationContext {
        return new OperationContext(
            scope: $scope,
            generatedAt: new DateTimeImmutable(),
            user: $user,
            entityName: $entityName,
            roles: $roles,
        );
    }

    /**
     * @return list<string>
     */
    public function resolveUserRoles(?UserInterface $user): array
    {
        if (null === $user) {
            return [];
        }

        $roles = [];

        foreach ($user->getRoles() as $role) {
            $normalizedRole = strtoupper(trim($role));

            if ('' === $normalizedRole) {
                continue;
            }

            if (!\in_array($normalizedRole, $roles, true)) {
                $roles[] = $normalizedRole;
            }
        }

        return $roles;
    }
}
