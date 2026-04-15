<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * This interface and tag are part of EasyAdmin; if an application runs on a version
 * where action extensions are not available, simply remove this service and add
 * the export actions manually in configureActions().
 */
final readonly class ExportActionExtension implements ActionsExtensionInterface
{
    public function __construct(
        private ExportConfigFactory $exportConfigFactory,
        private RouterInterface $router,
        private ExportRequestResolver $exportRequestResolver,
        private ExportRouteMetadataResolver $exportRouteMetadataResolver,
        private RoleAuthorizationChecker $roleAuthorizationChecker,
        private ExportSetMetadataResolver $exportSetMetadataResolver,
    ) {}

    /**
     * @param AdminContext<object> $context
     */
    public function supports(AdminContext $context): bool
    {
        $crud = $context->getCrud();

        if (null === $crud || Crud::PAGE_INDEX !== $crud->getCurrentPage()) {
            return false;
        }
        $crudControllerFqcn = $crud->getControllerFqcn();

        if (null === $crudControllerFqcn || !is_a($crudControllerFqcn, AbstractCrudController::class, true)) {
            return false;
        }

        try {
            $this->exportConfigFactory->create($crudControllerFqcn);
            $this->exportSetMetadataResolver->resolveForCrud($crudControllerFqcn);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param AdminContext<object> $context
     *
     * @throws ReflectionException
     */
    public function extend(Actions $actions, AdminContext $context): void
    {
        $crud = $context->getCrud();

        if (null === $crud) {
            return;
        }
        $crudControllerFqcn = $crud->getControllerFqcn();

        if (null === $crudControllerFqcn || !is_a($crudControllerFqcn, AbstractCrudController::class, true)) {
            return;
        }
        /* @var class-string<AbstractCrudController<object>> $crudControllerFqcn */

        try {
            $config = $this->exportConfigFactory->create($crudControllerFqcn);
            $exportSets = $this->filterGrantedExportSets(
                $this->exportSetMetadataResolver->resolveForCrud($crudControllerFqcn),
                $config,
            );
        } catch (Throwable) {
            return;
        }

        if ([] === $exportSets) {
            return;
        }

        $request = $context->getRequest();

        if (!$this->exportRequestResolver->canDisplayExportAction($config, $request)) {
            return;
        }

        $exportActions = $this->buildExportActions($request, $context, $crudControllerFqcn, $config, $exportSets);
        $previewActions = $this->buildPreviewActions($request, $context, $crudControllerFqcn, $config, $exportSets);
        $batchActions = $this->buildBatchExportActions($context, $crudControllerFqcn, $config, $exportSets);
        if ([] === $exportActions && [] === $batchActions) {
            return;
        }

        if (ExportActionDisplay::DROPDOWN === $config->actionDisplay) {
            if ([] !== $exportActions || [] !== $previewActions) {
                $actions->add(Crud::PAGE_INDEX, $this->buildExportDropdown($exportActions, $previewActions));
            }

            foreach ($batchActions as $batchAction) {
                $actions->addBatchAction($batchAction);
            }

            return;
        }

        foreach ($exportActions as $exportAction) {
            $actions->add(Crud::PAGE_INDEX, $exportAction);
        }

        foreach ($previewActions as $previewAction) {
            $actions->add(Crud::PAGE_INDEX, $previewAction);
        }

        foreach ($batchActions as $batchAction) {
            $actions->addBatchAction($batchAction);
        }
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     * @param list<ExportSetMetadata> $exportSets
     *
     * @return list<Action>
     *
     * @throws ReflectionException
     */
    private function buildBatchExportActions(AdminContext $context, string $crudControllerFqcn, ExportConfig $config, array $exportSets): array
    {
        if (!$config->batchExport) {
            return [];
        }

        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $actions = [];
        $multipleSets = \count($exportSets) > 1;

        foreach ($exportSets as $exportSet) {
            foreach ($this->getFormatDefinitions($config) as $format => $definition) {
                if (!$config->supportsFormat($format)) {
                    continue;
                }

                $routeName = \sprintf('%s_%s_export_batch_%s', $dashboardRouteName, $crudRouteName, $format);
                $parameters = $this->buildExportSetParameters($exportSet);
                $label = $this->buildSetAwareLabel(
                    baseLabel: $config->batchExportLabel,
                    format: $format,
                    formatCount: \count($config->formats),
                    exportSet: $exportSet,
                    multipleSets: $multipleSets,
                );

                $actions[] = Action::new('jdBatchExport_' . $exportSet->getName() . '_' . $format, $label)
                    ->linkToUrl($this->router->generate($routeName, $parameters))
                    ->addCssClass('btn btn-secondary')
                    ->setIcon('fa fa-download');
            }
        }

        return $actions;
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     * @param list<ExportSetMetadata> $exportSets
     *
     * @return list<Action>
     *
     * @throws ReflectionException
     */
    private function buildExportActions(Request $request, AdminContext $context, string $crudControllerFqcn, ExportConfig $config, array $exportSets): array
    {
        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        unset($currentQuery['exportSet']);
        $actions = [];
        $multipleSets = \count($exportSets) > 1;

        foreach ($exportSets as $exportSet) {
            foreach ($this->getFormatDefinitions($config) as $format => $definition) {
                if (!$config->supportsFormat($format)) {
                    continue;
                }

                $parameters = [...$currentQuery, ...$this->buildExportSetParameters($exportSet)];
                $label = $this->buildSetAwareLabel(
                    baseLabel: $definition['label'],
                    format: $format,
                    formatCount: \count($config->formats),
                    exportSet: $exportSet,
                    multipleSets: $multipleSets,
                );

                $actions[] = Action::new($definition['action'] . '_' . $exportSet->getName(), $label)
                    ->linkToUrl(fn () => $this->router->generate(
                        \sprintf('%s_%s_export_%s', $dashboardRouteName, $crudRouteName, $format),
                        $parameters,
                    ))
                    ->createAsGlobalAction();
            }
        }

        return $actions;
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     * @param list<ExportSetMetadata> $exportSets
     *
     * @return list<Action>
     *
     * @throws ReflectionException
     */
    private function buildPreviewActions(Request $request, AdminContext $context, string $crudControllerFqcn, ExportConfig $config, array $exportSets): array
    {
        if (!$config->previewEnabled) {
            return [];
        }

        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        unset($currentQuery['exportSet']);
        $multipleSets = \count($exportSets) > 1;
        $actions = [];

        foreach ($exportSets as $exportSet) {
            $parameters = [...$currentQuery, ...$this->buildExportSetParameters($exportSet), 'format' => $config->getDefaultFormat()];
            $label = $multipleSets
                ? \sprintf('%s (%s)', $config->previewLabel, $exportSet->getLabel())
                : $config->previewLabel;

            $actions[] = Action::new('jdExportPreview_' . $exportSet->getName(), $label)
                ->linkToUrl(fn (): string => $this->router->generate(
                    \sprintf('%s_%s_export_preview', $dashboardRouteName, $crudRouteName),
                    $parameters,
                ))
                ->createAsGlobalAction();
        }

        return $actions;
    }

    /**
     * @param list<Action> $exportActions
     * @param list<Action> $previewActions
     */
    private function buildExportDropdown(array $exportActions, array $previewActions): ActionGroup
    {
        $group = ActionGroup::new('jdExportGroup', 'Export', 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->asPrimaryActionGroup();

        foreach ($exportActions as $exportAction) {
            $group->addAction($exportAction);
        }

        foreach ($previewActions as $previewAction) {
            $group->addAction($previewAction);
        }

        return $group;
    }

    /**
     * @return array<string, array{action: string, label: string}>
     */
    private function getFormatDefinitions(ExportConfig $config): array
    {
        return [
            ExportFormat::CSV => ['action' => 'jdExportCsv', 'label' => $config->csvLabel],
            ExportFormat::XLSX => ['action' => 'jdExportXlsx', 'label' => $config->xlsxLabel],
            ExportFormat::JSON => ['action' => 'jdExportJson', 'label' => $config->jsonLabel],
            ExportFormat::XML => ['action' => 'jdExportXml', 'label' => $config->xmlLabel],
        ];
    }

    /**
     * @param list<ExportSetMetadata> $exportSets
     *
     * @return list<ExportSetMetadata>
     */
    private function filterGrantedExportSets(array $exportSets, ExportConfig $config): array
    {
        if ([] !== $config->requiredRoles && !$this->roleAuthorizationChecker->isGrantedForAnyRole($config->requiredRoles)) {
            return [];
        }

        return array_values(array_filter(
            $exportSets,
            fn (ExportSetMetadata $exportSet): bool => $this->roleAuthorizationChecker->isGrantedForAnyRole($exportSet->getRequiredRoles()),
        ));
    }

    /**
     * @return array<string, string>
     */
    private function buildExportSetParameters(ExportSetMetadata $exportSet): array
    {
        if ('default' === $exportSet->getName()) {
            return [];
        }

        return ['exportSet' => $exportSet->getName()];
    }

    private function buildSetAwareLabel(string $baseLabel, string $format, int $formatCount, ExportSetMetadata $exportSet, bool $multipleSets): string
    {
        $label = $multipleSets ? \sprintf('%s (%s)', $exportSet->getLabel(), strtoupper($format)) : $baseLabel;

        if ($multipleSets) {
            return $label;
        }

        if ($formatCount > 1 && !str_contains($baseLabel, strtoupper($format))) {
            return \sprintf('%s (%s)', $baseLabel, strtoupper($format));
        }

        return $baseLabel;
    }
}
