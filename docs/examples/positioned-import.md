# Positioned Import Example

Use positioned mapping when the CSV contains skipped columns or has a fixed positional contract.

CSV:

```text
externalId,sku,name,status,price,createdAt
legacy-1001,TSHIRT-BLACK-M,Black T-shirt,draft,1999,2026-05-01
legacy-1002,MUG-WHITE,White mug,published,1290,2026-05-02
```

## Skip A Column Implicitly

Column 1 is skipped because no importable field maps to it.

```php
public static function getImportFields(?string $importSet = null): array
{
    return [
        TextImportField::new('sku', 'SKU')->position(2)->required(),
        TextImportField::new('name', 'Name')->position(3)->required(),
        ChoiceImportField::new('status', 'Status')
            ->setChoices(['draft' => 'Draft', 'published' => 'Published'])
            ->position(4)
            ->required(),
        TextImportField::new('price', 'Price')
            ->position(5)
            ->required(),
        DateImportField::new('createdAt', 'Created at')
            ->setFormat('Y-m-d')
            ->position(6),
    ];
}
```

## Document A Skipped Column

`IgnoredImportField` is optional. Use it only when you want to document the skipped column.

```php
use JorisDugue\EasyAdminExtraBundle\Field\IgnoredImportField;

public static function getImportFields(?string $importSet = null): array
{
    return [
        IgnoredImportField::new('externalId', 'External ID')->position(1),
        TextImportField::new('sku', 'SKU')->position(2)->required(),
        TextImportField::new('name', 'Name')->position(3)->required(),
    ];
}
```

## Rules

- Import positions are 1-based CSV column indexes.
- `position(0)` is invalid.
- Every importable field must have `position()` in positioned mode.
- Unmapped CSV columns are ignored implicitly.
- Duplicate positions are invalid.
- Positions win over headers.
