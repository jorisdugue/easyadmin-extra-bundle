<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class OperationRequestMetadataResolverTest extends TestCase
{
    public function testResolveExportReadsCrudDashboardAndFormat(): void
    {
        $resolver = new OperationRequestMetadataResolver();
        $request = new Request();
        $request->attributes->set('_jd_ea_extra_crud', 'App\\Controller\\ProductCrudController');
        $request->attributes->set('_jd_ea_extra_dashboard', 'App\\Controller\\AdminDashboardController');
        $request->attributes->set('_jd_ea_extra_format', ' CSV ');

        $metadata = $resolver->resolveExport($request, 'export');

        self::assertSame('App\\Controller\\ProductCrudController', $metadata->crudControllerFqcn);
        self::assertSame('App\\Controller\\AdminDashboardController', $metadata->dashboardControllerFqcn);
        self::assertSame('csv', $metadata->format);
    }

    public function testResolveExportRejectsMissingCrudAttribute(): void
    {
        $resolver = new OperationRequestMetadataResolver();

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('No CRUD controller was provided for export.');

        $resolver->resolveExport(new Request(), 'export');
    }

    public function testResolveExportRejectsMissingFormatAttribute(): void
    {
        $resolver = new OperationRequestMetadataResolver();
        $request = new Request();
        $request->attributes->set('_jd_ea_extra_crud', 'App\\Controller\\ProductCrudController');
        $request->attributes->set('_jd_ea_extra_dashboard', 'App\\Controller\\AdminDashboardController');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No export format was provided for batch export.');

        $resolver->resolveExport($request, 'batch export');
    }

    public function testResolveWithoutFormatKeepsFormatNull(): void
    {
        $resolver = new OperationRequestMetadataResolver();
        $request = new Request();
        $request->attributes->set('_jd_ea_extra_crud', 'App\\Controller\\ProductCrudController');
        $request->attributes->set('_jd_ea_extra_dashboard', 'App\\Controller\\AdminDashboardController');

        $metadata = $resolver->resolveWithoutFormat($request, 'export preview');

        self::assertNull($metadata->format);
    }
}
