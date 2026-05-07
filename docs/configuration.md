# Configuration

Only the options on this page are public bundle configuration.

## discovery_paths

The route loader scans configured directories for EasyAdmin dashboards and CRUD controllers marked with `#[AdminExport]` or `#[AdminImport]`.

Default:

```yaml
joris_dugue_easyadmin_extra:
  discovery_paths:
    - '%kernel.project_dir%/src/Controller'
```

Example with custom admin directories:

```yaml
joris_dugue_easyadmin_extra:
  discovery_paths:
    - '%kernel.project_dir%/src/Controller'
    - '%kernel.project_dir%/src/Admin'
```

Each path must be a string.

## export.action_display

Controls how export actions are displayed globally.

```yaml
joris_dugue_easyadmin_extra:
  export:
    action_display: buttons
```

Supported values:

- `buttons`
- `dropdown`

Default:

```yaml
joris_dugue_easyadmin_extra:
  export:
    action_display: buttons
```

`#[AdminExport(actionDisplay: ...)]` can override this per CRUD controller.

## Route Loader Configuration

The route loader is configured in Symfony routes, not under the bundle config root:

```yaml
easyadmin_extra:
  resource: .
  type: jorisdugue_easyadmin_extra.routes
```

## Not Public Configuration Yet

These values are current implementation details and should not be treated as extension points:

- import temporary storage path
- import token TTL
- import maximum upload size
- import preview row limit
- import maximum column count
- import persistence batch size
- exporter-specific options beyond `#[AdminExport]`

If your application needs these to be configurable, that requires a future public bundle API.

## Documentation Site

This repository uses Zensical:

```toml
[project]
site_name = "EasyAdmin Extra Bundle"
docs_dir = "docs"
site_dir = "site"
```

Do not add `mkdocs.yml` for this documentation site.
