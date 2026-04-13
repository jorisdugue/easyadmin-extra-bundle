<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;

interface ExportSetMetadataProviderInterface
{
    /**
     * @return list<ExportSetMetadata>
     */
    public static function getExportSetMetadata(): array;
}
