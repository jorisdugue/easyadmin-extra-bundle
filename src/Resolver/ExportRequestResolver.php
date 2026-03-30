<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves request-driven export context information and UI visibility rules.
 */
final class ExportRequestResolver
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

    /**
     * Returns true when the export action should be visible for the current request.
     */
    public function canDisplayExportAction(ExportConfig $config, Request $request): bool
    {
        if ($config->fullExport) {
            return true;
        }

        if (!$config->filteredExport) {
            return false;
        }

        return $this->hasActiveContext($request);
    }
}
