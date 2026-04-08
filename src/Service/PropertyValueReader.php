<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service;

use BackedEnum;
use DateTimeInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportPropertyException;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;
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

    /**
     * Reads the raw property value from the given entity using the field property path.
     *
     * @throws InvalidExportPropertyException When the property path is missing or unreadable
     */
    public function read(object $entity, ExportFieldInterface $field): mixed
    {
        $dto = $field->getAsDto();
        $propertyPath = $dto->getProperty();
        $fieldLabel = $this->resolveFieldLabel($field, $propertyPath);
        if (null === $propertyPath || '' === trim($propertyPath)) {
            throw InvalidExportPropertyException::missingPropertyPath($fieldLabel);
        }

        try {
            return $this->propertyAccessor->getValue($entity, $propertyPath);
        } catch (Throwable $e) {
            throw InvalidExportPropertyException::unreadableProperty($propertyPath, $fieldLabel, $entity::class, $e);
        }
    }

    /**
     * Normalizes an arbitrary value into a string representation suitable for export output.
     *
     * Normalization rules:
     * - null => empty string
     * - DateTimeInterface => Y-m-d H:i:s
     * - BackedEnum => backed value
     * - UnitEnum => enum case name
     * - Stringable/scalar => string cast
     * - iterable => recursively normalized and joined with ", "
     * - unsupported values => empty string
     */
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

        if ($value instanceof Stringable || \is_scalar($value)) {
            return ValueStringifier::stringify($value);
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

    /**
     * Resolves a safe field label for exception messages.
     *
     * Falls back to the property path when the configured label is missing or empty,
     * and finally to "[unnamed]" when no better label is available.
     */
    private function resolveFieldLabel(ExportFieldInterface $field, ?string $propertyPath): string
    {
        $label = $field->getAsDto()->getLabel();

        if (\is_string($label) && '' !== trim($label)) {
            return $label;
        }

        if (null !== $propertyPath && '' !== trim($propertyPath)) {
            return $propertyPath;
        }

        return '[unnamed]';
    }
}
