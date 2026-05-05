<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Controller;

use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\OperationAdminContextFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudActionNameResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\BatchExportRequestValidator;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationRequestMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Export\ExportManager;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;
use ReflectionException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminExportBatchController extends AbstractController
{
    public function __construct(
        private readonly ExportManager $exportManager,
        private readonly CrudActionNameResolver $crudActionNameResolver,
        private readonly OperationRequestMetadataResolver $operationRequestMetadataResolver,
        private readonly OperationAdminContextFactory $operationAdminContextFactory,
        private readonly BatchExportRequestValidator $batchExportRequestValidator,
    ) {}

    /**
     * Handles a batch export POST request submitted by EasyAdmin's batch action form.
     *
     * EasyAdmin submits selected entity IDs as batchActionEntityIds[] in the POST body.
     * This controller reads those IDs, builds a scoped query, and streams the export.
     *
     * @throws ReflectionException
     */
    public function __invoke(Request $request): Response
    {
        $metadata = $this->operationRequestMetadataResolver->resolveExport($request, 'batch export');
        $this->batchExportRequestValidator->validate($request, $metadata->crudControllerFqcn);

        $rawIds = $request->request->all('batchActionEntityIds');
        $ids = array_values(array_filter(
            array_map(static fn (mixed $id): string => trim(ValueStringifier::stringify($id)), $rawIds),
            static fn (string $id): bool => '' !== $id,
        ));

        if ([] === $ids) {
            throw InvalidBatchExportException::emptySelection();
        }

        $this->operationAdminContextFactory->createForRequest($request, $metadata, $this->crudActionNameResolver->resolve($request));

        return $this->exportManager->exportBatch($metadata->crudControllerFqcn, (string) $metadata->format, $ids, $request);
    }
}
