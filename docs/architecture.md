# Architecture

This page is for contributors and advanced users who need to understand how the bundle is put together.

## Design Overview

The bundle adds import/export operations around EasyAdmin instead of changing EasyAdmin’s field system.

EasyAdmin fields remain UI-focused. Import and export fields are data-contract focused. This keeps file formats stable even when admin pages change.

## Route Discovery

`AdminOperationRouteLoader` is the route loader behind:

```text
jorisdugue_easyadmin_extra.routes
```

It scans configured `discovery_paths`, finds EasyAdmin dashboards, and finds CRUD controllers marked with `#[AdminExport]` or `#[AdminImport]`.

`AdminExportRouteLoader` extends `AdminOperationRouteLoader` as a backwards-compatibility wrapper.

## Action Extensions

EasyAdmin actions are added by:

- `ExportActionExtension`
- `ImportActionExtension`

They run on EasyAdmin CRUD index pages. Export actions respect export configuration, export sets, and role checks. Import adds the preview action for CRUD controllers marked with `#[AdminImport]`.

## Controllers

The operation controllers keep HTTP concerns at the edge:

- `AdminExportController`
- `AdminExportBatchController`
- `AdminExportPreviewController`
- `AdminImportPreviewController`
- `AdminImportConfirmController`

Preview controllers render the preview screens. Confirm/export controllers delegate the main work to services.

## Import Pipeline

The import pipeline is:

```text
CsvUploadValidator
-> CsvPreviewReader
-> ImportPreviewValidator
-> TemporaryImportStorage
-> ImportManager
-> ImportEntityHydrator
-> ImportPersister
```

Preview validates and displays configured fields. Confirmation reopens the temporary file, checks integrity metadata, revalidates the CSV, hydrates new entities, and persists all-or-nothing.

This split exists so users can review imports before persistence and so confirmation never trusts browser-submitted preview row data.

## Export Pipeline

The export pipeline resolves configuration, builds an EasyAdmin-aware query, creates an export payload, then delegates to the selected exporter.

Important pieces include:

- `ExportConfigFactory`
- `ExportSetMetadataResolver`
- operation context resolvers/factories
- `EntityQueryBuilderFactory`
- `ExportPayloadFactory`
- `ExporterRegistry`
- `CsvExporter`, `XlsxExporter`, `JsonExporter`, `XmlExporter`

Final export row generation uses Doctrine `toIterable()` and periodically clears the entity manager.

## Templates And Translations

The bundle extension prepends:

- Twig template path: `templates`
- translator path: `translations`

Runtime resources include:

```text
templates/export/preview.html.twig
templates/import/preview.html.twig
translations/JorisDugueEasyAdminExtraBundle.en.yaml
translations/JorisDugueEasyAdminExtraBundle.fr.yaml
```

These files must stay packaged with the bundle.

## Current Limitations

- Import is CSV-only.
- Import creates new entity instances.
- Async import/export is not implemented.
- Several import limits are internal constants, not public configuration.
