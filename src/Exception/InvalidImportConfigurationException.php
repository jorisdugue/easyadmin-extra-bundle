<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class InvalidImportConfigurationException extends EasyAdminExtraException
{
    public static function missingAdminImportAttribute(string $crudControllerFqcn): self
    {
        return new self(\sprintf(
            'The CRUD controller "%s" must be annotated with #[AdminImport] to enable imports.',
            $crudControllerFqcn,
        ));
    }

    public static function missingImportFieldsProvider(string $crudControllerFqcn, string $expectedInterface): self
    {
        return new self(\sprintf(
            'The CRUD controller "%s" must implement "%s" to provide import fields.',
            $crudControllerFqcn,
            $expectedInterface,
        ));
    }

    public static function invalidImportField(string $crudControllerFqcn, string $expectedInterface): self
    {
        return new self(\sprintf(
            'The "%s::getImportFields()" method must return only instances of "%s".',
            $crudControllerFqcn,
            $expectedInterface,
        ));
    }

    public static function missingFieldProperty(string $fieldLabel): self
    {
        return new self(\sprintf(
            'The import field "%s" must define a property.',
            $fieldLabel,
        ));
    }

    public static function duplicateCsvColumnPosition(int $position, string $firstField, string $secondField): self
    {
        return new self(\sprintf(
            'Duplicate import CSV column position %d configured for fields "%s" and "%s".',
            $position,
            $firstField,
            $secondField,
        ));
    }

    public static function mixedExplicitAndSequentialMapping(): self
    {
        return new self('Mixed import mapping is ambiguous. When using position(), every importable field must define an explicit CSV column position.');
    }
}
