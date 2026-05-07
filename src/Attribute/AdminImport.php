<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AdminImport
{
    public function __construct(
        public ?string $routeName = null,
        public ?string $routePath = null,
    ) {}
}
