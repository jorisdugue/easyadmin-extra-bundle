<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Export;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPreview;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use JorisDugue\EasyAdminExtraBundle\Event\Export\AfterExportEvent;
use JorisDugue\EasyAdminExtraBundle\Event\Export\BeforeExportEvent;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Factory\Export\ExportContextFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\Operation\EntityQueryBuilderFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportPreviewInspector;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\OperationScopeResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final readonly class ExportManager
{
    public function __construct(
        private CrudControllerResolver $crudControllerResolver,
        private ExportConfigFactory $exportConfigFactory,
        private ExportContextFactory $exportContextFactory,
        private ExportPayloadFactory $exportPayloadFactory,
        private EntityQueryBuilderFactory $entityQueryBuilderFactory,
        private ExportPreviewInspector $exportPreviewInspector,
        private ExporterRegistry $exporterRegistry,
        private ExportSetMetadataResolver $exportSetMetadataResolver,
        private RoleAuthorizationChecker $roleAuthorizationChecker,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function export(string $crudControllerFqcn, string $format, Request $request): Response
    {
        [$crudController, $config] = $this->resolveAuthorizedCrudAndConfig($crudControllerFqcn, $request);

        $this->assertFormatSupported($config, $crudController::class, $format);

        $context = $this->exportContextFactory->create(
            $crudController,
            $request,
            $config,
            $format,
        );

        $qb = $this->entityQueryBuilderFactory->createQueryBuilderForScope(
            $crudController,
            $request,
            $context->scope,
        );

        $payload = $this->exportPayloadFactory->create(
            $crudController,
            $qb,
            $config,
            $context,
        );

        return $this->createExportResponse($format, $context, $payload);
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function preview(string $crudControllerFqcn, string $format, Request $request): ExportPreview
    {
        [$crudController, $config] = $this->resolveAuthorizedCrudAndConfig($crudControllerFqcn, $request);

        if (!$config->previewEnabled) {
            throw new AccessDeniedException('Export preview is not enabled for this resource.');
        }

        $this->assertFormatSupported($config, $crudController::class, $format);

        $context = $this->exportContextFactory->create(
            $crudController,
            $request,
            $config,
            $format,
        );

        $qb = $this->entityQueryBuilderFactory->createQueryBuilderForScope(
            $crudController,
            $request,
            $context->scope,
        );

        $qb->setFirstResult(0)->setMaxResults($config->previewLimit);

        [$headers, $rows] = $this->exportPayloadFactory->createPreview(
            $crudController,
            $qb,
            $config,
            $context,
            $config->previewLimit,
        );

        return new ExportPreview(
            format: $format,
            scope: $context->scope,
            entityName: $context->entityName,
            limit: $config->previewLimit,
            headers: $headers,
            rows: $rows,
            showFormatPreviewActions: $this->exportPreviewInspector->hasFormatSpecificPreviewVariants($config),
            actionDisplay: $config->actionDisplay,
            formatLabels: $this->buildFormatLabels($config),
        );
    }

    /**
     * Exports a manually selected set of entities identified by their IDs.
     *
     * This method is triggered by EasyAdmin batch actions. It receives the list
     * of IDs submitted via the POST form and delegates to the same export
     * pipeline as export(), using a selection-scoped QueryBuilder.
     *
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     * @param list<int|string>                             $ids
     *
     * @throws ReflectionException
     */
    public function exportBatch(string $crudControllerFqcn, string $format, array $ids, Request $request): Response
    {
        if ([] === $ids) {
            throw InvalidBatchExportException::emptySelection();
        }

        [$crudController, $config] = $this->resolveAuthorizedCrudAndConfig($crudControllerFqcn, $request);

        if (!$config->batchExport) {
            throw new AccessDeniedException('Batch export is not enabled for this resource.');
        }

        $this->assertFormatSupported($config, $crudController::class, $format);

        $context = $this->exportContextFactory->create(
            $crudController,
            $request,
            $config,
            $format,
            OperationScopeResolver::SCOPE_SELECTION,
        );

        $qb = $this->entityQueryBuilderFactory->createQueryBuilderForScope(
            $crudController,
            $request,
            $context->scope,
            $ids,
        );

        $payload = $this->exportPayloadFactory->create(
            $crudController,
            $qb,
            $config,
            $context,
        );

        return $this->createExportResponse($format, $context, $payload);
    }

    private function assertGranted(ExportConfig $config, ExportSetMetadata $setMetadata): void
    {
        if ([] !== $config->requiredRoles && !$this->roleAuthorizationChecker->isGrantedForAnyRole($config->requiredRoles)) {
            throw new AccessDeniedException(\sprintf('One of the following roles is required to export this resource: %s.', implode(', ', $config->requiredRoles)));
        }

        $requiredRoles = $setMetadata->getRequiredRoles();

        if ([] !== $requiredRoles && !$this->roleAuthorizationChecker->isGrantedForAnyRole($requiredRoles)) {
            throw new AccessDeniedException(\sprintf('One of the following roles is required to access the "%s" export set: %s.', $setMetadata->getName(), implode(', ', $requiredRoles)));
        }
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerClass
     */
    private function assertFormatSupported(ExportConfig $config, string $crudControllerClass, string $format): void
    {
        if (!$config->supportsFormat($format)) {
            throw InvalidExportConfigurationException::forbiddenFormat($format, $crudControllerClass, $config->formats);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildFormatLabels(ExportConfig $config): array
    {
        $formatLabels = [];

        foreach ($config->formats as $availableFormat) {
            $formatLabels[$availableFormat] = $config->getLabelForFormat($availableFormat);
        }

        return $formatLabels;
    }

    private function createExportResponse(string $format, ExportContext $context, ExportPayload $payload): Response
    {
        $this->eventDispatcher->dispatch(new BeforeExportEvent($context, $payload));

        $response = $this->exporterRegistry->export($format, $payload);

        $this->eventDispatcher->dispatch(new AfterExportEvent($context, $payload, $response));

        return $response;
    }

    private function toConfigExportSet(ExportSetMetadata $setMetadata): ?string
    {
        return 'default' === $setMetadata->getName() ? null : $setMetadata->getName();
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @return array{0: AbstractCrudController<object>, 1: ExportConfig}
     *
     * @throws ReflectionException
     */
    private function resolveAuthorizedCrudAndConfig(string $crudControllerFqcn, Request $request): array
    {
        $requestedSet = $this->exportSetMetadataResolver->normalizeRequestedSet($request->query->get('exportSet'));
        $setMetadata = $this->exportSetMetadataResolver->resolveRequestedSet($crudControllerFqcn, $requestedSet);
        $crudController = $this->crudControllerResolver->resolve($crudControllerFqcn);
        $config = $this->exportConfigFactory->create($crudControllerFqcn, $this->toConfigExportSet($setMetadata));

        $this->assertGranted($config, $setMetadata);

        return [$crudController, $config];
    }
}
