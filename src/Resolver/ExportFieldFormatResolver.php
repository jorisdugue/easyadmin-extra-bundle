<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;

final class ExportFieldFormatResolver
{
    /**
     * Determines whether a field is visible for the given export format.
     */
    private function isVisibleForFormat(ExportFieldDto $dto, string $format): bool
    {
        $visibleFormats = $dto->getCustomOption(ExportFieldOption::VISIBLE_FORMATS);
        $hiddenFormats = $dto->getCustomOption(ExportFieldOption::HIDDEN_FORMATS);

        if (\is_array($visibleFormats) && [] !== $visibleFormats) {
            return \in_array($format, $visibleFormats, true);
        }

        if (\is_array($hiddenFormats) && [] !== $hiddenFormats) {
            return !\in_array($format, $hiddenFormats, true);
        }

        return true;
    }

    /**
     * Determines whether a field is visible for the given security roles.
     *
     * @param list<string> $roles
     */
    private function isVisibleForRoles(ExportFieldDto $dto, array $roles): bool
    {
        $visibleRoles = $dto->getCustomOption(ExportFieldOption::VISIBLE_ROLES);
        $hiddenRoles = $dto->getCustomOption(ExportFieldOption::HIDDEN_ROLES);

        if (\is_array($visibleRoles) && [] !== $visibleRoles) {
            foreach ($roles as $role) {
                if (\in_array($role, $visibleRoles, true)) {
                    return true;
                }
            }

            return false;
        }

        if (\is_array($hiddenRoles) && [] !== $hiddenRoles) {
            foreach ($roles as $role) {
                if (\in_array($role, $hiddenRoles, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Determines whether a field should be visible for the given format and roles.
     *
     * @param list<string> $roles
     */
    public function isVisible(ExportFieldDto $dto, string $format, array $roles = []): bool
    {
        $format = $this->normalizeFormat($format);
        $roles = $this->normalizeRoles($roles);

        return $this->isVisibleForFormat($dto, $format) && $this->isVisibleForRoles($dto, $roles);
    }

    /**
     * Resolves the exported header label for the given field, format and roles.
     *
     * Resolution order:
     * 1. Role-specific label
     * 2. Format-specific label
     * 3. Default field label
     * 4. Field property name
     *
     * @param list<string> $roles
     */
    public function resolveHeader(ExportFieldDto $dto, string $format, array $roles = []): string
    {
        $format = $this->normalizeFormat($format);
        $roles = $this->normalizeRoles($roles);
        $roleLabels = $dto->getCustomOption(ExportFieldOption::ROLE_LABELS);

        if (\is_array($roleLabels) && [] !== $roleLabels) {
            foreach ($roles as $role) {
                if (\array_key_exists($role, $roleLabels)) {
                    $label = ValueStringifier::stringify($roleLabels[$role]);

                    if ('' !== trim($label)) {
                        return $label;
                    }
                }
            }
        }
        $formatLabels = $dto->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        if (\is_array($formatLabels) && \array_key_exists($format, $formatLabels)) {
            $label = ValueStringifier::stringify($formatLabels[$format]);

            if ('' !== trim($label)) {
                return $label;
            }
        }

        $label = $dto->getLabel();
        $property = $dto->getProperty();
        $normalizedLabel = false !== $label && null !== $label ? ValueStringifier::stringify($label) : '';

        if ('' === trim($normalizedLabel)) {
            return $property ?? '';
        }

        return $normalizedLabel;
    }

    /**
     * Normalizes an export format name.
     */
    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }

    /**
     * Normalizes security roles by trimming, uppercasing and deduplicating them.
     *
     * @param list<string> $roles
     *
     * @return list<string>
     */
    private function normalizeRoles(array $roles): array
    {
        $normalizedRoles = [];

        foreach ($roles as $role) {
            $normalizedRole = strtoupper(trim($role));

            if ('' === $normalizedRole) {
                continue;
            }

            if (!\in_array($normalizedRole, $normalizedRoles, true)) {
                $normalizedRoles[] = $normalizedRole;
            }
        }

        return $normalizedRoles;
    }
}
