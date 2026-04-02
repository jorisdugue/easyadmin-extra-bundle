<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service;

use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPreview;
use JorisDugue\EasyAdminExtraBundle\Exporter\CsvExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\JsonExporter;
use JorisDugue\EasyAdminExtraBundle\Exporter\XlsxExporter;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Field\ExportFieldOption;
use JorisDugue\EasyAdminExtraBundle\Resolver\CrudControllerResolver;
use JorisDugue\EasyAdminExtraBundle\Support\CollectionFactoryCompat;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class ExportManager
{
    public function __construct(
        private CrudControllerResolver $crudControllerResolver,
        private ExportConfigFactory $exportConfigFactory,
        private ExportPayloadFactory $exportPayloadFactory,
        private JsonExporter $jsonExporter,
        private CsvExporter $csvExporter,
        private XlsxExporter $xlsxExporter,
        private AuthorizationCheckerInterface $authorizationChecker,
        private Security $security,
        private CollectionFactoryCompat $collectionFactoryCompat,
        private FilterFactory $filterFactory,
    ) {}

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function export(string $crudControllerFqcn, string $format, Request $request): Response
    {
        $crudController = $this->crudControllerResolver->resolve($crudControllerFqcn);
        $config = $this->exportConfigFactory->create($crudControllerFqcn);

        $this->assertGranted($config);

        if (!$config->supportsFormat($format)) {
            throw new InvalidArgumentException(\sprintf('Le format "%s" n\'est pas autorisé.', $format));
        }

        $context = $this->createExportContext($crudController, $request, $config, $format);
        $queryBuilder = $this->createQueryBuilderForScope($crudController, $request, $context->scope);
        $payload = $this->exportPayloadFactory->create(
            $crudController,
            $queryBuilder,
            $config,
            $context
        );

        return match ($format) {
            ExportFormat::CSV => $this->csvExporter->export($payload),
            ExportFormat::XLSX => $this->xlsxExporter->export($payload),
            ExportFormat::JSON => $this->jsonExporter->export($payload),
            default => throw new InvalidArgumentException(\sprintf('Le format "%s" n\'est pas supporté.', $format)),
        };
    }

    /**
     * @param AbstractCrudController<object> $crudController
     */
    private function createQueryBuilderForScope(
        AbstractCrudController $crudController,
        Request $request,
        string $scope,
    ): QueryBuilder {
        return match ($scope) {
            'context' => $this->createContextQueryBuilder($crudController, $request),
            default => $this->createAllQueryBuilder($crudController, $request),
        };
    }

    /**
     * @param AbstractCrudController<object> $crudController
     *
     * @throws ReflectionException
     */
    private function createExportContext(
        AbstractCrudController $crudController,
        Request $request,
        ExportConfig $config,
        string $format,
    ): ExportContext {
        $user = $this->security->getUser();
        return new ExportContext(
            format: $format,
            scope: $this->resolveScope($request, $config),
            generatedAt: new DateTimeImmutable(),
            user: $user,
            entityName: $this->guessEntityName($crudController),
            roles: $this->resolveUserRoles($user)
        );
    }

    /**
     * @param class-string<AbstractCrudController<object>> $crudControllerFqcn
     *
     * @throws ReflectionException
     */
    public function preview(string $crudControllerFqcn, string $format, Request $request): ExportPreview
    {
        $crudController = $this->crudControllerResolver->resolve($crudControllerFqcn);
        $config = $this->exportConfigFactory->create($crudControllerFqcn);

        $this->assertGranted($config);

        if (!$config->previewEnabled) {
            throw new AccessDeniedException('Export preview is not enabled for this resource.');
        }

        if (!$config->supportsFormat($format)) {
            throw new InvalidArgumentException(\sprintf('Le format "%s" n\'est pas autorisé pour la prévisualisation.', $format));
        }

        $context = $this->createExportcontext($crudController, $request, $config, $format);
        $queryBuilder = $this->createQueryBuilderForScope($crudController, $request, $context->scope);
        $queryBuilder->setFirstResult(0)
            ->setMaxResults($config->previewLimit);
        [$headers, $rows] = $this->exportPayloadFactory->createPreview(
            $crudController,
            $queryBuilder,
            $config,
            $context,
            $config->previewLimit
        );

        $formatLabels = [];

        foreach ($config->formats as $availableFormat) {
            $formatLabels[$availableFormat] = $config->getLabelForFormat($availableFormat);
        }

        return new ExportPreview(
            format: $format,
            scope: $context->scope,
            entityName: $context->entityName,
            limit: $config->previewLimit,
            headers: $headers,
            rows: $rows,
            showFormatPreviewActions: $this->hasFormatSpecificPreviewVariants($config),
            actionDisplay: $config->actionDisplay,
            formatLabels: $formatLabels
        );
    }

    private function hasFormatSpecificPreviewVariants(ExportConfig $config): bool
    {
        if (\count($config->formats) < 2) {
            return false;
        }

        foreach ($config->fields as $field) {
            if ($this->fieldHasFormatSpecificPreviewVariant($field)) {
                return true;
            }
        }

        return false;
    }

    private function fieldHasFormatSpecificPreviewVariant(ExportFieldInterface $field): bool
    {
        $dto = $field->getAsDto();
        $visibleFormats = $dto->getCustomOption(ExportFieldOption::VISIBLE_FORMATS);
        if (\is_array($visibleFormats) && [] !== $visibleFormats) {
            return true;
        }
        $hiddenFormats = $dto->getCustomOption(ExportFieldOption::HIDDEN_FORMATS);
        if (\is_array($hiddenFormats) && [] !== $hiddenFormats) {
            return true;
        }

        $formatLabels = $dto->getCustomOption(ExportFieldOption::FORMAT_LABELS);

        return \is_array($formatLabels) && [] !== $formatLabels;
    }

    private function resolveScope(Request $request, ExportConfig $config): string
    {
        $forcedMode = $request->query->getString('mode');

        if ('' !== $forcedMode && !\in_array($forcedMode, ['all', 'context'], true)) {
            throw new InvalidArgumentException(\sprintf('Invalid export mode "%s". Allowed modes are: all, context.', $forcedMode));
        }

        if ('all' === $forcedMode) {
            if (!$config->fullExport) {
                throw new AccessDeniedException('Full export (mode=all) is not enabled for this resource.');
            }

            return 'all';
        }

        if ('context' === $forcedMode) {
            if (!$config->filteredExport) {
                throw new AccessDeniedException('Filtered export (mode=context) is not enabled for this resource.');
            }

            return 'context';
        }

        $hasSearch = '' !== trim((string) ($request->query->get('query') ?? ''));
        $hasFilters = [] !== $request->query->all('filters');
        $hasSort = [] !== $request->query->all('sort');

        if ($hasSearch || $hasFilters || $hasSort) {
            if (!$config->filteredExport) {
                throw new AccessDeniedException('Filtered export is not enabled for this resource.');
            }

            return 'context';
        }

        if ($config->fullExport) {
            return 'all';
        }

        if ($config->filteredExport) {
            throw new AccessDeniedException('A filtered export is enabled for this resource, but the current request does not contain any search, filter, or sort context.');
        }

        throw new AccessDeniedException('Export is not enabled for this resource.');
    }

    private function createEmptySearchDto(SearchDto $searchDto, Request $request): SearchDto
    {
        return new SearchDto(
            $request,
            $searchDto->getSearchableProperties(),
            null,
            [],
            [],
            [],
            $searchDto->getSearchMode()
        );
    }

    /**
     * @param AbstractCrudController<object> $crudController
     */
    private function createAllQueryBuilder(AbstractCrudController $crudController, Request $request): QueryBuilder
    {
        if (method_exists($crudController, 'createExportAllQueryBuilder')) {
            $qb = $crudController->createExportAllQueryBuilder();

            if (!$qb instanceof QueryBuilder) {
                throw new RuntimeException('La méthode createExportAllQueryBuilder() doit retourner un QueryBuilder Doctrine.');
            }

            $qb->resetDQLPart('orderBy');

            return $this->stripPagination($qb);
        }

        /** @var AdminContext<object>|null $context */
        $context = $request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (null === $context) {
            throw new RuntimeException('Unable to build a full export without an EasyAdmin request context.');
        }

        $search = $context->getSearch();
        if (!$search instanceof SearchDto) {
            throw new RuntimeException('Unable to build a full export because the EasyAdmin search context is missing.');
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            throw new RuntimeException('Unable to build a full export because the EasyAdmin CRUD context is missing.');
        }

        $fields = $this->collectionFactoryCompat->createFieldCollection(
            $crudController->configureFields(Crud::PAGE_INDEX)
        );

        $filters = $this->filterFactory->create(
            $crud->getFiltersConfig(),
            $fields,
            $context->getEntity()
        );

        $qb = $crudController->createIndexQueryBuilder(
            $this->createEmptySearchDto($search, $request),
            $context->getEntity(),
            $fields,
            $filters
        );

        $qb->resetDQLPart('orderBy');

        return $this->stripPagination($qb);
    }

    /**
     * @param AbstractCrudController<object> $crudController
     */
    private function createContextQueryBuilder(AbstractCrudController $crudController, Request $request): QueryBuilder
    {
        /** @var AdminContext<object>|null $context */
        $context = $request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);
        if (null === $context) {
            throw new RuntimeException('Unable to build a context export without an EasyAdmin request context.');
        }

        $search = $context->getSearch();
        if (!$search instanceof SearchDto) {
            throw new RuntimeException('Unable to build a context export without an EasyAdmin search context.');
        }

        $crud = $context->getCrud();

        if (null === $crud) {
            throw new RuntimeException('Unable to build a context export because the EasyAdmin CRUD context is missing.');
        }
        $fields = $this->collectionFactoryCompat->createFieldCollection(
            $crudController->configureFields(Crud::PAGE_INDEX)
        );

        $filters = $this->filterFactory->create(
            $crud->getFiltersConfig(),
            $fields,
            $context->getEntity()
        );

        $qb = $crudController->createIndexQueryBuilder(
            $search,
            $context->getEntity(),
            $fields,
            $filters
        );

        return $this->stripPagination($qb);
    }

    private function stripPagination(QueryBuilder $queryBuilder): QueryBuilder
    {
        $queryBuilder->setFirstResult(null);
        $queryBuilder->setMaxResults(null);

        return $queryBuilder;
    }

    private function assertGranted(ExportConfig $config): void
    {
        if (null !== $config->requiredRole && !$this->authorizationChecker->isGranted($config->requiredRole)) {
            throw new AccessDeniedException(\sprintf('Le rôle "%s" est requis pour exporter.', $config->requiredRole));
        }
    }

    /**
     * @param AbstractCrudController<object> $crudController
     *
     * @throws ReflectionException
     */
    private function guessEntityName(AbstractCrudController $crudController): string
    {
        $short = new ReflectionClass($crudController::getEntityFqcn())->getShortName();
        $short = preg_replace('/(?<!^)[A-Z]/', '_$0', $short) ?? $short;

        return strtolower($short);
    }

    private function resolveUserRoles(?UserInterface $user): array
    {
        if (null === $user) {
            return [];
        }

        $roles = [];

        foreach ($user->getRoles() as $role) {
            if (!\is_string($role)) {
                continue;
            }

            $normalizedRole = strtoupper(trim($role));

            if ('' === $normalizedRole) {
                continue;
            }

            if (!\in_array($normalizedRole, $roles, true)) {
                $roles[] = $normalizedRole;
            }
        }

        return $roles;
    }
}
