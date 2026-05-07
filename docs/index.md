# EasyAdmin Extra Bundle

EasyAdmin Extra Bundle adds explicit import and export workflows to EasyAdmin CRUD controllers.

The bundle is built around one idea: the fields used for data exchange should not have to match the fields used to render an admin UI.

- EasyAdmin fields describe what administrators see and edit.
- Export fields describe the file your application produces.
- Import fields describe how a CSV file is validated, previewed, transformed, and persisted.

## Why Use This?

Use this bundle when your back office needs predictable data exchange rather than a quick dump of visible table columns.

Typical cases include:

- exporting a stable CSV/XLSX/JSON/XML contract for another team
- masking or restricting sensitive columns
- exporting only selected rows from an EasyAdmin list
- letting users preview a CSV import before data is written
- mapping CSV files by header, declaration order, or CSV column position

## Documentation

- [Getting started](getting-started.md): installation, route loader setup, and first CRUD examples.
- [Export](export.md): `#[AdminExport]`, configured export fields, formats, preview, batch export, sets, and limitations.
- [Import](import.md): `#[AdminImport]`, configured import fields, mapping modes, preview, confirmation, persistence, and troubleshooting.
- [Security](security.md): practical security notes for CSV import and spreadsheet export.
- [Configuration](configuration.md): current public configuration and intentional limitations.
- [Architecture](architecture.md): route loading, action extensions, controllers, services, and design decisions.

## Examples

- [Product import](examples/product-import.md)
- [Product export](examples/product-export.md)
- [Positioned import](examples/positioned-import.md)
- [Transformers](examples/transformers.md)
- [Stats import](examples/stats-import.md)

## Build The Documentation Site

This repository uses Zensical.

```bash
zensical serve
zensical build
```

Generated `site/` output is build output and should not be committed.
