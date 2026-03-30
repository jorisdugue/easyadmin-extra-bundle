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

    private function formatBoolean(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        return (bool) $value
            ? $dto->getCustomOption(BooleanExportField::OPTION_TRUE_LABEL)
            : $dto->getCustomOption(BooleanExportField::OPTION_FALSE_LABEL);
    }

    private function formatChoice(mixed $value, ExportFieldDto $dto): mixed
    {
        if (null === $value) {
            return null;
        }

        $choices = $dto->getCustomOption(ChoiceExportField::OPTION_CHOICES) ?? [];

        return $choices[$value] ?? $value;
    }

    private function formatDate(mixed $value, ExportFieldDto $dto): mixed
    {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        $format = $dto->getCustomOption(DateExportField::OPTION_FORMAT);

        return $value->format($format);
    }

    private function formatDateTime(mixed $value, ExportFieldDto $dto): mixed
    {
        if (!$value instanceof DateTimeInterface) {
            return $value;
        }

        $format = $dto->getCustomOption(DateTimeExportField::OPTION_FORMAT);

        return $value->format($format);
    }

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
            \is_string($thousandsSeparator) ? $thousandsSeparator : ''
        );
    }

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
            \is_string($thousandsSeparator) ? $thousandsSeparator : ''
        );
    }

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
            \is_string($thousandsSeparator) ? $thousandsSeparator : ' '
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
