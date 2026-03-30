<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Support;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;

final class CollectionFactoryCompat
{
    public function createFieldCollection(iterable $fields): FieldCollection
    {
        if (method_exists(FieldCollection::class, 'new')) {
            /* @phpstan-ignore-next-line */
            return FieldCollection::new($fields);
        }

        $array = \is_array($fields) ? $fields : iterator_to_array($fields, false);

        return new FieldCollection($array);
    }
}
