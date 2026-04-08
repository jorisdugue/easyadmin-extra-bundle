<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use ReflectionClass;
use ReflectionException;

class ExportConfigFactory
{
    public function __construct(private readonly string $defaultActionDisplay = ExportActionDisplay::BUTTONS->value) {}

    /**
     * Creates an export configuration from a CRUD controller class or instance.
     *
     * @param object|class-string<AbstractCrudController<object>> $crudController
     *
     * @throws ReflectionException
     */
    public function create(object|string $crudController): ExportConfig
    {
        $controllerFqcn = \is_object($crudController) ? $crudController::class : $crudController;
        $reflection = new ReflectionClass($controllerFqcn);
        $attributes = $reflection->getAttributes(AdminExport::class);

        if ([] === $attributes) {
            throw InvalidExportConfigurationException::missingAdminExportAttribute($controllerFqcn);
        }

        if (!is_subclass_of($controllerFqcn, ExportFieldsProviderInterface::class)) {
            throw InvalidExportConfigurationException::missingExportFieldsProvider($controllerFqcn, ExportFieldsProviderInterface::class);
        }

        /** @var AdminExport $attribute */
        $attribute = $attributes[0]->newInstance();

        return new ExportConfig(
            filename: $attribute->filename,
            fields: $controllerFqcn::getExportFields(),
            formats: $attribute->formats,
            fullExport: $attribute->fullExport,
            filteredExport: $attribute->filteredExport,
            maxRows: $attribute->maxRows,
            requiredRole: $attribute->requiredRole,
            csvLabel: $attribute->csvLabel,
            xlsxLabel: $attribute->xlsxLabel,
            jsonLabel: $attribute->jsonLabel,
            allowSpreadsheetFormulas: $attribute->allowSpreadsheetFormulas,
            routeName: $attribute->routeName,
            routePath: $attribute->routePath,
            actionDisplay: $attribute->actionDisplay ?? ExportActionDisplay::from($this->defaultActionDisplay),
            previewEnabled: $attribute->previewEnabled,
            previewLimit: $attribute->previewLimit,
            previewLabel: $attribute->previewLabel,
            batchExport: $attribute->batchExport,
            batchExportLabel: $attribute->batchExportLabel,
        );
    }
}
