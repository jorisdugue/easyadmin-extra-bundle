<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use ReflectionClass;
use RuntimeException;

class ExportConfigFactory
{
    public function create(object|string $crudController): ExportConfig
    {
        $controllerFqcn = \is_object($crudController) ? $crudController::class : $crudController;
        $reflection = new ReflectionClass($controllerFqcn);
        $attributes = $reflection->getAttributes(AdminExport::class);

        if ([] === $attributes) {
            throw new RuntimeException(\sprintf('Le CRUD "%s" doit déclarer l\'attribut #[AdminExport].', $controllerFqcn));
        }

        if (!is_subclass_of($controllerFqcn, ExportFieldsProviderInterface::class)) {
            throw new RuntimeException(\sprintf('Le CRUD "%s" doit implémenter %s.', $controllerFqcn, ExportFieldsProviderInterface::class));
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
