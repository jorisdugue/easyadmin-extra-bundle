<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

use DateTimeImmutable;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ExportContext
{
    public function __construct(
        public string $format,
        public string $scope,
        public DateTimeImmutable $generatedAt,
        public ?UserInterface $user,
        public string $entityName,
        public array $roles = [],
    ) {}
}
