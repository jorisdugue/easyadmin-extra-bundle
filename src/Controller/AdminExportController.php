<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Service\ExportManager;
use RuntimeException;
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

    public function __invoke(Request $request): Response
    {
        $crudControllerFqcn = (string) $request->attributes->get('_jd_ea_extra_crud', '');
        $dashboardControllerFqcn = (string) $request->attributes->get('_jd_ea_extra_dashboard', '');
        $format = (string) $request->attributes->get('_jd_ea_extra_format', '');

        if ('' === $crudControllerFqcn) {
            throw $this->createNotFoundException("Aucun CRUD controller fourni pour l'export.");
        }

        if ('' === $format) {
            throw new RuntimeException("Aucun format fournis pour l'export");
        }
        // Force to build a new contexte of EA or this will be not working
        $context = $this->adminContextFactory->create(
            $request,
            $this->dashboardResolver->resolve($dashboardControllerFqcn),
            $this->controllerResolver->resolve($crudControllerFqcn),
            // Force here for contexte datas
            $request->attributes->get(EA::CRUD_ACTION)
        );
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return $this->exportManager->export($crudControllerFqcn, $format, $request);
    }
}
