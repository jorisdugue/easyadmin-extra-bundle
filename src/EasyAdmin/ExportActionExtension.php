<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use ReflectionException;
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

        if (null === $crudControllerFqcn) {
            return false;
        }
        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */

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

        if (null === $crudControllerFqcn) {
            return;
        }
        /** @var class-string<AbstractCrudController<object>> $crudControllerFqcn */

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
        $dashboardRouteName = $context->getDashboardRouteName();
        $crudRouteName = $this->exportRouteMetadataResolver->resolveRouteName(
            $crudControllerFqcn,
            $config
        );
        $currentQuery = $request->query->all();
        $formats = $this->getFormatDefinitions($config);

        foreach ($formats as $format => $definition) {
            if (!$config->supportsFormat($format)) {
                continue;
            }
            $actions->add(
                Crud::PAGE_INDEX,
                Action::new($definition['action'], $definition['label'])
                    ->linkToUrl(fn () => $this->router->generate(
                        \sprintf('%s_%s_export_%s', $dashboardRouteName, $crudRouteName, $format),
                        $currentQuery
                    ))
                    ->createAsGlobalAction()
            );
        }
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
