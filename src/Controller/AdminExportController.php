<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminExportController extends AbstractController
{
    public function __construct(
        private readonly ExportManager $exportManager,
        private readonly AdminContextFactory $adminContextFactory,
        private readonly CrudControllerResolver $controllerResolver,
        private readonly DashboardResolver $dashboardResolver,
    ) {}

    /**
     * Builds a fresh EasyAdmin context for the targeted dashboard and CRUD controller,
     * then delegates the export generation to the export manager.
     *
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $rawCrudControllerFqcn = $request->attributes->get('_jd_ea_extra_crud', '');
        $rawDashboardControllerFqcn = $request->attributes->get('_jd_ea_extra_dashboard', '');
        $rawFormat = $request->attributes->get('_jd_ea_extra_format', '');

        if (!\is_string($rawCrudControllerFqcn) || '' === trim($rawCrudControllerFqcn)) {
            throw $this->createNotFoundException('No CRUD controller was provided for export.');
        }

        if (!\is_string($rawDashboardControllerFqcn) || '' === trim($rawDashboardControllerFqcn)) {
            throw $this->createNotFoundException('No dashboard controller was provided for export.');
        }

        if (!\is_string($rawFormat) || '' === trim($rawFormat)) {
            throw new InvalidArgumentException('No export format was provided.');
        }
        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */
        $crudControllerFqcn = trim($rawCrudControllerFqcn);
        /** @var class-string<DashboardControllerInterface> $dashboardControllerFqcn */
        $dashboardControllerFqcn = trim($rawDashboardControllerFqcn);
        $format = ExportFormat::normalize($rawFormat);

        $rawCrudAction = $request->attributes->get(EA::CRUD_ACTION);
        $crudAction = \is_string($rawCrudAction) && '' !== trim($rawCrudAction) ? trim($rawCrudAction) : null;

        // Build a fresh EasyAdmin context for the targeted export request.
        $context = $this->adminContextFactory->create(
            $request,
            $this->dashboardResolver->resolve($dashboardControllerFqcn),
            $this->controllerResolver->resolve($crudControllerFqcn),
            $crudAction,
        );
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return $this->exportManager->export($crudControllerFqcn, $format, $request);
    }
}
