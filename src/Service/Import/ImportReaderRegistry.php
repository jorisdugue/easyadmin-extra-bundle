<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use InvalidArgumentException;
use JorisDugue\EasyAdminExtraBundle\Contract\ImportReaderInterface;

final class ImportReaderRegistry
{
    /**
     * @param iterable<ImportReaderInterface> $readers
     */
    public function __construct(private readonly iterable $readers) {}

    public function get(string $format): ImportReaderInterface
    {
        $format = strtolower(trim($format));

        foreach ($this->readers as $reader) {
            if ($reader->supports($format)) {
                return $reader;
            }
        }

        throw new InvalidArgumentException(\sprintf('Unsupported import format "%s".', $format));
    }
}
