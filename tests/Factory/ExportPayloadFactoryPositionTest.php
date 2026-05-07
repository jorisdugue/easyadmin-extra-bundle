<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Factory;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportRowMapperInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use JorisDugue\EasyAdminExtraBundle\Factory\ExportPayloadFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportCountResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldFormatResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\ExportFieldValueResolver;
use JorisDugue\EasyAdminExtraBundle\Resolver\FilenameResolver;
use JorisDugue\EasyAdminExtraBundle\Service\PropertyValueReader;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

final class ExportPayloadFactoryPositionTest extends TestCase
{
    private ExportPayloadFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new ExportPayloadFactory(
            new ExportFieldValueResolver(new PropertyValueReader()),
            new FilenameResolver(),
            new ExportFieldFormatResolver(),
            new ExportCountResolver(),
            new EventDispatcher(),
        );
    }

    public function testCreateSortsHeadersPropertiesAndRowsByAscendingPosition(): void
    {
        $config = new ExportConfig(
            filename: 'users_{format}',
            fields: [
                TextExportField::new('email', 'Email')->position(20),
                TextExportField::new('name', 'Name')->position(10),
                TextExportField::new('phone', 'Phone'),
            ],
            formats: ['csv'],
            maxRows: null,
        );

        $context = new ExportContext(
            format: 'csv',
            scope: 'all',
            generatedAt: new DateTimeImmutable('2026-03-31 10:00:00'),
            user: null,
            entityName: 'user',
        );

        $entity = new class {
            public string $name = 'John';
            public string $email = 'john@example.com';
            public string $phone = '0102030405';
        };

        $crudController = new class {};

        $qb = $this->createQueryBuilderStub([$entity]);

        $payload = $this->factory->create($crudController, $qb, $config, $context);

        self::assertSame(['Name', 'Email', 'Phone'], $payload->headers);
        self::assertSame(['name', 'email', 'phone'], $payload->properties);
        self::assertSame(
            [['John', 'john@example.com', '0102030405']],
            iterator_to_array($payload->rows, false),
        );
    }

    public function testCreateKeepsDeclarationOrderWhenPositionsAreEqual(): void
    {
        $config = new ExportConfig(
            filename: 'users_{format}',
            fields: [
                TextExportField::new('email', 'Email')->position(10),
                TextExportField::new('name', 'Name')->position(10),
                TextExportField::new('phone', 'Phone'),
            ],
            formats: ['csv'],
            maxRows: null,
        );

        $context = new ExportContext(
            format: 'csv',
            scope: 'all',
            generatedAt: new DateTimeImmutable('2026-03-31 10:00:00'),
            user: null,
            entityName: 'user',
        );

        $entity = new class {
            public string $email = 'john@example.com';
            public string $name = 'John';
            public string $phone = '0102030405';
        };

        $crudController = new class {};

        $qb = $this->createQueryBuilderStub([$entity]);

        $payload = $this->factory->create($crudController, $qb, $config, $context);

        self::assertSame(['Email', 'Name', 'Phone'], $payload->headers);
        self::assertSame(['email', 'name', 'phone'], $payload->properties);
        self::assertSame(
            [['john@example.com', 'John', '0102030405']],
            iterator_to_array($payload->rows, false),
        );
    }

    public function testCreateKeepsFieldsWithoutPositionAtTheEndInDeclarationOrder(): void
    {
        $config = new ExportConfig(
            filename: 'users_{format}',
            fields: [
                TextExportField::new('phone', 'Phone'),
                TextExportField::new('email', 'Email')->position(20),
                TextExportField::new('name', 'Name')->position(10),
                TextExportField::new('city', 'City'),
            ],
            formats: ['csv'],
            maxRows: null,
        );

        $context = new ExportContext(
            format: 'csv',
            scope: 'all',
            generatedAt: new DateTimeImmutable('2026-03-31 10:00:00'),
            user: null,
            entityName: 'user',
        );

        $entity = new class {
            public string $name = 'John';
            public string $email = 'john@example.com';
            public string $phone = '0102030405';
            public string $city = 'Paris';
        };

        $crudController = new class {};

        $qb = $this->createQueryBuilderStub([$entity]);

        $payload = $this->factory->create($crudController, $qb, $config, $context);

        self::assertSame(['Name', 'Email', 'Phone', 'City'], $payload->headers);
        self::assertSame(['name', 'email', 'phone', 'city'], $payload->properties);
        self::assertSame(
            [['John', 'john@example.com', '0102030405', 'Paris']],
            iterator_to_array($payload->rows, false),
        );
    }

    public function testCreateAppliesPositionToCustomMappedRows(): void
    {
        $config = new ExportConfig(
            filename: 'users_{format}',
            fields: [
                TextExportField::new('email', 'Email')->position(20),
                TextExportField::new('name', 'Name')->position(10),
                TextExportField::new('phone', 'Phone'),
            ],
            formats: ['csv'],
            maxRows: null,
        );

        $context = new ExportContext(
            format: 'csv',
            scope: 'all',
            generatedAt: new DateTimeImmutable('2026-03-31 10:00:00'),
            user: null,
            entityName: 'user',
        );

        $entity = new class {
            public string $name = 'John';
            public string $email = 'john@example.com';
            public string $phone = '0102030405';
        };

        $crudController = new class implements CustomExportRowMapperInterface {
            public function mapExportRow(object $entity): array
            {
                return [
                    'phone' => $entity->phone,
                    'email' => $entity->email,
                    'name' => $entity->name,
                ];
            }
        };

        $qb = $this->createQueryBuilderStub([$entity]);

        $payload = $this->factory->create($crudController, $qb, $config, $context);

        self::assertSame(['Name', 'Email', 'Phone'], $payload->headers);
        self::assertSame(['name', 'email', 'phone'], $payload->properties);
        self::assertSame(
            [['John', 'john@example.com', '0102030405']],
            iterator_to_array($payload->rows, false),
        );
    }

    /**
     * @throws Exception
     */
    public function testCreateThrowsWhenCustomMappedRowMissesExpectedKey(): void
    {
        $config = new ExportConfig(
            filename: 'users_{format}',
            fields: [
                TextExportField::new('email', 'Email')->position(20),
                TextExportField::new('name', 'Name')->position(10),
            ],
            formats: ['csv'],
            maxRows: null,
        );

        $context = new ExportContext(
            format: 'csv',
            scope: 'all',
            generatedAt: new DateTimeImmutable('2026-03-31 10:00:00'),
            user: null,
            entityName: 'user',
        );

        $entity = new class {
            public string $name = 'John';
            public string $email = 'john@example.com';
        };

        $crudController = new class implements CustomExportRowMapperInterface {
            public function mapExportRow(object $entity): array
            {
                return [
                    'email' => $entity->email,
                ];
            }
        };

        $qb = $this->createQueryBuilderStub([$entity]);

        $payload = $this->factory->create($crudController, $qb, $config, $context);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Custom export row mapper is missing key "name".');
        iterator_to_array($payload->rows, false);
    }

    /**
     * @param list<object> $entities
     *
     * @throws Exception
     */
    private function createQueryBuilderStub(array $entities): QueryBuilder
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $query = $this->createStub(Query::class);
        $query
            ->method('toIterable')
            ->willReturn($entities);

        $qb = $this->createStub(QueryBuilder::class);
        $qb
            ->method('getQuery')
            ->willReturn($query);

        $qb
            ->method('getEntityManager')
            ->willReturn($entityManager);

        return $qb;
    }
}
