<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use Closure;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportFieldDto;

trait ImportFieldTrait
{
    private ImportFieldDto $dto;

    private function __construct()
    {
        $this->dto = new ImportFieldDto();
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

    public function setLabel(?string $label): static
    {
        $this->dto->setLabel($label);

        return $this;
    }

    public function required(bool $required = true): static
    {
        $this->dto->setRequired($required);

        return $this;
    }

    public function optional(): static
    {
        return $this->required(false);
    }

    public function position(?int $position = null): static
    {
        if (null !== $position && $position < 1) {
            throw new InvalidArgumentException('Import field position must be greater than or equal to 1.');
        }

        $this->dto->setPosition($position);

        return $this;
    }

    public function transformUsing(callable $transformer): static
    {
        $this->dto->setTransformer(
            $transformer instanceof Closure
                ? $transformer
                : Closure::fromCallable($transformer),
        );

        return $this;
    }

    public function setCustomOption(string $name, mixed $value): static
    {
        $this->dto->setCustomOption($name, $value);

        return $this;
    }

    public function getAsDto(): ImportFieldDto
    {
        return $this->dto;
    }
}
