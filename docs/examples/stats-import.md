# Stats Import Example

This is a real-world style example. The main documentation uses generic Product examples; this page shows a Stats-shaped CSV.

CSV:

```text
id,uuid,typeAction,lang,platform,createdAt
1,7b40b4e4-3f8e-4ca5-a5ed-111111111111,create,fr,web,2026-05-06
2,89eec593-0417-4f58-a45b-222222222222,update,en,mobile,2026-05-07
```

The external `id` column is skipped. The imported fields are `uuid`, `typeAction`, `lang`, `platform`, and `createdAt`.

```php
use App\Entity\Stats;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminImport;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\ChoiceImportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateImportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextImportField;

#[AdminImport]
final class StatsCrudController extends AbstractCrudController implements ImportFieldsProviderInterface
{
    public static function getEntityFqcn(): string
    {
        return Stats::class;
    }

    public static function getImportFields(?string $importSet = null): array
    {
        return [
            TextImportField::new('uuid', 'UUID')->position(2)->required(),
            ChoiceImportField::new('typeAction', 'Type action')
                ->setChoices([
                    'create' => 'Create',
                    'update' => 'Update',
                    'delete' => 'Delete',
                ])
                ->position(3)
                ->required(),
            ChoiceImportField::new('lang', 'Language')
                ->setChoices([
                    'fr' => 'French',
                    'en' => 'English',
                ])
                ->position(4)
                ->required(),
            ChoiceImportField::new('platform', 'Platform')
                ->setChoices([
                    'web' => 'Web',
                    'mobile' => 'Mobile',
                ])
                ->position(5)
                ->required(),
            DateImportField::new('createdAt', 'Created at')
                ->setFormat('Y-m-d')
                ->position(6)
                ->required(),
        ];
    }
}
```

Column 1 is ignored implicitly because positioned mapping is used and no importable field maps to that CSV column.
