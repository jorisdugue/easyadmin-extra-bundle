<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use DateTimeImmutable;

final readonly class ExportContext
{
    public function __construct(
        public string $format,
        public string $scope,
        public DateTimeImmutable $generatedAt,
        public ?object $user,
        public string $entityName,
    ) {}
}
