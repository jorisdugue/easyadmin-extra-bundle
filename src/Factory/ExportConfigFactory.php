<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

class ExportConfigFactory
{
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
            throw new RuntimeException(\sprintf('The CRUD controller "%s" must declare the #[AdminExport] attribute.', $controllerFqcn));
        }

        if (!is_subclass_of($controllerFqcn, ExportFieldsProviderInterface::class)) {
            throw new RuntimeException(\sprintf('The CRUD controller "%s" must implement %s.', $controllerFqcn, ExportFieldsProviderInterface::class));
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
        );
    }
}
