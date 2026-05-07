<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Contract;

use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportPreview;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportReadOptions;
use Symfony\Component\HttpFoundation\File\UploadedFile;

interface ImportReaderInterface
{
    public function getFormat(): string;

    public function supports(string $format): bool;

    public function createEmptyPreview(): ImportPreview;

    public function createErrorPreview(string $message): ImportPreview;

    public function read(?UploadedFile $file, ImportReadOptions $options, ?ImportConfig $importConfig = null): ImportPreview;
}
