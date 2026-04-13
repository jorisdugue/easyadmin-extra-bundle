<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminExportBatchController extends AbstractController
{
    public function __construct(
        private readonly ExportManager $exportManager,
        private readonly AdminContextFactory $adminContextFactory,
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly CrudControllerResolver $controllerResolver,
        private readonly DashboardResolver $dashboardResolver,
    ) {}

    /**
     * Handles a batch export POST request submitted by EasyAdmin's batch action form.
     *
     * EasyAdmin submits selected entity IDs as batchActionEntityIds[] in the POST body.
     * This controller reads those IDs, builds a scoped query, and streams the export.
     *
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $rawCrudControllerFqcn = $request->attributes->get('_jd_ea_extra_crud', '');
        $rawDashboardControllerFqcn = $request->attributes->get('_jd_ea_extra_dashboard', '');
        $rawFormat = $request->attributes->get('_jd_ea_extra_format', '');

        if (!\is_string($rawCrudControllerFqcn) || '' === trim($rawCrudControllerFqcn)) {
            throw $this->createNotFoundException('No CRUD controller was provided for batch export.');
        }

        if (!\is_string($rawDashboardControllerFqcn) || '' === trim($rawDashboardControllerFqcn)) {
            throw $this->createNotFoundException('No dashboard controller was provided for batch export.');
        }

        if (!\is_string($rawFormat) || '' === trim($rawFormat)) {
            throw new InvalidArgumentException('No export format was provided for batch export.');
        }

        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */
        $crudControllerFqcn = trim($rawCrudControllerFqcn);
        /** @var class-string<DashboardControllerInterface> $dashboardControllerFqcn */
        $dashboardControllerFqcn = trim($rawDashboardControllerFqcn);
        $format = ExportFormat::normalize($rawFormat);

        $rawIds = $request->request->all('batchActionEntityIds');
        $ids = array_values(array_filter(
            array_map(static fn (mixed $id): string => trim(ValueStringifier::stringify($id)), $rawIds),
            static fn (string $id): bool => '' !== $id,
        ));

        if ([] === $ids) {
            throw new InvalidArgumentException('Batch export requires at least one selected entity ID.');
        }

        $context = $this->adminContextFactory->create(
            $request,
            $this->dashboardResolver->resolve($dashboardControllerFqcn),
            $this->controllerResolver->resolve($crudControllerFqcn),
            $this->crudActionNameResolver->resolve($request),
        );
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return $this->exportManager->exportBatch($crudControllerFqcn, $format, $ids, $request);
    }
}
