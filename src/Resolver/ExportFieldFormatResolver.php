<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

final class ExportFieldFormatResolver
{
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
     * @param list<string> $roles
     */
    public function isVisible(ExportFieldDto $dto, string $format, array $roles = []): bool
    {
        $format = $this->normalizeFormat($format);
        $roles = $this->normalizeRoles($roles);

        return $this->isVisibleForFormat($dto, $format)
            && $this->isVisibleForRoles($dto, $roles);
    }

    /**
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
                    $label = $roleLabels[$role];

                    if (null !== $label && '' !== trim((string) $label)) {
                        return (string) $label;
                    }
                }
            }
        }

        $formatLabels = $dto->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        if (\is_array($formatLabels) && \array_key_exists($format, $formatLabels)) {
            $label = $formatLabels[$format];

            if (null !== $label && '' !== trim((string) $label)) {
                return (string) $label;
            }
        }

        $label = $dto->getLabel();
        $property = $dto->getProperty();

        if (false === $label || null === $label || '' === trim((string) $label)) {
            return $property ?? '';
        }

        return $label;
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }

    /**
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
