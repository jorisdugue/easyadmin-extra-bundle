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
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use ReflectionException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
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
        private AuthorizationCheckerInterface $authorizationChecker,
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
        } catch (Throwable) {
            return;
        }

        if (null !== $config->requiredRole && !$this->authorizationChecker->isGranted($config->requiredRole)) {
            return;
        }
        $request = $context->getRequest();

        if (!$this->exportRequestResolver->canDisplayExportAction($config, $request)) {
            return;
        }

        $exportActions = $this->buildExportActions($request, $context, $crudControllerFqcn, $config);
        $previewAction = $this->buildPreviewAction($request, $context, $crudControllerFqcn, $config);
        $batchActions = $this->buildBatchExportActions($context, $crudControllerFqcn, $config);
        if ([] === $exportActions && [] === $batchActions) {
            return;
        }

        if (ExportActionDisplay::DROPDOWN === $config->actionDisplay) {
            if ([] !== $exportActions) {
                $actions->add(Crud::PAGE_INDEX, $this->buildExportDropdown($exportActions, $previewAction));
            }

            foreach ($batchActions as $batchAction) {
                $actions->addBatchAction($batchAction);
            }

            return;
        }

        foreach ($exportActions as $exportAction) {
            $actions->add(Crud::PAGE_INDEX, $exportAction);
        }

        if (null !== $previewAction) {
            $actions->add(Crud::PAGE_INDEX, $previewAction);
        }

        foreach ($batchActions as $batchAction) {
            $actions->addBatchAction($batchAction);
        }
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @return list<Action>
     *
     * @throws ReflectionException
     */
    private function buildBatchExportActions(AdminContext $context, string $crudControllerFqcn, ExportConfig $config): array
    {
        if (!$config->batchExport) {
            return [];
        }

        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $actions = [];

        foreach ($this->getFormatDefinitions($config) as $format => $definition) {
            if (!$config->supportsFormat($format)) {
                continue;
            }

            $routeName = \sprintf('%s_%s_export_batch_%s', $dashboardRouteName, $crudRouteName, $format);

            $label = 1 === \count($config->formats)
                ? $config->batchExportLabel
                : \sprintf('%s (%s)', $config->batchExportLabel, strtoupper($format));

            $actions[] = Action::new('jdBatchExport_' . $format, $label)
                ->linkToUrl($this->router->generate($routeName))
                ->addCssClass('btn btn-secondary')
                ->setIcon('fa fa-download');
        }

        return $actions;
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @return list<Action>
     *
     * @throws ReflectionException
     */
    private function buildExportActions(Request $request, AdminContext $context, string $crudControllerFqcn, ExportConfig $config): array
    {
        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        $actions = [];

        foreach ($this->getFormatDefinitions($config) as $format => $definition) {
            if (!$config->supportsFormat($format)) {
                continue;
            }

            $actions[] = Action::new($definition['action'], $definition['label'])
                ->linkToUrl(fn () => $this->router->generate(
                    \sprintf('%s_%s_export_%s', $dashboardRouteName, $crudRouteName, $format),
                    $currentQuery,
                ))
                ->createAsGlobalAction();
        }

        return $actions;
    }

    /**
     * @param AdminContext<object> $context
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    private function buildPreviewAction(Request $request, AdminContext $context, string $crudControllerFqcn, ExportConfig $config): ?Action
    {
        if (!$config->previewEnabled) {
            return null;
        }

        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $config);
        $currentQuery = $request->query->all();
        $currentQuery['format'] = $config->getDefaultFormat();

        return Action::new('jdExportPreview', $config->previewLabel)
            ->linkToUrl(fn (): string => $this->router->generate(
                \sprintf('%s_%s_export_preview', $dashboardRouteName, $crudRouteName),
                $currentQuery,
            ))
            ->createAsGlobalAction();
    }

    /**
     * @param list<Action> $exportActions
     */
    private function buildExportDropdown(array $exportActions, ?Action $previewAction): ActionGroup
    {
        $group = ActionGroup::new('jdExportGroup', 'Export', 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->asPrimaryActionGroup();

        foreach ($exportActions as $exportAction) {
            $group->addAction($exportAction);
        }

        if (null !== $previewAction) {
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
        ];
    }
}
