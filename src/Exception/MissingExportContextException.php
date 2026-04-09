<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

class MissingExportContextException extends EasyAdminExtraException
{
    public static function missingAdminContext(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin admin context is available.',
        );
    }

    public static function missingCrudContext(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin CRUD context is available in the current admin context.',
        );
    }

    public static function missingRequest(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin admin context is available on the current request attributes (EA::CONTEXT_REQUEST_ATTRIBUTE).',
        );
    }

    public static function missingSearchDto(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin SearchDto is available.',
        );
    }
}
