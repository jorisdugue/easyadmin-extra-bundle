<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use DateTime;
use DateTimeInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ImportConfig;
use JorisDugue\EasyAdminExtraBundle\Field\DateImportField;
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use JorisDugue\EasyAdminExtraBundle\Service\Import\ImportEntityHydrator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Throwable;

final class ImportEntityHydratorTest extends TestCase
{
    public function testItHydratesImportableFieldsInPreviewOrder(): void
    {
        $hydrator = new ImportEntityHydrator();

        [$entity, $result] = $hydrator->hydrate(
            ImportHydratorTestEntity::class,
            new ImportConfig([
                TextImportField::new('name', 'Name'),
                IgnoredImportField::new('legacyId')->position(2),
                TextImportField::new('email', 'Email'),
            ]),
            ['Alice', 'alice@example.com'],
            1,
        );

        self::assertTrue($result->success);
        self::assertInstanceOf(ImportHydratorTestEntity::class, $entity);
        self::assertSame('Alice', $entity->name);
        self::assertSame('alice@example.com', $entity->email);
        self::assertNull($entity->legacyId);
    }

    public function testItReportsNonWritablePropertiesAsRowErrors(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Unable to hydrate import row.',
                self::callback(static fn (array $context): bool => ImportHydratorPrivateEntity::class === $context['entity_class']
                    && 3 === $context['row_number']
                    && 'name' === $context['property']
                    && \is_string($context['exception_class'])
                    && \is_string($context['exception_message'])
                    && $context['exception'] instanceof Throwable),
            );
        $hydrator = new ImportEntityHydrator(logger: $logger);

        [$entity, $result] = $hydrator->hydrate(
            ImportHydratorPrivateEntity::class,
            new ImportConfig([TextImportField::new('name', 'Name')]),
            ['Alice'],
            3,
        );

        self::assertNull($entity);
        self::assertFalse($result->success);
        self::assertSame(3, $result->rowNumber);
        self::assertSame(['Property "name" is not writable.'], $result->errors);
    }

    public function testItHydratesDateTimeSetterWithNormalizedDateValue(): void
    {
        $hydrator = new ImportEntityHydrator();
        $date = new DateTime('2026-05-06 08:15:00');

        [$entity, $result] = $hydrator->hydrate(
            ImportHydratorDateEntity::class,
            new ImportConfig([DateImportField::new('createdAt', 'Created at')->setFormat('Y-m-d H:i:s')]),
            [$date],
            1,
        );

        self::assertTrue($result->success);
        self::assertInstanceOf(ImportHydratorDateEntity::class, $entity);
        self::assertSame($date, $entity->createdAt);
    }
}

final class ImportHydratorTestEntity
{
    public ?string $name = null;
    public ?string $email = null;
    public ?string $legacyId = null;
}

final class ImportHydratorPrivateEntity
{
    private ?string $name = null;
}

final class ImportHydratorDateEntity
{
    public ?DateTimeInterface $createdAt = null;

    public function setCreatedAt(?DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
