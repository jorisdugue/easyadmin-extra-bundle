<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;

trait ExportFieldMaskTrait
{
    abstract public function setTransformer(callable $callback): static;

    /**
     * Replace the exported value with a fixed replacement string.
     */
    public function mask(string $replacement = '***', bool $preserveNull = true): static
    {
        return $this->setTransformer(
            static function (
                mixed $value,
                object $entity,
                ExportFieldInterface $field,
            ) use ($replacement, $preserveNull): mixed {
                if (null === $value && $preserveNull) {
                    return null;
                }

                return $replacement;
            }
        );
    }

    /**
     * Conditionally masks the exported value.
     *
     * Expected condition signature:
     * callable(mixed $value, object $entity, ExportFieldInterface $field): bool
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
            }
        );
    }

    /**
     * Redacts the exported value using a fixed replacement string.
     */
    public function redact(string $replacement = '[REDACTED]'): static
    {
        return $this->mask($replacement);
    }

    /**
     * Conditionally redacts the exported value using a fixed replacement string.
     */
    public function redactIf(
        callable $condition,
        string $replacement = '[REDACTED]',
        bool $preserveNull = true,
    ): static {
        return $this->maskIf($condition, $replacement, $preserveNull);
    }

    /**
     * Partially masks the exported value.
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
            ) use ($visibleStart, $visibleEnd, $maskCharacter, $preserveNull): mixed {
                if (null === $value && $preserveNull) {
                    return null;
                }

                if (null === $value) {
                    return null;
                }

                $string = (string) $value;
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
            }
        );
    }
}
