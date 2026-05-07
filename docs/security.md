# Security

## CSV Files Are Untrusted Input

CSV import accepts user-supplied files. The bundle treats extension and MIME checks as useful signals, not proof that a file is safe or valid.

Validation is layered and bounded so preview can reject obvious non-CSV files and avoid unbounded parsing work.

## Layered CSV Validation

Current upload checks include:

- Symfony upload validity
- `.csv` extension
- allowed CSV/text MIME types
- non-empty file
- maximum upload size of 2 MB

Current content checks include:

- bounded content sample
- binary/polyglot signature rejection for ZIP, PDF, Windows executable, and ELF prefixes
- leading PHP and HTML marker rejection
- null byte rejection
- excessive control character rejection
- encoding validation
- maximum sampled line length
- bounded CSV parsing
- maximum 50 columns
- at least one usable CSV row

Preview displays up to 20 rows. Validation samples a bounded number of rows before preview; it is not a full-file security scanner.

## Temporary Storage And Confirmation

When preview has no blocking errors, the uploaded CSV is copied to a private temporary location and metadata is stored beside it.

The metadata includes:

- creation time
- format
- sanitized client filename
- CRUD controller FQCN
- selected separator and encoding
- first-row-header flag
- file size
- SHA-256 hash

The confirmation token is opaque, random, and represented as 64 lowercase hexadecimal characters. It expires after 30 minutes.

On confirmation, the bundle checks the token, CRUD controller binding, metadata shape, file size, and SHA-256 hash before re-reading the CSV.

## Revalidation On Confirm

Confirmation revalidates the CSV from the temporary file. It does not trust hidden row payloads from the preview form or browser.

The confirmation flow re-runs CSV reading, configured field mapping, transformations, validation, hydration, and persistence checks.

## CSRF

Preview and confirmation use separate CSRF token IDs:

```text
jd_import_preview
jd_import_confirm
```

Invalid CSRF tokens are rejected.

## Formula Injection In Exports

Spreadsheet applications may interpret values beginning with formula prefixes. By default, exported values starting with `=`, `+`, `-`, or `@` after leading whitespace are prefixed with a single quote.

```php
#[AdminExport(
    allowSpreadsheetFormulas: false,
)]
```

Only set `allowSpreadsheetFormulas: true` when exported values are trusted.

## Safe UI Errors And Logs

Import transformation, hydration, and persistence errors are exposed to users as safe messages. Where logger support exists, detailed exceptions are logged for application diagnostics.

## Current Limitations

- Validation is defensive and bounded, not perfect file-type or malware detection.
- Import hardening limits are not public configuration options.
- Temporary import storage uses the system temporary directory internally.
- Domain-specific validation remains the application’s responsibility.
