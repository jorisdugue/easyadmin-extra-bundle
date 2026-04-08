<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class InvalidExportConfigurationException extends EasyAdminExtraException
{
    public static function missingAdminExportAttribute(string $crudControllerFqcn): self
    {
        return new self(\sprintf(
            'The CRUD controller "%s" must be annotated with #[AdminExport] to enable exports.',
            $crudControllerFqcn,
        ));
    }

    public static function missingExportFieldsProvider(string $crudControllerFqcn, string $expectedInterface): self
    {
        return new self(\sprintf(
            'The CRUD controller "%s" must implement "%s" to provide export fields.',
            $crudControllerFqcn,
            $expectedInterface,
        ));
    }

    public static function missingFieldProperty(string $fieldLabel): self
    {
        return new self(\sprintf(
            'The export field "%s" must define a property.',
            $fieldLabel,
        ));
    }

    public static function invalidExportAllQueryBuilderReturnType(
        string $crudControllerFqcn,
        string $expectedType,
        string $actualType,
    ): self {
        return new self(\sprintf(
            'The "%s::createExportAllQueryBuilder()" method must return an instance of "%s"; "%s" returned.',
            $crudControllerFqcn,
            $expectedType,
            $actualType,
        ));
    }

    /**
     * @param list<string> $supportedFormats
     */
    public static function unsupportedFormat(string $format, array $supportedFormats): self
    {
        return new self(\sprintf(
            'The export format "%s" is not supported. Supported formats: %s.',
            $format,
            implode(', ', $supportedFormats),
        ));
    }

    /**
     * @param list<string> $allowedFormats
     */
    public static function forbiddenFormat(string $format, string $crudControllerFqcn, array $allowedFormats): self
    {
        return new self(\sprintf(
            'The export format "%s" is not allowed for CRUD controller "%s". Allowed formats: %s.',
            $format,
            $crudControllerFqcn,
            implode(', ', $allowedFormats),
        ));
    }

    public static function invalidDashboardControllerService(string $serviceId, string $expectedType): self
    {
        return new self(\sprintf(
            'The service "%s" is not an instance of "%s".',
            $serviceId,
            $expectedType,
        ));
    }
}
