# Getting Started

This page gets you from installation to a first working export or CSV import.

The bundle does not change your EasyAdmin CRUD configuration automatically. You opt in per CRUD controller with an attribute, then provide a small list of configured import/export fields. Those configured fields are the file contract.

## First-Use Path

1. Install the package.
2. Ensure the Symfony bundle is registered.
3. Register the route loader.
4. Configure `discovery_paths` only if your dashboards or CRUD controllers are outside `src/Controller`.
5. Add `#[AdminExport]` or `#[AdminImport]`.
6. Implement the matching provider interface.
7. Clear cache and check generated routes.

Most setup problems come from one of three places: the route loader is missing, the CRUD controller is outside `discovery_paths`, or the provider interface is missing.

## 1. Install

```bash
composer require jorisdugue/easyadmin-extra-bundle
```

Symfony Flex should auto-register the bundle. If your application does not use Flex, register the bundle manually.

You can confirm the bundle is installed by checking that Composer installed `jorisdugue/easyadmin-extra-bundle` and that Symfony sees `JorisDugueEasyAdminExtraBundle`.

## 2. Register The Route Loader

```yaml
# config/routes/easyadmin_extra.yaml
easyadmin_extra:
  resource: .
  type: jorisdugue_easyadmin_extra.routes
```

The route loader generates import/export routes for discovered EasyAdmin dashboards and CRUD controllers.

After adding the file, clear cache:

```bash
php bin/console cache:clear
```

## 3. Configure Discovery Paths If Needed

By default, the bundle scans:

```text
%kernel.project_dir%/src/Controller
```

If your admin controllers live elsewhere:

```yaml
# config/packages/easyadmin_extra.yaml
joris_dugue_easyadmin_extra:
  discovery_paths:
    - '%kernel.project_dir%/src/Controller'
    - '%kernel.project_dir%/src/Admin'
```

> Only add custom paths when needed. If your dashboards and CRUD controllers already live in `src/Controller`, the default is enough.

## 4. Add Your First Export

The quickest export is a CRUD controller with `#[AdminExport]` and `ExportFieldsProviderInterface`.

```php
use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

#[AdminExport(formats: ['csv'], previewEnabled: true)]
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

Export fields are not EasyAdmin fields. They are the columns in the exported file.

After clearing cache, visit the Product CRUD index page. You should see an export action. Because `previewEnabled: true` is set, you should also see an export preview action.

## 5. Add Your First CSV Import

CSV import follows the same pattern: `#[AdminImport]` plus `ImportFieldsProviderInterface`.

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

Import fields are the configured CSV columns. They control validation, transformation, and hydration.

With the example above, a CSV with headers can look like this:

```text
SKU,Name,Status,Created at
TSHIRT-BLACK-M,Black T-shirt,draft,2026-05-01
MUG-WHITE,White mug,published,2026-05-02
```

When previewing this file, enable the “first row contains headers” option. If that option is not enabled, the first line will be treated as data.

## 6. Clear Cache And Check Routes

After adding attributes or changing discovery paths, clear Symfony cache and inspect routes:

```bash
php bin/console cache:clear
php bin/console debug:router | grep export
php bin/console debug:router | grep import
```

On Windows PowerShell:

```bash
php bin/console debug:router | Select-String export
php bin/console debug:router | Select-String import
```

Expected route names depend on your dashboard and CRUD route names. Look for routes containing `export`, `export_preview`, `export_batch`, `import_preview`, or `import_confirm`.

## How The Two Flows Differ

Export creates a response immediately from the current EasyAdmin context. It uses configured export fields and can respect filters, search, sorting, export sets, roles, and selected rows.

CSV import is intentionally two-step:

```text
upload CSV -> preview validation -> confirmation -> revalidation -> persistence
```

Preview lets the user inspect mapped rows and validation errors. Confirmation re-reads the temporary CSV file and persists only after validation succeeds.

## Troubleshooting

### Action Not Visible

Check that:

- you are on the EasyAdmin CRUD index page
- the CRUD has `#[AdminExport]` or `#[AdminImport]`
- the matching provider interface is implemented
- the controller is discovered through `discovery_paths`
- export role restrictions are not hiding the action

### Route Not Found

Check that the route loader file exists and uses:

```yaml
type: jorisdugue_easyadmin_extra.routes
```

Then clear cache.

If the action is visible but clicking it fails with a missing route, the route loader is usually missing or cache has not been refreshed.

### CRUD Not Discovered

Move the CRUD controller under `src/Controller` or add its directory to `discovery_paths`.

Also make sure the class can be autoloaded. The route loader discovers PHP classes from files and then checks the attributes on those classes.

### Missing Provider Interface

`#[AdminExport]` requires `ExportFieldsProviderInterface`.

`#[AdminImport]` requires `ImportFieldsProviderInterface` when the import configuration is resolved.

### Import Preview Has Wrong Columns

Check the CSV mapping mode:

- If the CSV has headers, enable “first row contains headers”.
- If the CSV has no headers, fields map by declaration order.
- If you use `position()`, positions are 1-based CSV column indexes and win over headers.
