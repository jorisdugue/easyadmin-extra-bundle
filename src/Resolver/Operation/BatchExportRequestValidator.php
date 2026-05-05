<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final readonly class BatchExportRequestValidator
{
    public function __construct(
        private CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    public function validate(Request $request, string $crudControllerFqcn): void
    {
        $this->validateCsrfToken($request);
        $this->validateEntityFqcn($request, $crudControllerFqcn);
    }

    private function validateCsrfToken(Request $request): void
    {
        $actionName = trim($request->request->getString(EA::BATCH_ACTION_NAME));
        $csrfToken = $request->request->getString(EA::BATCH_ACTION_CSRF_TOKEN);

        if ('' === $actionName || !$this->csrfTokenManager->isTokenValid(new CsrfToken('ea-batch-action-' . $actionName, $csrfToken))) {
            throw new InvalidCsrfTokenException();
        }
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     */
    private function validateEntityFqcn(Request $request, string $crudControllerFqcn): void
    {
        $postedEntityFqcn = $request->request->get(EA::ENTITY_FQCN);
        $postedEntityFqcn = \is_string($postedEntityFqcn) ? trim($postedEntityFqcn) : null;

        /** @var class-string<object> $expectedEntityFqcn */
        $expectedEntityFqcn = $crudControllerFqcn::getEntityFqcn();

        if (null === $postedEntityFqcn || '' === $postedEntityFqcn || $postedEntityFqcn !== $expectedEntityFqcn) {
            throw InvalidBatchExportException::invalidEntityFqcn($expectedEntityFqcn, $postedEntityFqcn);
        }
    }
}
