<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Event\Export;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use Symfony\Contracts\EventDispatcher\Event;

final class BeforeExportEvent extends Event
{
    public function __construct(
        private readonly ExportContext $context,
        private readonly ExportPayload $payload,
    ) {}

    public function getContext(): ExportContext
    {
        return $this->context;
    }

    public function getPayload(): ExportPayload
    {
        return $this->payload;
    }
}
