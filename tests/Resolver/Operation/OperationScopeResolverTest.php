<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class OperationScopeResolverTest extends TestCase
{
    private OperationScopeResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OperationScopeResolver(new ActiveIndexContextResolver());
    }

    public function testItResolvesContextScopeWhenRequestContainsFilters(): void
    {
        $request = new Request(['filters' => ['status' => ['comparison' => '=', 'value' => 'active']]]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: true);

        self::assertSame(OperationScopeResolver::SCOPE_CONTEXT, $this->resolver->resolveForExport($request, $config));
    }

    public function testItAcceptsExplicitContextModeWhenFilteredExportIsEnabled(): void
    {
        $request = new Request(['mode' => OperationScopeResolver::SCOPE_CONTEXT]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: true);

        self::assertSame(OperationScopeResolver::SCOPE_CONTEXT, $this->resolver->resolveForExport($request, $config));
    }

    public function testItRejectsForcedAllWhenFullExportIsDisabled(): void
    {
        $request = new Request(['mode' => OperationScopeResolver::SCOPE_ALL]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: false, filteredExport: true);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Full export (mode=all) is not enabled for this resource.');

        $this->resolver->resolveForExport($request, $config);
    }

    public function testItRejectsForcedContextWhenFilteredExportIsDisabled(): void
    {
        $request = new Request(['mode' => OperationScopeResolver::SCOPE_CONTEXT]);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Filtered export (mode=context) is not enabled for this resource.');

        $this->resolver->resolveForExport($request, $config);
    }

    public function testItRejectsInvalidMode(): void
    {
        $request = new Request(['mode' => 'wat']);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: true);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid export mode "wat" provided in query parameter "mode". Allowed values are "all" and "context".');

        $this->resolver->resolveForExport($request, $config);
    }

    public function testItFallsBackToAllWhenFullExportIsEnabledAndNoContextIsActive(): void
    {
        $request = new Request();
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: false);

        self::assertSame(OperationScopeResolver::SCOPE_ALL, $this->resolver->resolveForExport($request, $config));
    }

    public function testItRejectsActiveContextWhenFilteredExportIsDisabled(): void
    {
        $request = new Request(['query' => 'alice']);
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: true, filteredExport: false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Filtered export is not enabled for this resource.');

        $this->resolver->resolveForExport($request, $config);
    }

    public function testItRejectsContextOnlyConfigWithoutContextSignals(): void
    {
        $request = new Request();
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: false, filteredExport: true);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Filtered export is enabled for this resource, but the current request does not contain any search, filter, or sort context.');

        $this->resolver->resolveForExport($request, $config);
    }

    public function testItRejectsWhenExportIsCompletelyDisabled(): void
    {
        $request = new Request();
        $config = new ExportConfig(filename: 'users', fields: [], fullExport: false, filteredExport: false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage('Export is not enabled for this resource.');

        $this->resolver->resolveForExport($request, $config);
    }
}