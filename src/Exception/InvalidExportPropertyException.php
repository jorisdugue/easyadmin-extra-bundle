<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

use Throwable;

final class InvalidExportPropertyException extends EasyAdminExtraException
{
    public static function missingPropertyPath(string $fieldLabel): self
    {
        return new self(\sprintf(
            'The export field "%s" does not define any readable property path.',
            $fieldLabel,
        ));
    }

    public static function unreadableProperty(
        string $propertyPath,
        string $fieldLabel,
        string $entityClass,
        ?Throwable $previous = null,
    ): self {
        return new self(\sprintf(
            'Unable to read property path "%s" for export field "%s" on entity "%s".',
            $propertyPath,
            $fieldLabel,
            $entityClass,
        ), 0, $previous);
    }
}
