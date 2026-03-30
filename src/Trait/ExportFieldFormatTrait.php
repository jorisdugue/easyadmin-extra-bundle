<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

trait ExportFieldFormatTrait
{
    abstract public function setCustomOption(string $name, mixed $value): static;

    abstract public function getAsDto(): ExportFieldDto;

    /**
     * Restricts the field to the given export formats only.
     *
     * @param list<string> $formats
     */
    public function onlyOnFormats(array $formats): static
    {
        return $this->setCustomOption(
            ExportFieldOption::VISIBLE_FORMATS,
            $this->normalizeFormats($formats)
        );
    }

    /**
     * Hides the field on the given export formats.
     *
     * @param list<string> $formats
     */
    public function hideOnFormats(array $formats): static
    {
        return $this->setCustomOption(
            ExportFieldOption::HIDDEN_FORMATS,
            $this->normalizeFormats($formats)
        );
    }

    /**
     * Adds a single allowed export format.
     */
    public function onlyOnFormat(string $format): static
    {
        return $this->onlyOnFormats([$format]);
    }

    /**
     * Adds a single hidden export format.
     */
    public function hideOnFormat(string $format): static
    {
        return $this->hideOnFormats([$format]);
    }

    /**
     * Alias for onlyOnFormat(), useful for fluent readability.
     */
    public function showOnFormat(string $format): static
    {
        return $this->onlyOnFormat($format);
    }

    /**
     * Defines a custom label for a specific export format.
     */
    public function setLabelForFormat(string $format, string $label): static
    {
        $format = $this->normalizeFormat($format);

        $labels = $this->getAsDto()->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        if (!\is_array($labels)) {
            $labels = [];
        }

        $labels[$format] = $label;

        return $this->setCustomOption(ExportFieldOption::FORMAT_LABELS, $labels);
    }

    /**
     * Defines multiple format-specific labels.
     *
     * @param array<string, string> $labels
     */
    public function setLabelsForFormats(array $labels): static
    {
        return array_reduce(
            array_keys($labels),
            static fn ($field, $format) => $field->setLabelForFormat($format, $labels[$format]),
            $this
        );
    }

    /**
     * @param list<string> $formats
     *
     * @return list<string>
     */
    private function normalizeFormats(array $formats): array
    {
        $normalized = [];

        foreach ($formats as $format) {
            $normalized[] = $this->normalizeFormat($format);
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeFormat(string $format): string
    {
        $format = strtolower(trim($format));

        if ('' === $format) {
            throw new InvalidArgumentException('Export format cannot be empty.');
        }

        return $format;
    }
}
