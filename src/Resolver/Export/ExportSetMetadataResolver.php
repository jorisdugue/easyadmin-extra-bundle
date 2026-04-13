<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Export;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportSetMetadataProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;

final class ExportSetMetadataResolver
{
    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @return list<ExportSetMetadata>
     */
    public function resolveForCrud(string $crudControllerFqcn): array
    {
        if (!is_subclass_of($crudControllerFqcn, ExportSetMetadataProviderInterface::class)) {
            return [new ExportSetMetadata('default', 'Export')];
        }

        /** @var list<ExportSetMetadata> $metadata */
        $metadata = $crudControllerFqcn::getExportSetMetadata();

        if ([] === $metadata) {
            throw new InvalidExportConfigurationException(\sprintf('The CRUD controller "%s" must declare at least one export set metadata entry.', $crudControllerFqcn));
        }

        $resolved = [];

        foreach ($metadata as $item) {
            if (!$item instanceof ExportSetMetadata) {
                throw new InvalidExportConfigurationException(\sprintf('The CRUD controller "%s" must only return "%s" instances from getExportSetMetadata().', $crudControllerFqcn, ExportSetMetadata::class));
            }

            $name = $this->normalizeName($item->getName());

            if (isset($resolved[$name])) {
                throw new InvalidExportConfigurationException(\sprintf('The CRUD controller "%s" declares the export set "%s" more than once.', $crudControllerFqcn, $name));
            }

            $resolved[$name] = new ExportSetMetadata(
                $name,
                $this->resolveLabel($item, $name),
                $this->normalizeRoles($item->getRequiredRoles()),
            );
        }

        if (!isset($resolved['default'])) {
            throw new InvalidExportConfigurationException(\sprintf('The CRUD controller "%s" must declare a "default" export set metadata entry.', $crudControllerFqcn));
        }

        return array_values($resolved);
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    public function resolveRequestedSet(string $crudControllerFqcn, ?string $requestedSet): ExportSetMetadata
    {
        $resolvedSet = null !== $requestedSet ? $this->normalizeName($requestedSet) : 'default';
        $resolveMetadata = $this->resolveForCrud($crudControllerFqcn);
        foreach ($resolveMetadata as $metadata) {
            if ($metadata->getName() === $resolvedSet) {
                return $metadata;
            }
        }

        $availableSets = array_map(
            static fn (ExportSetMetadata $metadata): string => $metadata->getName(),
            $this->resolveForCrud($crudControllerFqcn),
        );

        throw new InvalidExportConfigurationException(\sprintf('Unknown export set "%s" for CRUD controller "%s". Available sets: %s.', $resolvedSet, $crudControllerFqcn, implode(', ', $availableSets)));
    }

    public function normalizeRequestedSet(mixed $requestedSet): ?string
    {
        if (!\is_string($requestedSet)) {
            return null;
        }

        $requestedSet = trim($requestedSet);

        if ('' === $requestedSet) {
            return null;
        }

        return $this->normalizeName($requestedSet);
    }

    private function normalizeName(string $name): string
    {
        $name = strtolower(trim($name));

        if ('' === $name) {
            throw new InvalidExportConfigurationException('Export set names cannot be empty.');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $name)) {
            throw new InvalidExportConfigurationException(\sprintf('Invalid export set name "%s". Allowed characters are: a-z, 0-9, "_" and "-".', $name));
        }

        return $name;
    }

    private function resolveLabel(ExportSetMetadata $metadata, string $normalizedName): string
    {
        $label = $metadata->getLabel();

        if (null !== $label) {
            $label = trim($label);

            if ('' !== $label) {
                return $label;
            }
        }

        if ('default' === $normalizedName) {
            return 'Export';
        }

        return $this->humanizeName($normalizedName);
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $resolved = [];

        foreach ($roles as $role) {
            $role = strtoupper(trim($role));

            if ('' === $role) {
                continue;
            }

            if (!\in_array($role, $resolved, true)) {
                $resolved[] = $role;
            }
        }

        return $resolved;
    }

    private function humanizeName(string $name): string
    {
        return ucfirst(str_replace(['_', '-'], ' ', $name));
    }
}
