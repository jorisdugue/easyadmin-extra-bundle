<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use ReflectionClass;
use ReflectionException;

final class ImportConfigFactory
{
    /**
     * @param object|class-string<AbstractCrudController<object>> $crudController
     *
     * @throws InvalidImportConfigurationException
     * @throws ReflectionException
     */
    public function create(object|string $crudController, ?string $importSet = null): ImportConfig
    {
        $controllerFqcn = \is_object($crudController) ? $crudController::class : $crudController;
        $reflection = new ReflectionClass($controllerFqcn);
        $attributes = $reflection->getAttributes(AdminImport::class);

        if ([] === $attributes) {
            throw InvalidImportConfigurationException::missingAdminImportAttribute($controllerFqcn);
        }

        if (!is_subclass_of($controllerFqcn, ImportFieldsProviderInterface::class)) {
            throw InvalidImportConfigurationException::missingImportFieldsProvider($controllerFqcn, ImportFieldsProviderInterface::class);
        }

        $fields = $this->resolveFields($controllerFqcn, $importSet);
        $this->validateDuplicatePositions($fields);

        return new ImportConfig(
            fields: $fields,
            importSet: $importSet,
        );
    }

    /**
     * @param class-string<ImportFieldsProviderInterface> $controllerFqcn
     *
     * @return list<ImportFieldInterface>
     */
    private function resolveFields(string $controllerFqcn, ?string $importSet): array
    {
        $fields = $controllerFqcn::getImportFields($importSet);

        foreach ($fields as $field) {
            if (!$field instanceof ImportFieldInterface) {
                throw InvalidImportConfigurationException::invalidImportField($controllerFqcn, ImportFieldInterface::class);
            }
        }

        return array_values($fields);
    }

    /**
     * @param list<ImportFieldInterface> $fields
     */
    private function validateDuplicatePositions(array $fields): void
    {
        $fieldsByPosition = [];

        foreach ($fields as $field) {
            $position = $field->getAsDto()->getPosition();
            if (null === $position) {
                continue;
            }

            $property = (string) ($field->getAsDto()->getProperty() ?? '');
            if (isset($fieldsByPosition[$position])) {
                throw InvalidImportConfigurationException::duplicateCsvColumnPosition($position, $fieldsByPosition[$position], $property);
            }

            $fieldsByPosition[$position] = $property;
        }
    }
}
