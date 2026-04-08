<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

final class ExportLimitExceededException extends EasyAdminExtraException
{
    public static function maxRowsExceeded(int $maxRows, int $currentRowCount): self
    {
        return new self(sprintf(
            'The export exceeded the configured maximum number of rows (%d). Processed rows: %d.',
            $maxRows,
            $currentRowCount,
        ));
    }
}