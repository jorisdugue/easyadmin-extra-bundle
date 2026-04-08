<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Support;

use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Field\FieldInterface;

final class CollectionFactoryCompat
{
    /**
     * @param iterable<FieldInterface|string> $fields
     */
    public function createFieldCollection(iterable $fields): FieldCollection
    {
        $array = \is_array($fields) ? $fields : iterator_to_array($fields, false);

        return new FieldCollection($array);
    }
}
