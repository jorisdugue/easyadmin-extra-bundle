<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminExportController extends AbstractController
{
    public function __construct(
        private readonly ExportManager $exportManager,
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly OperationRequestMetadataResolver $operationRequestMetadataResolver,
        private readonly OperationAdminContextFactory $operationAdminContextFactory,
    ) {}

    /**
     * Builds a fresh EasyAdmin context for the targeted dashboard and CRUD controller,
     * then delegates the export generation to the export manager.
     *
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $metadata = $this->operationRequestMetadataResolver->resolveExport($request, 'export');

        // Build a fresh EasyAdmin context for the targeted export request.
        $this->operationAdminContextFactory->createForRequest($request, $metadata, $this->crudActionNameResolver->resolve($request));

        return $this->exportManager->export($metadata->crudControllerFqcn, (string) $metadata->format, $request);
    }
}
