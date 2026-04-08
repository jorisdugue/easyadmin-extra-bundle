<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;

trait ExportFieldMaskTrait
{
    /**
     * Registers a value transformer for the field
     */
    abstract public function setTransformer(callable $callback): static;

    /**
     * Replace the exported value with a fixed replacement string.
     *
     * When $preserveNull is true, null values remain null instead of being replaced.
     */
    public function mask(string $replacement = '***', bool $preserveNull = true): static
    {
        return $this->setTransformer(
            static function (
                mixed $value,
                object $entity,
                ExportFieldInterface $field,
            ) use ($replacement, $preserveNull): ?string {
                if (null === $value && $preserveNull) {
                    return null;
                }

                return $replacement;
            },
        );
    }

    /**
     * Replaces the exported value with a fixed replacement string when the given condition returns true.
     *
     * Expected condition signature:
     * callable(mixed $value, object $entity, ExportFieldInterface $field): bool
     *
     * When $preserveNull is true, null values remain null instead of being replaced.
     */
    public function maskIf(
        callable $condition,
        string $replacement = '***',
        bool $preserveNull = true,
    ): static {
        return $this->setTransformer(
            static function (
                mixed $value,
                object $entity,
                ExportFieldInterface $field,
            ) use ($condition, $replacement, $preserveNull): mixed {
                if (!$condition($value, $entity, $field)) {
                    return $value;
                }

                if (null === $value && $preserveNull) {
                    return null;
                }

                return $replacement;
            },
        );
    }

    /**
     * Replaces the exported value with a redacted placeholder.
     */
    public function redact(string $replacement = '[REDACTED]'): static
    {
        return $this->mask($replacement);
    }

    /**
     * Replaces the exported value with a redacted placeholder when the given condition returns true.
     */
    public function redactIf(
        callable $condition,
        string $replacement = '[REDACTED]',
        bool $preserveNull = true,
    ): static {
        return $this->maskIf($condition, $replacement, $preserveNull);
    }

    /**
     * Masks part of the exported value while keeping a configurable number of characters visible.
     *
     * The resulting value keeps the first $visibleStart characters and the last $visibleEnd characters,
     * while replacing the middle part with repeated $maskCharacter characters.
     *
     * Examples:
     * - partialMask(0, 2) on "secret@example.com" => "****************om"
     * - partialMask(2, 2) on "secret@example.com" => "se**************om"
     * - partialMask(0, 0) on "secret" => "******"
     *
     * When $preserveNull is true, null values remain null.
     * Non-stringable values are converted to an empty string.
     *
     * @throws InvalidArgumentException when $visibleStart or $visibleEnd is negative
     *                                  or when $maskCharacter is empty
     */
    public function partialMask(
        int $visibleStart = 0,
        int $visibleEnd = 2,
        string $maskCharacter = '*',
        bool $preserveNull = true,
    ): static {
        if ($visibleStart < 0) {
            throw new InvalidArgumentException('The "visibleStart" value must be greater than or equal to 0.');
        }

        if ($visibleEnd < 0) {
            throw new InvalidArgumentException('The "visibleEnd" value must be greater than or equal to 0.');
        }

        if ('' === $maskCharacter) {
            throw new InvalidArgumentException('The "maskCharacter" value cannot be empty.');
        }

        return $this->setTransformer(
            static function (
                mixed $value,
                object $entity,
                ExportFieldInterface $field,
            ) use ($visibleStart, $visibleEnd, $maskCharacter, $preserveNull): ?string {
                if (null === $value && $preserveNull) {
                    return null;
                }

                $string = ValueStringifier::stringify($value);
                $length = mb_strlen($string);

                if (0 === $length) {
                    return '';
                }

                if (0 === $visibleStart && 0 === $visibleEnd) {
                    return str_repeat($maskCharacter, $length);
                }

                if (($visibleStart + $visibleEnd) >= $length) {
                    return str_repeat($maskCharacter, $length);
                }

                $start = $visibleStart > 0 ? mb_substr($string, 0, $visibleStart) : '';
                $end = $visibleEnd > 0 ? mb_substr($string, -$visibleEnd) : '';
                $maskedLength = $length - $visibleStart - $visibleEnd;

                return $start . str_repeat($maskCharacter, $maskedLength) . $end;
            },
        );
    }
}
