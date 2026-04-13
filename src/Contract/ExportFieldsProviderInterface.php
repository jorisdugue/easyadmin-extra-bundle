<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

interface ExportFieldsProviderInterface
{
    /**
     * @return list<ExportFieldInterface>
     */
    public static function getExportFields(?string $exportSet = null): array;
}
