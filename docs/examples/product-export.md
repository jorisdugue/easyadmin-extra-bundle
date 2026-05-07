# Product Export Example

This example exports products as CSV and XLSX with a preview action.

```php
use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

#[AdminExport(
    filename: 'products',
    formats: ['csv', 'xlsx'],
    previewEnabled: true,
    batchExport: true,
)]
final class ProductCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return [
            TextExportField::new('sku', 'SKU')->position(10),
            TextExportField::new('name', 'Name')->position(20),
            ChoiceExportField::new('status', 'Status')
                ->setChoices([
                    'draft' => 'Draft',
                    'published' => 'Published',
                ])
                ->position(30),
            MoneyExportField::new('price', 'Price')
                ->storedAsCents()
                ->position(40),
            DateTimeExportField::new('createdAt', 'Created at')
                ->setFormat('Y-m-d H:i:s')
                ->position(50),
        ];
    }
}
```

Export positions control output order. Preview and batch export reuse the same configured fields.
