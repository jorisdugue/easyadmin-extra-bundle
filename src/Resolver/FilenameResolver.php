<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Resolver;

use JorisDugue\EasyAdminExtraBundle\Config\ExportConfig;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportContext;
use RuntimeException;

final class FilenameResolver
{
    public function resolve(object $crudController, ExportConfig $config, ExportContext $context): string
    {
        if (method_exists($crudController, 'buildExportFilename')) {
            $value = $crudController->buildExportFilename($context);
            if (!\is_string($value) || '' === trim($value)) {
                throw new RuntimeException('La méthode buildExportFilename() doit retourner une chaîne non vide.');
            }

            return $this->sanitize($value);
        }

        $username = $context->user?->getUserIdentifier() ?? 'anon';

        $resolved = strtr($config->filename, [
            '{date}' => $context->generatedAt->format('Y-m-d'),
            '{time}' => $context->generatedAt->format('H-i-s'),
            '{datetime}' => $context->generatedAt->format('Y-m-d_H-i-s'),
            '{entity}' => $context->entityName,
            '{user}' => $username,
            '{scope}' => $context->scope,
            '{format}' => $context->format,
        ]);

        return $this->sanitize($resolved);
    }

    private function sanitize(string $value): string
    {
        $value = trim($value);
        $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value) ?? 'export';
        $value = trim($value, '._-');

        return '' === $value ? 'export' : $value;
    }
}
