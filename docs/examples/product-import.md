# Product Import Example

This example imports a generic Product CSV:

```text
sku,name,status,price,createdAt
TSHIRT-BLACK-M,Black T-shirt,draft,1999,2026-05-01
MUG-WHITE,White mug,published,1290,2026-05-02
```

`price` is treated as an application value. There is no dedicated money import field, so this example imports it with `TextImportField`. Your entity setter or application layer should accept the validated value shape you configure.

## CRUD Controller

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
            TextImportField::new('sku', 'SKU')
                ->required()
                ->transformUsing(static fn (?string $value): ?string => null === $value ? null : strtoupper(trim($value))),
            TextImportField::new('name', 'Name')->required(),
            ChoiceImportField::new('status', 'Status')
                ->setChoices([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ])
                ->transformUsing(static fn (?string $value): string => strtolower(trim((string) $value)))
                ->required(),
            TextImportField::new('price', 'Price')
                ->required(),
            DateImportField::new('createdAt', 'Created at')
                ->setFormat('Y-m-d')
                ->required(),
        ];
    }
}
```

## Notes

- `ChoiceImportField` expects `draft` or `published`, not `Draft` or `Published`.
- `DateImportField` validates strings with `setFormat()` and normalizes them to a `DateTime` value for hydration.
- Transformer output feeds field validation; the validated row values are used for hydration and persistence.
- Persistence happens only after confirmation.
