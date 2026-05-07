<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Factory;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Exception\InvalidImportConfigurationException;
use JorisDugue\EasyAdminExtraBundle\Factory\ImportConfigFactory;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ImportConfigFactoryTest extends TestCase
{
    public function testItResolvesImportFieldsFromProvider(): void
    {
        $config = (new ImportConfigFactory())->create(ImportProviderCrudController::class, 'default');

        self::assertSame('default', $config->importSet);
        self::assertCount(3, $config->fields);
        self::assertSame('phone', $config->fields[0]->getAsDto()->getProperty());
    }

    public function testItPreservesDeclarationOrderBecauseImportPositionMeansCsvColumn(): void
    {
        $config = (new ImportConfigFactory())->create(ImportProviderCrudController::class);

        self::assertSame(
            ['phone', 'email', 'name'],
            array_map(static fn (ImportFieldInterface $field): ?string => $field->getAsDto()->getProperty(), $config->fields),
        );
    }

    public function testItReportsMissingImportFieldsProvider(): void
    {
        $this->expectException(InvalidImportConfigurationException::class);
        $this->expectExceptionMessage('must implement');

        (new ImportConfigFactory())->create(MissingImportProviderCrudController::class);
    }

    public function testItRejectsDuplicateImportPositions(): void
    {
        $this->expectException(InvalidImportConfigurationException::class);
        $this->expectExceptionMessage('Duplicate import CSV column position 6 configured for fields "createdAt" and "createdAt".');

        (new ImportConfigFactory())->create(DuplicatePositionImportProviderCrudController::class);
    }

    public function testItAcceptsValidSparsePositions(): void
    {
        $config = (new ImportConfigFactory())->create(SparsePositionImportProviderCrudController::class);

        self::assertSame([2, 3, 4, 6], array_map(static fn (ImportFieldInterface $field): ?int => $field->getAsDto()->getPosition(), $config->fields));
    }
}

#[AdminImport]
final class ImportProviderCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('phone', 'Phone'),
            TextImportField::new('email', 'Email')->position(10),
            TextImportField::new('name', 'Name')->position(20),
        ];
    }
}

#[AdminImport]
final class MissingImportProviderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }
}

#[AdminImport]
final class DuplicatePositionImportProviderCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('createdAt', 'Created at')->position(6),
            TextImportField::new('createdAt', 'Created at')->position(6),
        ];
    }
}

#[AdminImport]
final class SparsePositionImportProviderCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return stdClass::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('uuid', 'UUID')->position(2),
            TextImportField::new('typeAction', 'Type action')->position(3),
            TextImportField::new('lang', 'Language')->position(4),
            TextImportField::new('createdAt', 'Created at')->position(6),
        ];
    }
}
