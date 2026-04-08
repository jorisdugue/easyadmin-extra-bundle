<?php

namespace JorisDugue\EasyAdminExtraBundle\Exception;

class MissingExportContextException extends EasyAdminExtraException
{
    public static function missingAdminContext(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin admin context is available.'
        );
    }

    public static function missingCrudContext(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin CRUD context is available.'
        );
    }

    public static function missingRequest(): self
    {
        return new self(
            'Unable to build the export because no request is available in the current EasyAdmin context.'
        );
    }

    public static function missingSearchDto(): self
    {
        return new self(
            'Unable to build the export because no EasyAdmin SearchDto is available.'
        );
    }
}
