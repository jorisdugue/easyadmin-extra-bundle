<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use Closure;

final class ImportFieldDto
{
    private ?string $fieldFqcn = null;
    private ?string $property = null;
    private ?string $label = null;
    private bool $required = false;
    private ?int $position = null;
    private ?Closure $transformer = null;

    /**
     * @var array<string, mixed>
     */
    private array $customOptions = [];

    public function __clone(): void {}

    public function getFieldFqcn(): ?string
    {
        return $this->fieldFqcn;
    }

    public function setFieldFqcn(?string $fieldFqcn): void
    {
        $this->fieldFqcn = $fieldFqcn;
    }

    public function getProperty(): ?string
    {
        return $this->property;
    }

    public function setProperty(?string $property): void
    {
        $this->property = $property;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getTransformer(): ?Closure
    {
        return $this->transformer;
    }

    public function setTransformer(?Closure $transformer): void
    {
        $this->transformer = $transformer;
    }

    public function getCustomOption(string $name): mixed
    {
        return $this->customOptions[$name] ?? null;
    }

    public function setCustomOption(string $name, mixed $value): void
    {
        $this->customOptions[$name] = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }
}
