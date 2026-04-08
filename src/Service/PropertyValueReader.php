<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service;

use BackedEnum;
use DateTimeInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportPropertyException;
use Stringable;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;
use UnitEnum;

final class PropertyValueReader
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
            ->enableMagicCall()
            ->getPropertyAccessor();
    }

    public function read(object $entity, ExportFieldInterface $field): mixed
    {
        $dto = $field->getAsDto();
        $propertyPath = $dto->getProperty();
        if (null === $propertyPath || '' === trim($propertyPath)) {
            throw InvalidExportPropertyException::missingPropertyPath($label ?? '[unnamed]');
        }

        $fieldLabel = $dto->getLabel();

        // Fallback to propertyPath
        if (false === $fieldLabel || null === $fieldLabel || '' === trim((string) $fieldLabel)) {
            $fieldLabel = $propertyPath;
        }

        try {
            return $this->propertyAccessor->getValue($entity, $propertyPath);
        } catch (Throwable $e) {
            throw InvalidExportPropertyException::unreadableProperty(
                $propertyPath,
                $label ?? $propertyPath,
                $entity::class,
                $e,
            );
        }
    }

    public function normalize(mixed $value): string
    {
        if (null === $value) {
            return '';
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (\is_scalar($value)) {
            return (string) $value;
        }

        if (is_iterable($value)) {
            $parts = [];

            foreach ($value as $item) {
                $parts[] = $this->normalize($item);
            }

            return implode(', ', $parts);
        }

        return '';
    }
}
