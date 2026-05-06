<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Dto;

final readonly class ImportPreviewIssue
{
    public const ERROR = 'error';
    public const WARNING = 'warning';

    public function __construct(
        public string $severity,
        public string $message,
    ) {}

    public function isError(): bool
    {
        return self::ERROR === $this->severity;
    }
}
