<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Tests\Service\Import;

use JorisDugue\EasyAdminExtraBundle\Service\Import\TemporaryImportStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class TemporaryImportStorageTest extends TestCase
{
    public function testItStoresAndResolvesTemporaryCsvWithMetadata(): void
    {
        $storage = new TemporaryImportStorage();
        $file = $this->createUploadedFile("Name\nAlice\n");

        $temporaryFile = $storage->store($file, ProductCrudController::class, 'comma', 'UTF-8', true);

        try {
            self::assertFileExists($temporaryFile->path);
            self::assertSame('users.csv', $temporaryFile->clientFilename);
            self::assertSame(ProductCrudController::class, $temporaryFile->crudControllerFqcn);
            self::assertSame('comma', $temporaryFile->separator);
            self::assertSame('UTF-8', $temporaryFile->encoding);
            self::assertTrue($temporaryFile->firstRowContainsHeaders);
            self::assertSame(11, $temporaryFile->size);
            self::assertSame(hash_file('sha256', $temporaryFile->path), $temporaryFile->sha256);

            $resolved = $storage->resolve($temporaryFile->token, ProductCrudController::class);
            self::assertNotNull($resolved);
            self::assertSame($temporaryFile->token, $resolved->token);
            self::assertSame($temporaryFile->path, $resolved->path);
            self::assertSame($temporaryFile->size, $resolved->size);
            self::assertSame($temporaryFile->sha256, $resolved->sha256);
        } finally {
            $storage->delete($temporaryFile->token);
        }
    }

    public function testItRejectsTokenForAnotherCrudControllerWithoutDeletingTheValidFile(): void
    {
        $storage = new TemporaryImportStorage();
        $file = $this->createUploadedFile("Name\nAlice\n");
        $temporaryFile = $storage->store($file, ProductCrudController::class, 'auto', 'UTF-8', false);

        try {
            self::assertNull($storage->resolve($temporaryFile->token, OtherProductCrudController::class));

            $resolved = $storage->resolve($temporaryFile->token, ProductCrudController::class);
            self::assertNotNull($resolved);
            self::assertSame($temporaryFile->token, $resolved->token);
        } finally {
            $storage->delete($temporaryFile->token);
        }
    }

    public function testItRejectsStoredFilesWhenSizeOrHashChanged(): void
    {
        $storage = new TemporaryImportStorage();
        $file = $this->createUploadedFile("Name\nAlice\n");
        $temporaryFile = $storage->store($file, ProductCrudController::class, 'auto', 'UTF-8', true);

        file_put_contents($temporaryFile->path, "Name\nMallory\n");

        self::assertNull($storage->resolve($temporaryFile->token, ProductCrudController::class));
        self::assertFileDoesNotExist($temporaryFile->path);
    }

    public function testItIgnoresInvalidTokensSafely(): void
    {
        $storage = new TemporaryImportStorage();

        self::assertNull($storage->resolve('../invalid-token', ProductCrudController::class));
    }

    private function createUploadedFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'jd_import_storage_');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'users.csv', 'text/csv', null, true);
    }
}

final class ProductCrudController {}

final class OtherProductCrudController {}
