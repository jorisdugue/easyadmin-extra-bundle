<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use Symfony\Component\HttpFoundation\Request;

final readonly class ActiveIndexContextResolver
{
    /**
     * Returns true when the current request contains an active EasyAdmin index context,
     * meaning at least one search, filter, or sort parameter is applied.
     */
    public function hasActiveContext(Request $request): bool
    {
        $hasSearch = '' !== trim((string) ($request->query->get('query') ?? ''));
        $hasFilters = [] !== $request->query->all('filters');
        $hasSort = [] !== $request->query->all('sort');

        return $hasSearch || $hasFilters || $hasSort;
    }
}