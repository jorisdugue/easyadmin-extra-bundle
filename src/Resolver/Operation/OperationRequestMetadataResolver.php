<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Dto\Operation\OperationRequestMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final readonly class OperationRequestMetadataResolver
{
    /**
     * @throws InvalidArgumentException
     */
    public function resolveExport(Request $request, string $operationLabel): OperationRequestMetadata
    {
        return new OperationRequestMetadata(
            crudControllerFqcn: $this->requireCrudControllerAttribute($request, $operationLabel),
            dashboardControllerFqcn: $this->requireDashboardControllerAttribute($request, $operationLabel),
            format: $this->resolveFormat($request, $operationLabel),
        );
    }

    public function resolveWithoutFormat(Request $request, string $operationLabel): OperationRequestMetadata
    {
        return new OperationRequestMetadata(
            crudControllerFqcn: $this->requireCrudControllerAttribute($request, $operationLabel),
            dashboardControllerFqcn: $this->requireDashboardControllerAttribute($request, $operationLabel),
        );
    }

    /**
     * @return class-string<AbstractCrudController<object>>
     */
    private function requireCrudControllerAttribute(Request $request, string $operationLabel): string
    {
        $value = $request->attributes->get('_jd_ea_extra_crud', '');

        if (!\is_string($value) || '' === trim($value)) {
            throw new NotFoundHttpException(\sprintf('No CRUD controller was provided for %s.', $operationLabel));
        }

        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */
        $crudControllerFqcn = trim($value);

        return $crudControllerFqcn;
    }

    /**
     * @return class-string<DashboardControllerInterface>
     */
    private function requireDashboardControllerAttribute(Request $request, string $operationLabel): string
    {
        $value = $request->attributes->get('_jd_ea_extra_dashboard', '');

        if (!\is_string($value) || '' === trim($value)) {
            throw new NotFoundHttpException(\sprintf('No dashboard controller was provided for %s.', $operationLabel));
        }

        /** @var class-string<DashboardControllerInterface> $dashboardControllerFqcn */
        $dashboardControllerFqcn = trim($value);

        return $dashboardControllerFqcn;
    }

    private function resolveFormat(Request $request, string $operationLabel): string
    {
        $rawFormat = $request->attributes->get('_jd_ea_extra_format', '');

        if (!\is_string($rawFormat) || '' === trim($rawFormat)) {
            throw new InvalidArgumentException(\sprintf('No export format was provided for %s.', $operationLabel));
        }

        return ExportFormat::normalize($rawFormat);
    }
}
