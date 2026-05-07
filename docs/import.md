# Import

## Conceptual Overview

CSV import is opt-in per EasyAdmin CRUD controller. It uses configured import fields, not EasyAdmin UI fields.

Import fields define the CSV contract: accepted columns, required values, transformations, validation, and the entity properties to hydrate.

Use CSV import when an administrator needs to review a file before it writes data. The preview step is part of the design, not an optional extra.

## Import Lifecycle

```text
upload
-> validate upload and CSV content
-> preview configured fields
-> store temporary file for confirmation
-> confirmation
-> revalidate from temporary file
-> hydrate new entities
-> persist all-or-nothing
```

Preview does not persist data. Confirmation is the persistence step.

## Add CSV Import In 10 Minutes

Start with a small import and header mapping. Once that works, add positioned mapping or transformers only if your CSV contract needs them.

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

`#[AdminImport]` currently supports `routeName` and `routePath`.

Example CSV for the controller above:

```text
SKU,Name,Status,Created at
TSHIRT-BLACK-M,Black T-shirt,draft,2026-05-01
MUG-WHITE,White mug,published,2026-05-02
```

When uploading this file, enable “first row contains headers”.

## Import Field Classes

Current import field classes:

- `TextImportField`
- `ChoiceImportField`
- `DateImportField`
- `IgnoredImportField`

Common helpers:

- `required()` / `optional()`
- `position()`
- `transformUsing()`
- `setCustomOption()`

`ChoiceImportField` validates against choice keys.

`DateImportField` validates strings with `setFormat()` and normalizes them to a `DateTime` value for hydration. A transformer may also return a `DateTimeInterface`.

## Mapping Modes

Choose one mapping mode per import. Most first imports should use header mapping because it is easiest to inspect and least sensitive to column order.

### 1. Header Mapping

Use when the CSV has a header row and no configured field uses `position()`.

```text
CSV header "SKU"    -> TextImportField::new('sku', 'SKU')
CSV header "Name"   -> TextImportField::new('name', 'Name')
CSV header "Status" -> ChoiceImportField::new('status', 'Status')
```

Unknown CSV columns are ignored with a preview warning. Missing required columns are preview errors.

Header labels come from the configured import field label. In the example above, `TextImportField::new('sku', 'SKU')` matches the `SKU` CSV header.

### 2. Sequential Mapping

Use when the CSV has no header row and no configured field uses `position()`.

```text
configured field 1 -> CSV column 1
configured field 2 -> CSV column 2
configured field 3 -> CSV column 3
```

Extra CSV columns are ignored with a preview warning.

Sequential mapping is simple but less self-documenting. If the CSV file changes column order, values will map differently.

### 3. Positioned Mapping

Use when the CSV contract is positional or contains columns you want to skip.

For import fields, `position()` is a 1-based CSV column index.

```php
public static function getImportFields(?string $importSet = null): array
{
    return [
        TextImportField::new('sku', 'SKU')->position(2)->required(),
        TextImportField::new('name', 'Name')->position(3)->required(),
        ChoiceImportField::new('status', 'Status')
            ->setChoices(['draft' => 'Draft', 'published' => 'Published'])
            ->position(4),
    ];
}
```

If any configured field defines `position()`, positioned mapping is enabled:

- positions win over headers
- every importable configured field must define `position()`
- `position(0)` is invalid
- duplicate positions are invalid
- unmapped CSV columns are skipped implicitly

Positioned mapping is the right choice when a vendor file includes technical columns you do not persist, such as an external ID.

## Skipped Columns And IgnoredImportField

Skipped columns do not require `IgnoredImportField`.

Use `IgnoredImportField` only when documenting an intentionally ignored column improves readability:

```php
IgnoredImportField::new('externalId', 'External ID')->position(1);
```

Ignored fields are hidden from preview output and are not hydrated onto the entity.

## Required And Optional Values

```php
TextImportField::new('sku', 'SKU')->required();
TextImportField::new('description', 'Description')->optional();
```

Empty required values are preview errors. Optional empty values are passed through as `null`.

Required checks happen after transformation. This lets you trim values before deciding whether they are empty.

## Transformers

Use `transformUsing()` to normalize the raw CSV value before validation.

```php
TextImportField::new('sku', 'SKU')
    ->transformUsing(static fn (?string $value): ?string => null === $value ? null : strtoupper(trim($value)));
```

Transformer exceptions become row/field preview errors. Transformer output feeds field validation; the validated row values produced by the preview pipeline are used for hydration and persistence during confirmation.

Common transformer uses:

- trim whitespace
- lowercase or uppercase technical values
- convert labels to choice keys
- turn empty strings into `null`
- return a `DateTimeInterface` for date fields

## Choice Values

`ChoiceImportField` validates against keys, not human labels.

```php
ChoiceImportField::new('status', 'Status')
    ->setChoices(['draft' => 'Draft', 'published' => 'Published']);
```

The CSV value must be `draft` or `published`. If your CSV contains `Draft`, transform it to `draft` first.

## Preview And Confirmation

Preview validates enough to show configured fields and issues. When there are no blocking errors, the uploaded CSV is copied to temporary storage and a confirmation token is rendered.

Confirmation revalidates from the temporary file. It does not trust hidden preview row payloads.

Persistence is all-or-nothing through Doctrine transaction handling. The bundle validates non-nullable Doctrine fields where practical before flushing.

If confirmation fails, no successful partial import should be assumed. Fix the CSV or configuration and run the preview/confirmation flow again.

## Hydration

During confirmation, the bundle creates new entity instances using the CRUD controller’s `getEntityFqcn()` and writes values with Symfony PropertyAccess.

Configured import field property names must be writable on the entity, either through public properties or setters supported by PropertyAccess.

## Common Mistakes

- Leaving “first row contains headers” unchecked when the CSV has headers.
- Using `position(0)`; import positions start at `1`.
- Reusing the same `position()` twice.
- Mixing positioned and unpositioned importable fields.
- Sending choice labels instead of choice keys.
- Omitting a non-nullable Doctrine field from the import configuration.
- Expecting preview to persist rows.

## Troubleshooting

### Import Action Does Not Appear

Check:

- the CRUD has `#[AdminImport]`
- the controller is inside `discovery_paths`
- you are on the CRUD index page
- the route loader is registered

### Preview Shows Headers As Data

Enable “first row contains headers” when your CSV has a header row.

### Required Column Is Missing

In header mapping, the CSV header must match the configured field label. For example, `TextImportField::new('sku', 'SKU')` expects `SKU`.

### Mixed Mapping Error

If one importable field uses `position()`, every importable field must use `position()`. Either remove all positions or add positions to all importable fields.

### Invalid Choice Value

`ChoiceImportField` validates keys. Use a transformer if the CSV contains labels.

If confirmation fails with an invalid token, upload and preview the CSV again. Tokens expire after 30 minutes and fail if the temporary file is missing, changed, or belongs to another CRUD controller.

If hydration fails, check that each configured field property is writable on the entity and that transformed values match the property type your entity expects.

## Current Limitations

- Only CSV import is currently supported.
- Import creates new entity instances.
- Import storage path, token TTL, preview row limit, maximum column count, and upload size are not public configuration options.
- Import set selection exists in the provider signature, but no public UI flow for selecting import sets is documented here.
