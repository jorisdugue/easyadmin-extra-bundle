<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service;

use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;

/**
 * Kept here only if you prefer a dedicated test helper namespace later.
 */
final class PropertyValueReader
{
    public function read(object $entity, ExportFieldInterface $field): mixed
    {
        return $entity->{$field->getAsDto()->getProperty()};
    }

    public function normalize(mixed $value): string
    {
        return null === $value ? '' : (string) $value;
    }
}
