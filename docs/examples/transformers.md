# Transformers

Transformers normalize values before validation and hydration on import, or before formatting on export.

## Trim

```php
TextImportField::new('sku', 'SKU')
    ->transformUsing(static fn (?string $value): ?string => null === $value ? null : trim($value));
```

## Lowercase

```php
TextImportField::new('slug', 'Slug')
    ->transformUsing(static fn (?string $value): ?string => null === $value ? null : strtolower(trim($value)));
```

## Label To Value

`ChoiceImportField` validates keys. Transform labels before validation when needed.

```php
ChoiceImportField::new('status', 'Status')
    ->setChoices([
        'draft' => 'Draft',
        'published' => 'Published',
    ])
    ->transformUsing(static function (?string $value): ?string {
        return match (strtolower(trim((string) $value))) {
            'draft' => 'draft',
            'published' => 'published',
            default => $value,
        };
    });
```

## Empty String To Null

```php
TextImportField::new('description', 'Description')
    ->optional()
    ->transformUsing(static fn (?string $value): ?string => '' === trim((string) $value) ? null : $value);
```

## Date Parsing

`DateImportField` validates strings with `setFormat()` and normalizes them to a `DateTime` value for hydration.

```php
DateImportField::new('createdAt', 'Created at')
    ->setFormat('Y-m-d');
```

A transformer may also return a `DateTimeInterface`.

## Export Transformer

```php
TextExportField::new('sku', 'SKU')
    ->setTransformer(static fn (mixed $value): string => strtoupper((string) $value));
```

## Error Handling

Import transformer exceptions become row/field preview errors. Transformer output feeds field validation; the validated row values are used for hydration and persistence.
