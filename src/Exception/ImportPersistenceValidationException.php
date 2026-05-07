<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exception;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportRowResult;
use RuntimeException;

final class ImportPersistenceValidationException extends RuntimeException
{
    /**
     * @param list<ImportRowResult> $rowResults
     */
    public function __construct(public readonly array $rowResults)
    {
        parent::__construct('Imported rows failed Doctrine metadata validation.');
    }
}
