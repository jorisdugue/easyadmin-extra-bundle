<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves request-driven export context information and UI visibility rules.
 */
final class ExportRequestResolver
{
    public function __construct(private readonly ActiveIndexContextResolver $activeIndexContextResolver) {}

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

        return $this->activeIndexContextResolver->hasActiveContext($request);
    }
}
