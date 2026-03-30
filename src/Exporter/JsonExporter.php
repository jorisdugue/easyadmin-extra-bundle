<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exporter;

use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JsonException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class JsonExporter
{
    public function export(ExportPayload $payload): StreamedResponse
    {
        $filename = str_ends_with($payload->filename, '.json')
            ? $payload->filename
            : $payload->filename . '.json';

        return new StreamedResponse(function () use ($payload): void {
            $handle = fopen('php://output', 'w');

            if (false === $handle) {
                throw new RuntimeException('Unable to open php://output.');
            }

            try {
                if (false === fwrite($handle, '[')) {
                    throw new RuntimeException('Unable to write JSON opening bracket.');
                }

                $first = true;
                $index = 0;

                foreach ($payload->rows as $row) {
                    $object = $this->normalizeRow($payload->properties, $row);
                    $json = json_encode(
                        $object,
                        \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR
                    );

                    if (!$first && false === fwrite($handle, ',')) {
                        throw new RuntimeException('Unable to write JSON separator.');
                    }

                    if (false === fwrite($handle, $json)) {
                        throw new RuntimeException('Unable to write JSON row.');
                    }

                    $first = false;

                    if (0 === (++$index % 100)) {
                        if (\function_exists('ob_flush')) {
                            @ob_flush();
                        }

                        flush();
                    }
                }

                if (false === fwrite($handle, ']')) {
                    throw new RuntimeException('Unable to write JSON closing bracket.');
                }
            } catch (JsonException $e) {
                throw new RuntimeException('JSON encoding failed.', 0, $e);
            } finally {
                fclose($handle);
            }
        }, Response::HTTP_OK, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => new ResponseHeaderBag()->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $filename
            ),
        ]);
    }

    /**
     * @param list<string> $properties
     * @param array<int|string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function normalizeRow(array $properties, array $row): array
    {
        $row = array_values($row);
        $normalized = [];

        foreach ($properties as $index => $key) {
            $normalized[$key] = $row[$index] ?? null;
        }

        return $normalized;
    }
}
