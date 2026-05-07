# Export

## Conceptual Overview

Export is opt-in per EasyAdmin CRUD controller. It uses configured export fields, not EasyAdmin UI fields.

This separation lets you export a stable file contract even when the admin list changes. Export fields control output columns, labels, ordering, transformations, role visibility, and format visibility.

Use export fields when the file is part of an operational workflow: sharing a catalog with another team, producing a finance extract, exporting selected rows for support, or creating a masked export for restricted users.

## Add Export In 5 Minutes

Start with one format and a few fields. Add preview, batch export, sets, and role restrictions only after the basic export works.

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

After adding this:

1. Clear Symfony cache.
2. Open the Product CRUD index page.
3. Use the export action to download CSV/XLSX.
4. Use the preview action to inspect the first rows before download.

## AdminExport Options

The current `#[AdminExport]` constructor options are:

- `filename`
- `formats`
- `fullExport`
- `filteredExport`
- `maxRows`
- `requiredRole`
- `requiredRoles`
- `csvLabel`
- `xlsxLabel`
- `jsonLabel`
- `xmlLabel`
- `allowSpreadsheetFormulas`
- `routeName`
- `routePath`
- `actionDisplay`
- `previewEnabled`
- `previewLimit`
- `previewLabel`
- `batchExport`
- `batchExportLabel`

Supported formats are `csv`, `xlsx`, `json`, and `xml`.

`actionDisplay` accepts `ExportActionDisplay::BUTTONS` or `ExportActionDisplay::DROPDOWN`. The global configuration uses the string values `buttons` and `dropdown`.

Example:

```php
use JorisDugue\EasyAdminExtraBundle\Enum\ExportActionDisplay;

#[AdminExport(
    formats: ['csv', 'xlsx'],
    actionDisplay: ExportActionDisplay::DROPDOWN,
)]
```

## ExportFieldsProviderInterface

`getExportFields()` returns the configured export fields. The optional `$exportSet` argument is used when export sets are configured.

```php
public static function getExportFields(?string $exportSet = null): array
{
    return [
        TextExportField::new('sku', 'SKU'),
        TextExportField::new('name', 'Name'),
    ];
}
```

## Export Field Classes

Current export field classes:

- `BooleanExportField`
- `ChoiceExportField`
- `DateExportField`
- `DateTimeExportField`
- `IntegerExportField`
- `MoneyExportField`
- `NumberExportField`
- `TextExportField`

Common helpers from the export field traits include:

- `setTransformer()`
- `setDefault()`
- `setEnabled()` / `setDisabled()`
- `setLabel()` / `hideLabel()`
- `position()`
- `nullSafe()`
- `setCustomOption()` / `setCustomOptions()`

`MoneyExportField` supports `storedAsCents()`, `euro()`, and `usd()`.

## Choosing Fields

Begin with fields that exist directly on the entity:

```php
TextExportField::new('sku', 'SKU');
TextExportField::new('name', 'Name');
```

Use nested property paths only when you are sure the relation can be read. If an optional relation may be `null`, use `nullSafe()` and optionally `setDefault()`.

```php
TextExportField::new('category.name', 'Category')
    ->nullSafe()
    ->setDefault('Uncategorized');
```

## Field Ordering

Export `position()` controls output order only.

It is not a CSV source column position. Import uses `position()` differently.

Export positions must be zero or greater. Positioned fields are sorted first; unpositioned fields keep declaration order after them.

```php
TextExportField::new('sku', 'SKU')->position(10);
TextExportField::new('name', 'Name')->position(20);
```

## Transformations

Use `setTransformer()` to customize the value after it is read from the entity.

```php
TextExportField::new('sku', 'SKU')
    ->setTransformer(static fn (mixed $value): string => strtoupper((string) $value));
```

The export value pipeline is: read property, apply transformer, apply default for `null`, apply field formatting, normalize to string.

Keep transformers deterministic. They run during export row preparation.

## Preview Export

Set `previewEnabled: true` to add an export preview action.

```php
#[AdminExport(
    formats: ['csv', 'xlsx'],
    previewEnabled: true,
    previewLimit: 20,
)]
```

Preview uses the same configured fields, format, export set, visibility rules, and EasyAdmin query context as the final export. The query is limited to `previewLimit`.

Preview is useful when users need to verify labels, masking, format-specific columns, or filters before downloading the file.

## Batch Export

Batch export is supported through EasyAdmin batch actions.

```php
#[AdminExport(
    formats: ['csv'],
    batchExport: true,
)]
```

Batch export reuses the same export pipeline and selected row identifiers.

> Composite identifiers are a current limitation for batch export.

Use batch export when users need only selected rows, not the whole filtered list.

## Full And Filtered Export

`fullExport` and `filteredExport` control which export scopes are available.

Filtered export uses the current EasyAdmin request context, including filters, search, sorting, and query builder behavior.

If users expect the export to match what they are seeing in the index page, filtered export is the relevant flow.

## Export Sets

Export sets are supported through `ExportSetMetadataProviderInterface`.

```php
use JorisDugue\EasyAdminExtraBundle\Contract\ExportSetMetadataProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;

final class ProductCrudController extends AbstractCrudController implements ExportFieldsProviderInterface, ExportSetMetadataProviderInterface
{
    public static function getExportSetMetadata(): array
    {
        return [
            new ExportSetMetadata('default', 'Default export'),
            new ExportSetMetadata('internal', 'Internal export', ['ROLE_ADMIN']),
        ];
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return match ($exportSet) {
            'internal' => [
                TextExportField::new('sku', 'SKU'),
                TextExportField::new('name', 'Name'),
                MoneyExportField::new('cost', 'Cost')->storedAsCents(),
            ],
            default => [
                TextExportField::new('sku', 'SKU'),
                TextExportField::new('name', 'Name'),
            ],
        };
    }
}
```

If a CRUD implements export set metadata, it must include a `default` set. Set names are normalized and may contain lowercase letters, digits, `_`, and `-`.

Use export sets when different consumers need different columns or permissions, for example “catalog” vs “internal”.

## Events

The current export lifecycle dispatches these synchronous Symfony events:

- `BeforeExportEvent`
- `AfterExportEvent`
- `BeforeExportRowEvent`
- `AfterExportRowEvent`

`BeforeExportRowEvent` can mutate row values, but it must keep the same column count.

## Common Mistakes

- Forgetting the route loader.
- Forgetting `ExportFieldsProviderInterface`.
- Placing the CRUD controller outside `discovery_paths`.
- Using export `position()` as if it were a CSV source column.
- Using an unsupported format.
- Hitting a row count failure on a complex query.
- Expecting EasyAdmin UI fields to be exported automatically.
- Forgetting that `ChoiceExportField::setChoices()` maps raw values to labels during export.

## Troubleshooting

### No Export Action Appears

Check:

- you are on the CRUD index page
- the CRUD has `#[AdminExport]`
- the CRUD implements `ExportFieldsProviderInterface`
- the controller is inside `discovery_paths`
- the current user satisfies `requiredRole` / `requiredRoles`

### Route Is Missing

If a route is missing, clear cache and run:

```bash
php bin/console debug:router
```

Look for routes ending in `export_csv`, `export_xlsx`, `export_preview`, or `export_batch_*`.

### Export Fails On Row Count

If export fails on row count, implement `CustomExportCountQueryBuilderInterface` for grouped queries, `HAVING`, composite identifiers, or other ambiguous count cases.

### Exported Values Are Blank Or Wrong

Check the configured property names and transformers. Export fields read from the entity using the configured property path. If a relation can be missing, use `nullSafe()`.

## Current Limitations

- Async export is not implemented.
- Composite identifiers are not supported for batch export.
- Exporter-specific public configuration beyond `#[AdminExport]` is not currently available.
- The default count strategy rejects ambiguous queries instead of guessing.
