<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use JorisDugue\EasyAdminExtraBundle\EasyAdmin\ExportActionExtension;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidExportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Resolver\Export\ExportSetMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRequestResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportRouteMetadataResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\ActiveIndexContextResolver;
use JorisDugue\EasyAdminExtraBundle\Service\Operation\RoleAuthorizationChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class ExportActionExtensionTest extends TestCase
{
    public function testInvalidConfigDoesNotCrashAndIsLogged(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Unable to configure EasyAdmin export actions.',
                self::callback(static function (array $context): bool {
                    return ($context['exception'] ?? null) instanceof InvalidExportConfigurationException
                        && InvalidExportActionCrudController::class === ($context['crud_controller'] ?? null);
                }),
            );

        $extension = new ExportActionExtension(
            new ExportConfigFactory(),
            $this->createMock(RouterInterface::class),
            new ExportRequestResolver(new ActiveIndexContextResolver()),
            new ExportRouteMetadataResolver(),
            new RoleAuthorizationChecker($this->createMock(AuthorizationCheckerInterface::class)),
            new ExportSetMetadataResolver(),
            $logger,
        );

        self::assertFalse($extension->supports($this->createAdminContext(InvalidExportActionCrudController::class)));
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    private function createAdminContext(string $crudControllerFqcn): AdminContext
    {
        $crudDto = new CrudDto();
        $crudDto->setPageName(Crud::PAGE_INDEX);
        $crudDto->setControllerFqcn($crudControllerFqcn);

        return AdminContext::forTesting(
            RequestContext::forTesting(new Request()),
            CrudContext::forTesting($crudDto),
        );
    }
}

final class InvalidExportActionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }
}
