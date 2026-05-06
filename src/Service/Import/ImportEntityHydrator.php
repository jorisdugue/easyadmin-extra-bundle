<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportRowResult;
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;
use Psr\Log\LoggerInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Throwable;

final class ImportEntityHydrator
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(?PropertyAccessorInterface $propertyAccessor = null, private readonly ?LoggerInterface $logger = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    /**
     * @param class-string $entityFqcn
     * @param list<mixed> $row
     *
     * @return array{0: object|null, 1: ImportRowResult}
     */
    public function hydrate(string $entityFqcn, ImportConfig $config, array $row, int $rowNumber): array
    {
        try {
            $entity = new $entityFqcn();
        } catch (Throwable $exception) {
            $this->logHydrationException($exception, $entityFqcn, $rowNumber);

            return [null, new ImportRowResult($rowNumber, false, ['The imported row could not be hydrated.'])];
        }

        $errors = [];

        foreach ($this->getImportableFields($config) as $index => $field) {
            $property = $field->getAsDto()->getProperty();
            if (!\is_string($property) || '' === trim($property)) {
                $errors[] = \sprintf('Field at position %d does not define a writable property.', $index + 1);
                continue;
            }

            try {
                $this->propertyAccessor->setValue($entity, $property, $row[$index] ?? null);
            } catch (Throwable $exception) {
                $this->logHydrationException($exception, $entityFqcn, $rowNumber, $property);
                $errors[] = \sprintf('Property "%s" is not writable.', $property);
            }
        }

        if ([] !== $errors) {
            return [null, new ImportRowResult($rowNumber, false, $errors)];
        }

        return [$entity, new ImportRowResult($rowNumber, true)];
    }

    private function logHydrationException(Throwable $exception, string $entityFqcn, int $rowNumber, ?string $property = null): void
    {
        $this->logger?->warning('Unable to hydrate import row.', [
            'entity_class' => $entityFqcn,
            'row_number' => $rowNumber,
            'property' => $property,
            'exception_class' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception' => $exception,
        ]);
    }

    /**
     * @return list<ImportFieldInterface>
     */
    private function getImportableFields(ImportConfig $config): array
    {
        return array_values(array_filter(
            $config->fields,
            static fn (ImportFieldInterface $field): bool => !$field instanceof IgnoredImportField,
        ));
    }
}
