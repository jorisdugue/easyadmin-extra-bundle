<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ExportSetMetadata
{
    /**
     * @param list<string> $requiredRoles
     */
    public function __construct(
        private string $name,
        private ?string $label = null,
        private array $requiredRoles = [],
    ) {}

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
}
