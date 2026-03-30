<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Trait;

use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

/**
 * Provides helpers to control field visibility and labeling per export format.
 *
 * Supported formats are defined in {@see ExportFormat}.
 *
 * Behavior:
 * - If visible formats are set, the field is only shown on those formats
 * - If hidden formats are set, the field is excluded from those formats
 * - If both are set, hidden formats take precedence
 */
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
            ExportFormat::normalizeMany($formats)
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
            ExportFormat::normalizeMany($formats)
        );
    }

    /**
     * Restricts the field to a single export format.
     */
    public function onlyOnFormat(string $format): static
    {
        return $this->onlyOnFormats([$format]);
    }

    /**
     * Hides the field on a single export format.
     */
    public function hideOnFormat(string $format): static
    {
        return $this->hideOnFormats([$format]);
    }

    /**
     * Alias for onlyOnFormat(), improves readability in some contexts.
     */
    public function showOnFormat(string $format): static
    {
        return $this->onlyOnFormat($format);
    }

    /**
     * Alias for onlyOnFormats(), improves readability in some contexts.
     *
     * @param list<string> $formats
     */
    public function showOnFormats(array $formats): static
    {
        return $this->onlyOnFormats($formats);
    }

    /**
     * Defines a custom label for a specific export format.
     */
    public function setLabelForFormat(string $format, string $label): static
    {
        $format = ExportFormat::normalize($format);

        /** @var array<string, string>|mixed $labels */
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
        $field = $this;

        foreach ($labels as $format => $label) {
            $field = $field->setLabelForFormat($format, $label);
        }

        return $field;
    }
}
