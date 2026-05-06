<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;

final readonly class ImportFieldHeaderResolver
{
    public function resolve(ImportFieldInterface $field): string
    {
        $dto = $field->getAsDto();
        $property = $dto->getProperty();

        if (!\is_string($property) || '' === trim($property)) {
            throw InvalidImportConfigurationException::missingFieldProperty((string) ($dto->getLabel() ?? ''));
        }

        $label = $dto->getLabel();

        return \is_string($label) && '' !== trim($label) ? $label : $property;
    }
}
