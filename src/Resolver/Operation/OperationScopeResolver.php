<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final readonly class OperationScopeResolver
{
    public const SCOPE_ALL = 'all';
    public const SCOPE_CONTEXT = 'context';
    public const SCOPE_SELECTION = 'selection';

    public function __construct(private readonly ActiveIndexContextResolver $activeIndexContextResolver) {}

    /**
     * Resolves the export scope from the current request and export configuration.
     */
    public function resolveForExport(Request $request, ExportConfig $config): string
    {
        $forcedMode = trim($request->query->getString('mode'));

        if ('' !== $forcedMode && !\in_array($forcedMode, [self::SCOPE_ALL, self::SCOPE_CONTEXT], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid export mode "%s" provided in query parameter "mode". Allowed values are "%s" and "%s".', $forcedMode, self::SCOPE_ALL, self::SCOPE_CONTEXT));
        }

        if (self::SCOPE_ALL === $forcedMode) {
            if (!$config->fullExport) {
                throw new AccessDeniedException('Full export (mode=all) is not enabled for this resource.');
            }

            return self::SCOPE_ALL;
        }

        if (self::SCOPE_CONTEXT === $forcedMode) {
            if (!$config->filteredExport) {
                throw new AccessDeniedException('Filtered export (mode=context) is not enabled for this resource.');
            }

            return self::SCOPE_CONTEXT;
        }

        $hasActiveContext = $this->activeIndexContextResolver->hasActiveContext($request);

        if ($hasActiveContext) {
            if (!$config->filteredExport) {
                throw new AccessDeniedException('Filtered export is not enabled for this resource.');
            }

            return self::SCOPE_CONTEXT;
        }

        if ($config->fullExport) {
            return self::SCOPE_ALL;
        }

        if ($config->filteredExport) {
            throw new AccessDeniedException('Filtered export is enabled for this resource, but the current request does not contain any search, filter, or sort context.');
        }

        throw new AccessDeniedException('Export is not enabled for this resource.');
    }
}
