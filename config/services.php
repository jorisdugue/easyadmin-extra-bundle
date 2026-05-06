<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportCountResolverInterface;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminImportPreviewController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportBatchController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportController;
use JorisDugue\EasyAdminExtraBundle\Controller\AdminExportPreviewController;
use JorisDugue\EasyAdminExtraBundle\EasyAdmin\ExportActionExtension;
use JorisDugue\EasyAdminExtraBundle\EasyAdmin\ImportActionExtension;
use JorisDugue\EasyAdminExtraBundle\Exporter\CsvExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\JsonExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\XlsxExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\XmlExporter;
use JorisDugue\EasyAdminExtraBundle\Factory\Export\ExportContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\EntityQueryBuilderFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\DashboardResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportPreviewInspector;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportCountResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\BatchExportRequestValidator;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntityMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\EntitySelectionResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationContextResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use JorisDugue\EasyAdminExtraBundle\Routing\AdminExportRouteLoader;
use JorisDugue\EasyAdminExtraBundle\Routing\AdminOperationRouteLoader;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExporterRegistry;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Service\Import\CsvPreviewReader;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use JorisDugue\EasyAdminExtraBundle\Service\SpreadsheetCellSanitizerService;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
        ->autowire()
        ->autoconfigure();

    $services->set(ExportConfigFactory::class)
        ->arg('$defaultActionDisplay', param('joris_dugue_easyadmin_extra.export.action_display'));
    $services->set(OperationRequestMetadataResolver::class);
    $services->set(OperationAdminContextFactory::class);
    $services->set(PropertyValueReader::class);
    $services->set(RoleAuthorizationChecker::class);
    $services->set(CrudControllerResolver::class)
        ->arg('$container', service('service_container'));
    $services->set(DashboardResolver::class)
        ->arg('$container', service('service_container'));
    $services->set(CrudActionNameResolver::class);
    $services->set(AdminExportBatchController::class)
        ->public()
        ->tag('controller.service_arguments');
    $services->set(FilenameResolver::class);
    $services->set(ExportPayloadFactory::class);
    $services->set(ExportFieldFormatResolver::class);
    $services->set(CsvExporter::class)
        ->tag('joris_dugue_easyadmin_extra.exporter');

    $services->set(JsonExporter::class)
        ->tag('joris_dugue_easyadmin_extra.exporter');

    $services->set(XlsxExporter::class)
        ->tag('joris_dugue_easyadmin_extra.exporter');

    $services->set(XmlExporter::class)
        ->tag('joris_dugue_easyadmin_extra.exporter');

    $services->set(ExporterRegistry::class)
        ->arg('$exporters', tagged_iterator('joris_dugue_easyadmin_extra.exporter'));

    $services->set(CollectionFactoryCompat::class);
    $services->set(CsvPreviewReader::class);
    $services->set(ExportManager::class);
    $services->set(ExportFieldValueResolver::class);
    $services->set(ExportRequestResolver::class);
    $services->set(ExportSetMetadataResolver::class);
    $services->set(ExportContextFactory::class);
    $services->set(EntityQueryBuilderFactory::class);
    $services->set(ActiveIndexContextResolver::class);
    $services->set(BatchExportRequestValidator::class);
    $services->set(ExportPreviewInspector::class);
    $services->set(OperationScopeResolver::class);
    $services->set(EntityMetadataResolver::class);
    $services->set(OperationContextFactory::class);
    $services->set(OperationContextResolver::class);
    $services->set(EntitySelectionResolver::class);
    $services->set(SpreadsheetCellSanitizerService::class);
    $services->set(ExportRouteMetadataResolver::class);
    $services->set(ImportRouteMetadataResolver::class);

    $services->set(AdminExportController::class)
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminExportPreviewController::class)
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminImportPreviewController::class)
        ->public()
        ->tag('controller.service_arguments');

    $services->set(AdminOperationRouteLoader::class)
        ->args([
            param('joris_dugue_easyadmin_extra.discovery_paths'),
            service(ExportConfigFactory::class),
            service(ExportRouteMetadataResolver::class),
        ])
        ->tag('routing.loader');

    $services->set(AdminExportRouteLoader::class)
        ->args([
            param('joris_dugue_easyadmin_extra.discovery_paths'),
            service(ExportConfigFactory::class),
            service(ExportRouteMetadataResolver::class),
        ]);
    $services->set(ExportCountResolver::class);
    $services->alias(ExportCountResolverInterface::class, ExportCountResolver::class);
    $services->set(ExportActionExtension::class)
        ->tag('ea.action_extension');

    $services->set(ImportActionExtension::class)
        ->tag('ea.action_extension');
};
