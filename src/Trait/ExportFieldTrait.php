<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use Closure;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;

trait ExportFieldTrait
{
    private ExportFieldDto $dto;

    private function __construct()
    {
        $this->dto = new ExportFieldDto();
    }

    public function __clone(): void
    {
        $this->dto = clone $this->dto;
    }

    public function setFieldFqcn(string $fieldFqcn): static
    {
        $this->dto->setFieldFqcn($fieldFqcn);

        return $this;
    }

    public function setProperty(string $propertyName): static
    {
        $this->dto->setProperty($propertyName);

        return $this;
    }

    public function setLabel(string|false|null $label): static
    {
        $this->dto->setLabel($label);

        return $this;
    }

    public function hideLabel(): static
    {
        $this->dto->setLabel(false);

        return $this;
    }

    public function setTransformer(callable $callback): static
    {
        $this->dto->setTransformer(
            $callback instanceof Closure
                ? $callback
                : Closure::fromCallable($callback)
        );

        return $this;
    }

    public function setDefault(mixed $value): static
    {
        $this->dto->setDefault($value);

        return $this;
    }

    public function setDisabled(bool $disabled = true): static
    {
        $this->dto->setEnabled(!$disabled);

        return $this;
    }

    public function setEnabled(bool $enabled = true): static
    {
        $this->dto->setEnabled($enabled);

        return $this;
    }

    public function setCustomOption(string $name, mixed $value): static
    {
        $this->dto->setCustomOption($name, $value);

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setCustomOptions(array $options): static
    {
        foreach ($options as $name => $value) {
            $this->dto->setCustomOption($name, $value);
        }

        return $this;
    }

    public function getAsDto(): ExportFieldDto
    {
        return $this->dto;
    }
}
