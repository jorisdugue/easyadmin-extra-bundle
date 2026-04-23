<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

final class AdminExportPreviewController extends AbstractController
{
    public function __construct(
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly ExportConfigFactory $exportConfigFactory,
        private readonly ExportManager $exportManager,
        private readonly ExportRouteMetadataResolver $exportRouteMetadataResolver,
        private readonly OperationRequestMetadataResolver $operationRequestMetadataResolver,
        private readonly OperationAdminContextFactory $operationAdminContextFactory,
        private readonly RouterInterface $router,
    ) {}

    /**
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $metadata = $this->operationRequestMetadataResolver->resolveWithoutFormat($request, 'export preview');

        $config = $this->exportConfigFactory->create($metadata->crudControllerFqcn);
        $requestedFormat = $request->query->getString('format');
        $format = '' !== $requestedFormat
            ? ExportFormat::normalize($requestedFormat)
            : $config->formats[0];

        if (!$config->supportsFormat($format)) {
            throw $this->createNotFoundException(\sprintf('The export format "%s" is not enabled for preview.', $format));
        }
        $context = $this->operationAdminContextFactory->createForRequest($request, $metadata, $this->crudActionNameResolver->resolve($request));

        $preview = $this->exportManager->preview($metadata->crudControllerFqcn, $format, $request);
        $dashboardRouteName = (string) $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($metadata->crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        unset($currentQuery['format']);

        /** @var array<string, string> $previewUrls */
        $previewUrls = [];
        /** @var array<string, string> $downloadUrls */
        $downloadUrls = [];

        foreach ($config->formats as $supportedFormat) {
            $previewUrls[$supportedFormat] = $this->router->generate(
                \sprintf('%s_%s_export_preview', $dashboardRouteName, $crudRouteName),
                [...$currentQuery, 'format' => $supportedFormat],
            );
            $downloadUrls[$supportedFormat] = $this->router->generate(
                \sprintf('%s_%s_export_%s', $dashboardRouteName, $crudRouteName, $supportedFormat),
                $currentQuery,
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
