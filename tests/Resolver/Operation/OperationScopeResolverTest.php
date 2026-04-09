<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class OperationScopeResolverTest extends TestCase
{
    public function testItResolvesContextScopeWhenRequestContainsFilters(): void
    {
        $resolver = new OperationScopeResolver();
        $request = new Request(['filters' => ['status' => ['comparison' => '=', 'value' => 'active']]]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: true);

        self::assertSame(OperationScopeResolver::SCOPE_CONTEXT, $resolver->resolveForExport($request, $config));
    }

    public function testItRejectsForcedAllWhenFullExportIsDisabled(): void
    {
        $resolver = new OperationScopeResolver();
        $request = new Request(['mode' => OperationScopeResolver::SCOPE_ALL]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: false, filteredExport: true);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Full export (mode=all) is not enabled for this resource.');

        $resolver->resolveForExport($request, $config);
    }

    public function testItRejectsContextOnlyConfigWithoutContextSignals(): void
    {
        $resolver = new OperationScopeResolver();
        $request = new Request();
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: false, filteredExport: true);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Filtered export is enabled for this resource, but the current request does not contain any search, filter, or sort context.');

        $resolver->resolveForExport($request, $config);
    }
}
