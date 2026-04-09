<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto\Operation;

/**
 * Represents an explicit selection of entity identifiers.
 */
final readonly class EntitySelection
{
    /**
     * @param list<int|string> $ids
     */
    public function __construct(public array $ids) {}
}
