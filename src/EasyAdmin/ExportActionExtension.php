<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
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

    public function supports(AdminContext $context): bool
    {
        $crud = $context->getCrud();

        if (null === $crud || Crud::PAGE_INDEX !== $crud->getCurrentPage()) {
            return false;
        }

        try {
            $this->exportConfigFactory->create($crud->getControllerFqcn());

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @throws ReflectionException
     */
    public function extend(Actions $actions, AdminContext $context): void
    {
        $crud = $context->getCrud();

        if (null === $crud) {
            return;
        }

        try {
            $config = $this->exportConfigFactory->create($crud->getControllerFqcn());
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
            $crud->getControllerFqcn(),
            $config
        );
        $currentQuery = $request?->query->all() ?? [];
        $formats = [
            'csv' => ['action' => 'jdExportCsv', 'label' => $config->csvLabel],
            'xlsx' => ['action' => 'jdExportXlsx', 'label' => $config->xlsxLabel],
            'json' => ['action' => 'jdExportJson', 'label' => $config->jsonLabel],
        ];

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
}
