<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportReadOptions
{
    public function __construct(
        public string $format,
        public string $separator,
        public string $encoding,
        public bool $firstRowContainsHeaders,
    ) {}

    public static function csv(
        string $separator,
        string $encoding,
        bool $firstRowContainsHeaders,
    ): self {
        return new self('csv', $separator, $encoding, $firstRowContainsHeaders);
    }
}
