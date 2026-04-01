<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Controller\DashboardControllerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\AdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\ExportManager;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class AdminExportPreviewController extends AbstractController
{
    public function __construct(
        private readonly AdminContextFactory $adminContextFactory,
        private readonly CrudControllerResolver $controllerResolver,
        private readonly DashboardResolver $dashboardResolver,
        private readonly ExportConfigFactory $exportConfigFactory,
        private readonly ExportManager $exportManager,
        private readonly ExportRouteMetadataResolver $exportRouteMetadataResolver,
        private readonly RouterInterface $router,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $rawCrudControllerFqcn = $request->attributes->get('_jd_ea_extra_crud', '');
        $rawDashboardControllerFqcn = $request->attributes->get('_jd_ea_extra_dashboard', '');

        if (!\is_string($rawCrudControllerFqcn) || '' === trim($rawCrudControllerFqcn)) {
            throw $this->createNotFoundException('No CRUD controller was provided for export preview.');
        }

        if (!\is_string($rawDashboardControllerFqcn) || '' === trim($rawDashboardControllerFqcn)) {
            throw $this->createNotFoundException('No dashboard controller was provided for export preview.');
        }

        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */
        $crudControllerFqcn = trim($rawCrudControllerFqcn);
        /** @var class-string<DashboardControllerInterface> $dashboardControllerFqcn */
        $dashboardControllerFqcn = trim($rawDashboardControllerFqcn);

        $config = $this->exportConfigFactory->create($crudControllerFqcn);
        $requestedFormat = $request->query->getString('format');
        $format = '' !== $requestedFormat
            ? ExportFormat::normalize($requestedFormat)
            : $config->formats[0];

        if (!$config->supportsFormat($format)) {
            throw $this->createNotFoundException(\sprintf('The export format "%s" is not enabled for preview.', $format));
        }

        $crudAction = $request->attributes->get(EA::CRUD_ACTION);
        $context = $this->adminContextFactory->create(
            $request,
            $this->dashboardResolver->resolve($dashboardControllerFqcn),
            $this->controllerResolver->resolve($crudControllerFqcn),
            $crudAction
        );
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        $preview = $this->exportManager->preview($crudControllerFqcn, $format, $request);
        $dashboardRouteName = (string) $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        unset($currentQuery['format']);

        /** @var array<string, string> $previewUrls */
        $previewUrls = [];
        /** @var array<string, string> $downloadUrls */
        $downloadUrls = [];

        foreach ($config->formats as $supportedFormat) {
            $previewUrls[$supportedFormat] = $this->router->generate(
                \sprintf('%s_%s_export_preview', $dashboardRouteName, $crudRouteName),
                [...$currentQuery, 'format' => $supportedFormat]
            );
            $downloadUrls[$supportedFormat] = $this->router->generate(
                \sprintf('%s_%s_export_%s', $dashboardRouteName, $crudRouteName, $supportedFormat),
                $currentQuery
            );
        }

        return $this->render('@JorisDugueEasyAdminExtraBundle/export/preview.html.twig', [
            'preview' => $preview,
            'preview_urls' => $previewUrls,
            'download_urls' => $downloadUrls,
            'available_formats' => $config->formats,
        ]);
    }
}
