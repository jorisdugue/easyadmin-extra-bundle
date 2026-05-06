<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Dto\TemporaryImportFile;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TemporaryImportStorage
{
    private const TTL_SECONDS = 1800;
    private const DIRECTORY = 'joris_dugue_easyadmin_extra_import';

    public function store(
        UploadedFile $file,
        string $crudControllerFqcn,
        string $separator,
        string $encoding,
        bool $firstRowContainsHeaders,
    ): TemporaryImportFile {
        $this->ensureDirectoryExists();

        $token = bin2hex(random_bytes(32));
        $path = $this->csvPath($token);
        $metadataPath = $this->metadataPath($token);

        if (!@copy($file->getPathname(), $path)) {
            throw new RuntimeException('The uploaded CSV file could not be stored for confirmation.');
        }

        $size = @filesize($path);
        $sha256 = @hash_file('sha256', $path);
        if (!\is_int($size) || !\is_string($sha256)) {
            $this->delete($token);

            throw new RuntimeException('The uploaded CSV file could not be stored for confirmation.');
        }

        $metadata = [
            'createdAt' => time(),
            'clientFilename' => $this->sanitizeFilename($file->getClientOriginalName()),
            'crudControllerFqcn' => $crudControllerFqcn,
            'separator' => $separator,
            'encoding' => $encoding,
            'firstRowContainsHeaders' => $firstRowContainsHeaders,
            'size' => $size,
            'sha256' => $sha256,
        ];

        $encodedMetadata = json_encode($metadata, \JSON_THROW_ON_ERROR);
        if (false === @file_put_contents($metadataPath, $encodedMetadata, \LOCK_EX)) {
            $this->delete($token);

            throw new RuntimeException('The import confirmation metadata could not be stored.');
        }

        return new TemporaryImportFile($token, $path, $metadata['clientFilename'], $crudControllerFqcn, $separator, $encoding, $firstRowContainsHeaders, $size, $sha256);
    }

    public function resolve(string $token, string $crudControllerFqcn): ?TemporaryImportFile
    {
        if (!$this->isValidToken($token)) {
            return null;
        }

        $path = $this->csvPath($token);
        $metadataPath = $this->metadataPath($token);

        if (!is_file($path) || !is_file($metadataPath)) {
            $this->delete($token);

            return null;
        }

        $metadata = $this->readMetadata($metadataPath);
        if (null === $metadata) {
            $this->delete($token);

            return null;
        }

        $createdAt = $metadata['createdAt'] ?? null;
        if (!\is_int($createdAt) || $createdAt + self::TTL_SECONDS < time()) {
            $this->delete($token);

            return null;
        }

        if (($metadata['crudControllerFqcn'] ?? null) !== $crudControllerFqcn) {
            return null;
        }

        if (!$this->hasValidOptionMetadata($metadata)) {
            $this->delete($token);

            return null;
        }

        $clientFilename = $metadata['clientFilename'];
        $storedCrudControllerFqcn = $metadata['crudControllerFqcn'];
        $separator = $metadata['separator'];
        $encoding = $metadata['encoding'];
        $firstRowContainsHeaders = $metadata['firstRowContainsHeaders'];
        $size = $metadata['size'];
        $sha256 = $metadata['sha256'];
        if (
            !\is_string($clientFilename)
            || !\is_string($separator)
            || !\is_string($encoding)
            || !\is_bool($firstRowContainsHeaders)
            || !\is_int($size)
            || !\is_string($sha256)
        ) {
            $this->delete($token);

            return null;
        }

        if (!$this->fileMatchesStoredMetadata($path, $size, $sha256)) {
            $this->delete($token);

            return null;
        }

        return new TemporaryImportFile(
            $token,
            $path,
            $clientFilename,
            $storedCrudControllerFqcn,
            $separator,
            $encoding,
            $firstRowContainsHeaders,
            $size,
            $sha256,
        );
    }

    public function delete(string $token): void
    {
        if (!$this->isValidToken($token)) {
            return;
        }

        @unlink($this->csvPath($token));
        @unlink($this->metadataPath($token));
    }

    private function directory(): string
    {
        return rtrim(sys_get_temp_dir(), \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . self::DIRECTORY;
    }

    private function ensureDirectoryExists(): void
    {
        $directory = $this->directory();
        if (is_dir($directory)) {
            return;
        }

        if (!@mkdir($directory, 0o700, true) && !is_dir($directory)) {
            throw new RuntimeException('The import confirmation storage directory could not be created.');
        }
    }

    private function csvPath(string $token): string
    {
        return $this->directory() . \DIRECTORY_SEPARATOR . $token . '.csv';
    }

    private function metadataPath(string $token): string
    {
        return $this->directory() . \DIRECTORY_SEPARATOR . $token . '.json';
    }

    private function isValidToken(string $token): bool
    {
        return 1 === preg_match('/\A[a-f0-9]{64}\z/', $token);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        return '' === trim($filename) ? 'uploaded.csv' : $filename;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $metadataPath): ?array
    {
        $contents = @file_get_contents($metadataPath);
        if (!\is_string($contents)) {
            return null;
        }

        try {
            $metadata = json_decode($contents, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!\is_array($metadata)) {
            return null;
        }

        $normalized = [];
        foreach ($metadata as $key => $value) {
            if (\is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function hasValidOptionMetadata(array $metadata): bool
    {
        return \is_string($metadata['clientFilename'] ?? null)
            && \is_string($metadata['crudControllerFqcn'] ?? null)
            && \is_string($metadata['separator'] ?? null)
            && \is_string($metadata['encoding'] ?? null)
            && \is_bool($metadata['firstRowContainsHeaders'] ?? null)
            && \is_int($metadata['size'] ?? null)
            && \is_string($metadata['sha256'] ?? null);
    }

    private function fileMatchesStoredMetadata(string $path, int $size, string $sha256): bool
    {
        $currentSize = @filesize($path);
        if ($currentSize !== $size) {
            return false;
        }

        $currentSha256 = @hash_file('sha256', $path);

        return \is_string($currentSha256) && hash_equals($sha256, $currentSha256);
    }
}
