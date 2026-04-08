<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportBatchController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportPreviewController;
use JorisDugue\EasyAdminExtraBundle\EasyAdmin\ExportActionExtension;
use JorisDugue\EasyAdminExtraBundle\Exporter\CsvExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\JsonExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\XlsxExporter;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\BatchIdsQueryBuilderResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportCountResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use JorisDugue\EasyAdminExtraBundle\Routing\AdminExportRouteLoader;
use JorisDugue\EasyAdminExtraBundle\Service\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ExportConfigFactory::class)
        ->arg('$defaultActionDisplay', param('joris_dugue_easyadmin_extra.export.action_display'));
    $services->set(PropertyValueReader::class);
    $services->set(CrudControllerResolver::class)
        ->arg('$container', service('service_container'));
    $services->set(DashboardResolver::class)
        ->arg('$container', service('service_container'));
    $services->set(AdminExportBatchController::class);
    $services->set(FilenameResolver::class);
    $services->set(ExportPayloadFactory::class);
    $services->set(ExportFieldFormatResolver::class);
    $services->set(CsvExporter::class);
    $services->set(JsonExporter::class);
    $services->set(XlsxExporter::class);
    $services->set(CollectionFactoryCompat::class);
    $services->set(ExportManager::class);
    $services->set(ExportFieldValueResolver::class);
    $services->set(ExportRequestResolver::class);
    $services->set(BatchIdsQueryBuilderResolver::class);
    $services->set(SpreadsheetCellSanitizerService::class);
    $services->set(ExportRouteMetadataResolver::class);
    $services->set(AdminExportController::class)
        ->public()
        ->tag('controller.service_arguments');
    $services->set(AdminExportPreviewController::class)
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminExportRouteLoader::class)
        ->args([
            param('joris_dugue_easyadmin_extra.discovery_paths'),
            service(ExportConfigFactory::class),
            service(ExportRouteMetadataResolver::class),
        ])
        ->tag('routing.loader');
    $services->set(ExportCountResolver::class);
    $services->alias(ExportCountResolverInterface::class, ExportCountResolver::class);
    $services->set(ExportActionExtension::class)
        ->tag('ea.action_extension');
};
