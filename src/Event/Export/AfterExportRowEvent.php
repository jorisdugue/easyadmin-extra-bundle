<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Event\Export;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterExportRowEvent extends Event
{
    /**
     * @param list<mixed> $row
     * @param list<string> $properties
     */
    public function __construct(
        private readonly ExportContext $context,
        private readonly object $entity,
        private readonly array $row,
        private readonly array $properties,
    ) {}

    public function getContext(): ExportContext
    {
        return $this->context;
    }

    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return list<mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @return list<string>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
