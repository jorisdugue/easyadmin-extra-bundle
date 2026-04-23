<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use InvalidArgumentException;

final class ExportSetMetadata
{
    /**
     * @var list<string>
     */
    private array $requiredRoles = [];

    /** @param list<string>|string $requiredRoles */
    public function __construct(
        private string $name,
        private ?string $label = null,
        string|array $requiredRoles = [],
    ) {
        $this->requiredRoles = $this->normalizeRequiredRoles($requiredRoles);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /**
     * @return list<string>
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }

    /**
     * @param string|list<string> $requiredRoles
     *
     * @return list<string>
     */
    private function normalizeRequiredRoles(string|array $requiredRoles): array
    {
        if (\is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }

        foreach ($requiredRoles as $requiredRole) {
            if (!\is_string($requiredRole) || '' === trim($requiredRole)) {
                throw new InvalidArgumentException('Export set required roles must be non-empty strings.');
            }
        }

        return array_values($requiredRoles);
    }
}
