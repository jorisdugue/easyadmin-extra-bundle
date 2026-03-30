<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportFieldDto;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

final class ExportFieldFormatResolver
{
    public function isVisible(ExportFieldDto $dto, string $format): bool
    {
        $format = $this->normalizeFormat($format);

        $visibleFormats = $dto->getCustomOption(ExportFieldOption::VISIBLE_FORMATS);
        $hiddenFormats = $dto->getCustomOption(ExportFieldOption::HIDDEN_FORMATS);

        if (\is_array($visibleFormats) && [] !== $visibleFormats) {
            return \in_array($format, $visibleFormats, true);
        }

        if (\is_array($hiddenFormats) && [] !== $hiddenFormats) {
            return !\in_array($format, $hiddenFormats, true);
        }

        return true;
    }

    public function resolveHeader(ExportFieldDto $dto, string $format): string
    {
        $format = $this->normalizeFormat($format);

        $formatLabels = $dto->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        if (\is_array($formatLabels) && \array_key_exists($format, $formatLabels)) {
            $label = $formatLabels[$format];

            if (null !== $label && '' !== trim((string) $label)) {
                return (string) $label;
            }
        }

        $label = $dto->getLabel();
        $property = $dto->getProperty();

        if (false === $label || null === $label || '' === trim((string) $label)) {
            return $property ?? '';
        }

        return $label;
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
