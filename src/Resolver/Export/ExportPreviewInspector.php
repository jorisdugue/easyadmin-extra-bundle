<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Export;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;

final readonly class ExportPreviewInspector
{
    public function hasFormatSpecificPreviewVariants(ExportConfig $config): bool
    {
        if (\count($config->formats) < 2) {
            return false;
        }

        foreach ($config->fields as $field) {
            if ($this->fieldHasFormatSpecificPreviewVariant($field)) {
                return true;
            }
        }

        return false;
    }

    private function fieldHasFormatSpecificPreviewVariant(ExportFieldInterface $field): bool
    {
        $dto = $field->getAsDto();

        $visibleFormats = $dto->getCustomOption(ExportFieldOption::VISIBLE_FORMATS);
        if (\is_array($visibleFormats) && [] !== $visibleFormats) {
            return true;
        }

        $hiddenFormats = $dto->getCustomOption(ExportFieldOption::HIDDEN_FORMATS);
        if (\is_array($hiddenFormats) && [] !== $hiddenFormats) {
            return true;
        }

        $formatLabels = $dto->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        return \is_array($formatLabels) && [] !== $formatLabels;
    }
}
