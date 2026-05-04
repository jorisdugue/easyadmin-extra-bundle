<?php

declare(strict_types=1);

namespace JorisDugue\EasyAdminExtraBundle\Exporter;

use DOMDocument;
use DOMException;
use JorisDugue\EasyAdminExtraBundle\Config\ExportFormat;
use JorisDugue\EasyAdminExtraBundle\Contract\ExporterInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportPayload;
use JorisDugue\EasyAdminExtraBundle\Util\ValueStringifier;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Exports a prepared payload as an XML download response.
 *
 * The XML structure is intentionally simple:
 * - a root <export> node
 * - one <item> node per exported row
 * - one child node per exported column
 *
 * Header labels are normalized into safe XML element names.
 */
final readonly class XmlExporter implements ExporterInterface
{
    public function getFormat(): string
    {
        return ExportFormat::XML;
    }

    /**
     * @throws DOMException
     */
    public function export(ExportPayload $payload): Response
    {
        $filename = str_ends_with($payload->filename, '.xml')
            ? $payload->filename
            : $payload->filename . '.xml';
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;

        $root = $document->createElement('export');
        $document->appendChild($root);

        foreach ($payload->rows as $row) {
            $itemNode = $document->createElement('item');
            $root->appendChild($itemNode);

            foreach ($payload->headers as $index => $header) {
                $fieldName = $this->normalizeElementName($header, $index);
                $value = $row[$index] ?? '';

                $fieldNode = $document->createElement($fieldName);
                $fieldNode->appendChild($document->createTextNode(ValueStringifier::stringify($value)));

                $itemNode->appendChild($fieldNode);
            }
        }

        $response = new Response(
            $document->saveXML() ?: '',
            Response::HTTP_OK,
            [
                'Content-Type' => 'application/xml; charset=UTF-8',
            ],
        );

        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $filename,
        );

        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * Converts a column header into a valid XML element name.
     */
    private function normalizeElementName(string $header, int $index): string
    {
        $normalized = trim($header);
        $normalized = strip_tags($normalized);
        $normalized = preg_replace('/[^A-Za-z0-9_\-]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_-');

        if ('' === $normalized) {
            return 'field_' . $index;
        }

        if (preg_match('/^[0-9]/', $normalized)) {
            return 'field_' . $normalized;
        }

        return strtolower($normalized);
    }
}
