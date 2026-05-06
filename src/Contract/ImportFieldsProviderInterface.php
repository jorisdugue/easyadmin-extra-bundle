<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

interface ImportFieldsProviderInterface
{
    /**
     * @return list<ImportFieldInterface>
     */
    public static function getImportFields(?string $importSet = null): array;
}
