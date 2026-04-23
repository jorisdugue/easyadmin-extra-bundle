<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use Symfony\Component\HttpFoundation\Request;

final class CrudActionNameResolver
{
    private const DEFAULT_ACTION = 'index';

    public function resolve(Request $request): string
    {
        $rawAction = $request->attributes->get(EA::CRUD_ACTION);
        if (\is_string($rawAction) && '' !== trim($rawAction)) {
            return trim($rawAction);
        }

        $rawAction = $request->query->get(EA::CRUD_ACTION);
        if (\is_string($rawAction) && '' !== trim($rawAction)) {
            return trim($rawAction);
        }

        return self::DEFAULT_ACTION;
    }
}
