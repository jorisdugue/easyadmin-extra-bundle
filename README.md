# EasyAdmin Extra Bundle

![CI](https://github.com/jorisdugue/easyadmin-extra-bundle/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-7.4%20%2F%208-black)
![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Packagist Version](https://img.shields.io/packagist/v/jorisdugue/easyadmin-extra-bundle)

Export and CSV import workflows for EasyAdmin 5 applications on Symfony 7.4/8 and PHP 8.2+.

EasyAdmin Extra Bundle adds opt-in data exchange actions to EasyAdmin CRUD controllers. It is designed for back offices that need stable file contracts, previewable CSV imports, selected-row exports, and explicit field definitions that are independent from the fields displayed in the EasyAdmin UI.

## Why Use This?

EasyAdmin fields are UI fields. They describe forms, list pages, formatting, and admin interaction.

Import and export files often need a different contract: hidden columns, fixed labels, stable ordering, normalized values, masked data, or explicit CSV column positions. This bundle keeps those concerns separate: you define configured import/export fields for the file contract, while EasyAdmin fields remain focused on the admin interface.

Use it when you need to:

- export CSV, XLSX, JSON, or XML from EasyAdmin CRUD index pages
- preview exports before download
- export selected rows through EasyAdmin batch actions
- preview CSV imports before persistence
- confirm CSV imports through a revalidated temporary file flow
- map imports by headers, field order, or explicit CSV column position
- transform import/export values without changing the EasyAdmin UI

## Features

- `#[AdminExport]` and `#[AdminImport]` attributes
- dedicated import/export field providers
- CSV/XLSX/JSON/XML export
- CSV import preview and confirmation
- batch export
- export sets
- export field masking, role visibility, and format visibility
- English and French translations

## Installation

```bash
composer require jorisdugue/easyadmin-extra-bundle
```

Symfony Flex should auto-register the bundle. If it does not, register the bundle in your Symfony application as usual.

## Requirements

- PHP `>=8.2`
- Symfony Framework Bundle `^7.4 || ^8.0`
- EasyAdmin Bundle `^5.0`
- Confirmed CSV imports require a Doctrine ORM entity manager for the imported entity.

## Route Loader

Add the route loader:

```yaml
# config/routes/easyadmin_extra.yaml
easyadmin_extra:
  resource: .
  type: jorisdugue_easyadmin_extra.routes
```

> Without this route loader, generated import and export routes are not available.

## Minimal Export Example

```php
use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

#[AdminExport(formats: ['csv', 'xlsx'], previewEnabled: true)]
final class ProductCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return [
            TextExportField::new('sku', 'SKU'),
            TextExportField::new('name', 'Name'),
            MoneyExportField::new('price', 'Price')->storedAsCents(),
        ];
    }
}
```

## Minimal Import Example

```php
use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceImportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateImportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;

#[AdminImport]
final class ProductCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('sku', 'SKU')->required(),
            TextImportField::new('name', 'Name')->required(),
            ChoiceImportField::new('status', 'Status')
                ->setChoices(['draft' => 'Draft', 'published' => 'Published'])
                ->required(),
            DateImportField::new('createdAt', 'Created at')->setFormat('Y-m-d'),
        ];
    }
}
```

## Documentation

- [Getting started](docs/getting-started.md)
- [Export](docs/export.md)
- [Import](docs/import.md)
- [Security](docs/security.md)
- [Configuration](docs/configuration.md)
- [Architecture](docs/architecture.md)
- [Examples](docs/examples/product-import.md)

The documentation site uses Zensical and is configured by [zensical.toml](zensical.toml):

```bash
zensical serve
zensical build
```

## License

MIT
