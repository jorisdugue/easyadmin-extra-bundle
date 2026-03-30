<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use Closure;

final class ExportFieldDto
{
    private ?string $fieldFqcn = null;
    private ?string $property = null;
    private string|false|null $label = null;
    private bool $enabled = true;
    private ?Closure $transformer = null;
    private mixed $default = null;

    /**
     * @var array<string, mixed>
     */
    private array $customOptions = [];

    public function __clone(): void
    {
        // Intentionally left blank.
        // This method exists to make future deep-clone adjustments explicit.
    }

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

    public function getLabel(): false|string|null
    {
        return $this->label;
    }

    public function setLabel(false|string|null $label): void
    {
        $this->label = $label;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getTransformer(): ?Closure
    {
        return $this->transformer;
    }

    public function setTransformer(?Closure $transformer): void
    {
        $this->transformer = $transformer;
    }

    public function getDefault(): mixed
    {
        return $this->default;
    }

    public function setDefault(mixed $default): void
    {
        $this->default = $default;
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

    /**
     * @param array<string, mixed> $customOptions
     */
    public function setCustomOptions(array $customOptions): void
    {
        $this->customOptions = $customOptions;
    }
}
