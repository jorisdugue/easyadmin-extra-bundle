<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Support;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;

final class CollectionFactoryCompat
{
    /**
     * @param iterable<mixed> $fields
     */
    public function createFieldCollection(iterable $fields): FieldCollection
    {
        $array = \is_array($fields) ? $fields : iterator_to_array($fields, false);

        return new FieldCollection($array);
    }
}
