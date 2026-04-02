<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

/**
 * Provides helpers to control field visibility and labeling per security role.
 *
 * Behavior:
 * - If visible roles are set, the field is only shown for those roles
 * - If hidden roles are set, the field is excluded for those roles
 * - If both are set, hidden roles take precedence
 */
trait ExportFieldRoleTrait
{
    abstract public function setCustomOption(string $name, mixed $value): static;

    abstract public function getAsDto(): ExportFieldDto;

    /**
     * Restricts the field to the given roles only.
     *
     * @param list<string> $roles
     */
    public function onlyForRoles(array $roles): static
    {
        return $this->setCustomOption(
            ExportFieldOption::VISIBLE_ROLES,
            $this->normalizeRoles($roles)
        );
    }

    /**
     * Restricts the field to a single role only.
     */
    public function onlyForRole(string $role): static
    {
        return $this->onlyForRoles([$role]);
    }

    /**
     * Hides the field for the given roles.
     *
     * @param list<string> $roles
     */
    public function hideForRoles(array $roles): static
    {
        return $this->setCustomOption(
            ExportFieldOption::HIDDEN_ROLES,
            $this->normalizeRoles($roles)
        );
    }

    /**
     * Hides the field for a single role.
     */
    public function hideForRole(string $role): static
    {
        return $this->hideForRoles([$role]);
    }

    /**
     * Alias for onlyForRole().
     */
    public function showForRole(string $role): static
    {
        return $this->onlyForRole($role);
    }

    /**
     * Alias for onlyForRoles().
     *
     * @param list<string> $roles
     */
    public function showForRoles(array $roles): static
    {
        return $this->onlyForRoles($roles);
    }

    /**
     * Defines a custom label for a specific role.
     */
    public function setLabelForRole(string $role, string $label): static
    {
        $role = $this->normalizeRole($role);

        /** @var array<string, string>|mixed $labels */
        $labels = $this->getAsDto()->getCustomOption(ExportFieldOption::ROLE_LABELS);

        if (!\is_array($labels)) {
            $labels = [];
        }

        $labels[$role] = $label;

        return $this->setCustomOption(ExportFieldOption::ROLE_LABELS, $labels);
    }

    /**
     * Defines multiple role-specific labels.
     *
     * @param array<string, string> $labels
     */
    public function setLabelsForRoles(array $labels): static
    {
        $field = $this;

        foreach ($labels as $role => $label) {
            $field = $field->setLabelForRole($role, $label);
        }

        return $field;
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
            $normalizedRole = $this->normalizeRole($role);

            if (!\in_array($normalizedRole, $normalizedRoles, true)) {
                $normalizedRoles[] = $normalizedRole;
            }
        }

        return $normalizedRoles;
    }

    private function normalizeRole(string $role): string
    {
        return strtoupper(trim($role));
    }
}
