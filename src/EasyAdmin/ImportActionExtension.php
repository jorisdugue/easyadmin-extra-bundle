<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Action\ActionsExtensionInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Resolver\ImportRouteMetadataResolver;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * This interface and tag are part of EasyAdmin; if an application runs on a version
 * where action extensions are not available, simply remove this service and add
 * the import action manually in configureActions().
 */
final readonly class ImportActionExtension implements ActionsExtensionInterface
{
    public function __construct(
        private RouterInterface $router,
        private ImportRouteMetadataResolver $importRouteMetadataResolver,
        private ?LoggerInterface $logger = null,
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
            return null !== $this->resolveImportAttribute($crudControllerFqcn);
        } catch (Throwable $exception) {
            $this->logConfigurationException($exception, $crudControllerFqcn);

            return false;
        }
    }

    /**
     * @param AdminContext<object> $context
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
            $importAttribute = $this->resolveImportAttribute($crudControllerFqcn);
            if (null === $importAttribute) {
                return;
            }

            $crudRouteName = $this->importRouteMetadataResolver->resolveRouteName($crudControllerFqcn, $importAttribute);
        } catch (Throwable $exception) {
            $this->logConfigurationException($exception, $crudControllerFqcn);

            return;
        }

        $routeName = \sprintf('%s_%s_import_preview', $context->getDashboardRouteName(), $crudRouteName);

        $actions->add(
            Crud::PAGE_INDEX,
            Action::new('jdImportPreview', 'Import', 'fa fa-upload')
                ->linkToUrl(fn (): string => $this->router->generate($routeName))
                ->createAsGlobalAction(),
        );
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    private function resolveImportAttribute(string $crudControllerFqcn): ?AdminImport
    {
        $reflection = new ReflectionClass($crudControllerFqcn);
        $attributes = $reflection->getAttributes(AdminImport::class);

        if ([] === $attributes) {
            return null;
        }

        /** @var AdminImport $attribute */
        $attribute = $attributes[0]->newInstance();

        return $attribute;
    }

    private function logConfigurationException(Throwable $exception, ?string $crudControllerFqcn): void
    {
        $this->logger?->warning('Unable to configure EasyAdmin import action.', [
            'exception' => $exception,
            'crud_controller' => $crudControllerFqcn,
        ]);
    }
}
