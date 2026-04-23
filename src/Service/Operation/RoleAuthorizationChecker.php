<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Operation;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class RoleAuthorizationChecker
{
    public function __construct(private AuthorizationCheckerInterface $authorizationChecker) {}

    /**
     * @param list<string> $roles
     */
    public function isGrantedForAnyRole(array $roles): bool
    {
        if ([] === $roles) {
            return true;
        }

        foreach ($roles as $role) {
            if ($this->authorizationChecker->isGranted($role)) {
                return true;
            }
        }

        return false;
    }
}
