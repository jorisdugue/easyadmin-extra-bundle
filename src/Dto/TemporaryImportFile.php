<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class TemporaryImportFile
{
    public function __construct(
        public string $token,
        public string $path,
        public string $clientFilename,
        public string $crudControllerFqcn,
        public string $separator,
        public string $encoding,
        public bool $firstRowContainsHeaders,
        public int $size,
        public string $sha256,
    ) {}
}
