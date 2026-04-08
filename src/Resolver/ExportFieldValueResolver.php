<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use DateTimeInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\BooleanExportField;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use JorisDugue\EasyAdminExtraBundle\Field\IntegerExportField;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\NumberExportField;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;

readonly class ExportFieldValueResolver
{
    public function __construct(
        private PropertyValueReader $propertyValueReader,
    ) {}

    /**
     * Resolves the final exported string value for a field.
     *
     * Resolution pipeline:
     * 1. Read the raw property value from the entity
     * 2. Apply the custom field transformer when configured
     * 3. Apply the field default value when the resolved value is null
     * 4. Apply field-specific formatting
     * 5. Normalize the result to a string
     */
    public function resolve(object $entity, ExportFieldInterface $field): string
    {
        $dto = $field->getAsDto();

        $value = $this->propertyValueReader->read($entity, $field);

        if (null !== $dto->getTransformer()) {
            $value = ($dto->getTransformer())($value, $entity, $field);
        }

        if (null === $value && null !== $dto->getDefault()) {
            $value = $dto->getDefault();
        }

        $value = $this->formatValue($value, $dto);

        return $this->propertyValueReader->normalize($value);
    }

    /**
     * Applies field-type-specific formatting.
     */
    private function formatValue(mixed $value, ExportFieldDto $dto): mixed
    {
        return match ($dto->getFieldFqcn()) {
            BooleanExportField::class => $this->formatBoolean($value, $dto),
            ChoiceExportField::class => $this->formatChoice($value, $dto),
            DateExportField::class => $this->formatDate($value, $dto),
            DateTimeExportField::class => $this->formatDateTime($value, $dto),
            NumberExportField::class => $this->formatNumber($value, $dto),
            IntegerExportField::class => $this->formatInteger($value, $dto),
            MoneyExportField::class => $this->formatMoney($value, $dto),
            default => $value,
        };
    }

    /**
     * Formats a boolean-like value using the configured true/false labels.
     */
    private function formatBoolean(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value
            ? $dto->getCustomOption(BooleanExportField::OPTION_TRUE_LABEL)
            : $dto->getCustomOption(BooleanExportField::OPTION_FALSE_LABEL);
    }

    /**
     * Formats a choice value using the configured choice map.
     */
    private function formatChoice(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        $choices = $dto->getCustomOption(ChoiceExportField::OPTION_CHOICES) ?? [];
        if (!\is_array($choices)) {
            return $value;
        }

        $choiceKey = \is_int($value) || \is_string($value) ? $value : null;

        if (null === $choiceKey) {
            return $value;
        }

        return $choices[$choiceKey] ?? $value;
    }

    /**
     * Formats a date value using the configured date format.
     */
    private function formatDate(mixed $value, ExportFieldDto $dto): mixed
    {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        $format = $dto->getCustomOption(DateExportField::OPTION_FORMAT);
        $format = \is_string($format) && '' !== trim($format) ? $format : 'Y-m-d';

        return $value->format($format);
    }

    /**
     * Formats a date-time value using the configured date-time format.
     */
    private function formatDateTime(mixed $value, ExportFieldDto $dto): mixed
    {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        $format = $dto->getCustomOption(DateTimeExportField::OPTION_FORMAT);
        $format = \is_string($format) && '' !== trim($format) ? $format : 'Y-m-d H:i:s';

        return $value->format($format);
    }

    /**
     * Formats a numeric value using the configured decimal precision and separators.
     */
    private function formatNumber(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value) && !\is_float($value) && !(\is_string($value) && is_numeric($value))) {
            return $value;
        }

        $decimals = $dto->getCustomOption(NumberExportField::OPTION_DECIMALS);
        $decimalSeparator = $dto->getCustomOption(NumberExportField::OPTION_DECIMAL_SEPARATOR);
        $thousandsSeparator = $dto->getCustomOption(NumberExportField::OPTION_THOUSANDS_SEPARATOR);

        return number_format(
            (float) $value,
            \is_int($decimals) ? $decimals : 2,
            \is_string($decimalSeparator) ? $decimalSeparator : '.',
            \is_string($thousandsSeparator) ? $thousandsSeparator : '',
        );
    }

    /**
     * Formats an integer value using the configured thousands separator.
     */
    private function formatInteger(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value) && !(\is_string($value) && preg_match('/^-?\d+$/', $value))) {
            return $value;
        }

        $thousandsSeparator = $dto->getCustomOption(IntegerExportField::OPTION_THOUSANDS_SEPARATOR);

        return number_format(
            (int) $value,
            0,
            '.',
            \is_string($thousandsSeparator) ? $thousandsSeparator : '',
        );
    }

    /**
     * Formats a monetary value using the configured precision, separators and symbol options.
     */
    private function formatMoney(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value) && !\is_float($value) && !(\is_string($value) && is_numeric($value))) {
            return $value;
        }

        $storedAsCents = true === $dto->getCustomOption(MoneyExportField::OPTION_STORED_AS_CENTS);
        $decimals = $dto->getCustomOption(MoneyExportField::OPTION_DECIMALS);
        $decimalSeparator = $dto->getCustomOption(MoneyExportField::OPTION_DECIMAL_SEPARATOR);
        $thousandsSeparator = $dto->getCustomOption(MoneyExportField::OPTION_THOUSANDS_SEPARATOR);
        $symbol = $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL);
        $symbolPosition = $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_POSITION);
        $symbolSpacing = $dto->getCustomOption(MoneyExportField::OPTION_SYMBOL_SPACING);

        $amount = (float) $value;

        if ($storedAsCents) {
            $amount /= 100;
        }

        $formatted = number_format(
            $amount,
            \is_int($decimals) ? $decimals : 2,
            \is_string($decimalSeparator) ? $decimalSeparator : ',',
            \is_string($thousandsSeparator) ? $thousandsSeparator : ' ',
        );

        if (!\is_string($symbol) || '' === $symbol) {
            return $formatted;
        }

        $space = $symbolSpacing ? ' ' : '';

        if (MoneyExportField::SYMBOL_POSITION_PREFIX === $symbolPosition) {
            return $symbol . $space . $formatted;
        }

        return $formatted . $space . $symbol;
    }
}
