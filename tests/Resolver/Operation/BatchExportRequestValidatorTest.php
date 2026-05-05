<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Resolver\Operation;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidBatchExportException;
use JorisDugue\EasyAdminExtraBundle\Resolver\Operation\BatchExportRequestValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class BatchExportRequestValidatorTest extends TestCase
{
    public function testValidateAcceptsEasyAdminBatchRequest(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager
            ->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => 'ea-batch-action-jdBatchExport_default_csv' === $token->getId() && 'valid-token' === $token->getValue()))
            ->willReturn(true);

        $request = new Request(request: [
            EA::BATCH_ACTION_NAME => 'jdBatchExport_default_csv',
            EA::BATCH_ACTION_CSRF_TOKEN => 'valid-token',
            EA::ENTITY_FQCN => BatchExportRequestValidatorEntity::class,
        ]);

        (new BatchExportRequestValidator($csrfTokenManager))->validate($request, BatchExportRequestValidatorCrudController::class);

        self::addToAssertionCount(1);
    }

    public function testValidateRejectsInvalidCsrfToken(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $request = new Request(request: [
            EA::BATCH_ACTION_NAME => 'jdBatchExport_default_csv',
            EA::BATCH_ACTION_CSRF_TOKEN => 'invalid-token',
            EA::ENTITY_FQCN => BatchExportRequestValidatorEntity::class,
        ]);

        $this->expectException(InvalidCsrfTokenException::class);

        (new BatchExportRequestValidator($csrfTokenManager))->validate($request, BatchExportRequestValidatorCrudController::class);
    }

    public function testValidateRejectsMissingEntityFqcn(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = new Request(request: [
            EA::BATCH_ACTION_NAME => 'jdBatchExport_default_csv',
            EA::BATCH_ACTION_CSRF_TOKEN => 'valid-token',
        ]);

        $this->expectException(InvalidBatchExportException::class);
        $this->expectExceptionMessage('Invalid batch export entity FQCN');

        (new BatchExportRequestValidator($csrfTokenManager))->validate($request, BatchExportRequestValidatorCrudController::class);
    }

    public function testValidateRejectsMismatchedEntityFqcn(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = new Request(request: [
            EA::BATCH_ACTION_NAME => 'jdBatchExport_default_csv',
            EA::BATCH_ACTION_CSRF_TOKEN => 'valid-token',
            EA::ENTITY_FQCN => 'App\\Entity\\Other',
        ]);

        $this->expectException(InvalidBatchExportException::class);
        $this->expectExceptionMessage('Invalid batch export entity FQCN');

        (new BatchExportRequestValidator($csrfTokenManager))->validate($request, BatchExportRequestValidatorCrudController::class);
    }
}

final class BatchExportRequestValidatorCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return BatchExportRequestValidatorEntity::class;
    }
}

final class BatchExportRequestValidatorEntity {}
