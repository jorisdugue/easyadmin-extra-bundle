<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Event\Export;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

final class AfterExportEvent extends Event
{
    public function __construct(
        private readonly ExportContext $context,
        private readonly ExportPayload $payload,
        private readonly Response $response,
    ) {}

    public function getContext(): ExportContext
    {
        return $this->context;
    }

    public function getPayload(): ExportPayload
    {
        return $this->payload;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }
}
