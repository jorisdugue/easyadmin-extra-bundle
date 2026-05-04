# EasyAdmin Extra Bundle

![CI](https://github.com/jorisdugue/easyadmin-extra-bundle/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-7.4%2b-black)
![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Packagist Version](https://img.shields.io/packagist/v/jorisdugue/easyadmin-extra-bundle)

Export and data safety tools for EasyAdmin 5 (Symfony 7.4/8, PHP 8.2+, PHP 8.5 ready)

---

## 🧠 Overview

EasyAdmin Extra Bundle extends EasyAdmin with **advanced export capabilities and data control tools**, while staying aligned with EasyAdmin conventions and keeping behavior explicit.

It provides:

* 📤 Structured data exports (**CSV, XLSX, JSON, XML**)
* 🧩 An **independent export field system**
* 🗂️ **Export sets / profiles** for multiple export schemas per CRUD
* 🔒 Strong security defaults (**masking, formula protection, role-based access**)
* 👀 Optional **preview flow** before download
* 📦 **Batch export** for selected rows directly from EasyAdmin
* ⚙️ Flexible action display (**buttons or dropdown**)
* ⚡ High performance for large datasets

---

## 🎯 Core Idea

EasyAdmin fields describe the **admin UI**.

Export fields describe the **export contract**.

These two layers are intentionally independent.

This allows you to:

* export fields not displayed in EasyAdmin
* compute custom values at export time
* apply masking and transformations only for export
* control column order independently
* define multiple export schemas for the same CRUD
* keep your admin UI simple while exposing richer data externally

---

## ❓ Why this bundle?

EasyAdmin is great for CRUD operations, but real-world backoffices often need more:

* exporting large datasets safely
* exporting only a selected subset of rows
* exposing different export schemas for different teams or use cases
* masking sensitive data (GDPR, finance, healthcare…)
* applying transformations at export time
* handling large datasets efficiently

👉 This bundle focuses on **data control, safety, and developer ergonomics**.
It is designed to be **non-intrusive**: opt-in with `#[AdminExport]`, explicit behavior, no hidden magic.

---

## ✨ Features

### 📤 Export

* CSV export (streamed, memory efficient)
* XLSX export
* JSON export
* XML export
* Full export or filtered export (EasyAdmin context-aware)
* Optional export preview per format
* Batch export for selected rows
* Custom filename support
* Configurable max rows
* Field-level transformations
* Custom export schema independent from EasyAdmin fields
* Multiple **export sets / profiles** per CRUD

### 🗂️ Export sets / profiles

A single CRUD can expose multiple export configurations.

Typical use cases:

* **default** export for day-to-day operations
* **gdpr** export with masked or restricted fields
* **finance** export with accounting-specific columns
* **support** export with operational data only

Each export set can define:

* its own field list
* its own labels
* its own role restrictions
* its own visibility in the UI

⚠️ If you implement export sets explicitly, a `default` set is required.

Export set names are validated and must use lowercase letters, digits, `_` or `-`.

### 🔒 Security

* Protection against spreadsheet formula injection (CSV & XLSX)
* Safe defaults (formulas disabled by default)
* Role-based access control (`requiredRole` / `requiredRoles`)
* Role-restricted export sets
* Role-based field visibility
* Opt-in export via attribute
* Full sanitization of exported values
* Strict row count validation to enforce export limits safely
* Built-in masking helpers:

  * `mask()`
  * `redact()`
  * `partialMask()`

### ⚡ Performance

* CSV exports are streamed (`php://output`)
* Uses Doctrine `toIterable()` (no full dataset in memory)
* Optional EntityManager clearing during iteration
* Strict row count validation before export
* Designed for large datasets

### 🧩 Developer Experience

* Attribute-based configuration (`#[AdminExport]`)
* Independent export field system
* Transformers & formatters
* Export sets / profile metadata
* Extensible architecture with dedicated exporters

---

## 📦 Installation

```bash
composer require jorisdugue/easyadmin-extra-bundle
```

Symfony Flex should auto-register the bundle.

### ⚠️ Routes configuration

This bundle uses a custom route loader.

```yaml
# config/routes/easyadmin_extra.yaml
easyadmin_extra:
  resource: .
  type: jorisdugue_easyadmin_extra.routes
```

Without this configuration, export routes will not be generated.

---

## ⚙️ Configuration (optional)

By default, the bundle scans the following directory to discover dashboards and CRUD controllers:

`src/Controller`

If your project uses a custom structure (recommended for modular or DDD architectures), you can configure additional discovery paths:

```yaml
# config/packages/easyadmin_extra.yaml
joris_dugue_easyadmin_extra:
  discovery_paths:
    - '%kernel.project_dir%/src/Controller'
    - '%kernel.project_dir%/src/Admin'
    - '%kernel.project_dir%/modules'
```

### Default action display

You can configure how export actions are displayed globally:

```yaml
# config/packages/easyadmin_extra.yaml
joris_dugue_easyadmin_extra:
  export:
    action_display: dropdown
```

Supported values:

* `dropdown`
* `buttons`

### 🧠 How discovery works

The bundle will:

* scan all configured directories
* detect EasyAdmin dashboards using `#[AdminDashboard]`
* detect exportable CRUD controllers using `#[AdminExport]`

👉 No specific folder structure is required.

👉 This makes the bundle compatible with:

* multi-dashboard applications
* modular architectures
* monorepos or packages

---

## 🚀 Quick Start

```php
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;

#[AdminExport(
    filename: 'users_export',
    formats: ['csv', 'xlsx', 'json', 'xml'],
    maxRows: 10000,
    previewEnabled: true,
    previewLimit: 30,
    batchExport: true,
)]
class UserCrudController extends AbstractCrudController
{
}
```

👉 Export routes and actions are automatically generated.

---

## 🧾 `#[AdminExport]` options

The `#[AdminExport]` attribute lets you configure the export behavior of a CRUD controller.

Common options include:

* `filename`
* `formats`
* `maxRows`
* `fullExport`
* `filteredExport`
* `previewEnabled`
* `previewLimit`
* `previewLabel`
* `batchExport`
* `batchExportLabel`
* `requiredRole`
* `requiredRoles`
* `csvLabel`
* `xlsxLabel`
* `jsonLabel`
* `xmlLabel`
* `actionDisplay`
* `routeName`
* `routePath`
* `allowSpreadsheetFormulas`

Example:

```php
#[AdminExport(
    filename: 'users_export',
    formats: ['csv', 'xlsx', 'json'],
    maxRows: 10000,
    previewEnabled: true,
    previewLabel: 'Preview export',
    batchExport: true,
    batchExportLabel: 'Export selection',
    requiredRoles: ['ROLE_ADMIN', 'ROLE_MANAGER'],
    csvLabel: 'CSV download',
    actionDisplay: 'dropdown',
)]
class UserCrudController extends AbstractCrudController
{
}
```

---

## 🧩 Export Fields

Define your export schema independently from EasyAdmin:

```php
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

class UserCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getExportFields(?string $exportSet = null): array
    {
        return match ($exportSet) {
            'gdpr' => [
                TextExportField::new('email', 'Email')->mask(),
                TextExportField::new('phone', 'Phone')->partialMask(2, 2),
                DateTimeExportField::new('createdAt', 'Created at'),
            ],
            default => [
                TextExportField::new('email', 'Email'),
                TextExportField::new('phone', 'Phone'),
                DateTimeExportField::new('createdAt', 'Created at'),
            ],
        };
    }
}
```

---

## 🗂️ Export Sets / Profiles

Use export sets to expose multiple export schemas for the same CRUD controller.

```php
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Contract\ExportSetMetadataProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Dto\ExportSetMetadata;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;
use JorisDugue\EasyAdminExtraBundle\Field\MoneyExportField;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;

class UserCrudController extends AbstractCrudController implements ExportFieldsProviderInterface, ExportSetMetadataProviderInterface
{
    public static function getExportSetMetadata(): array
    {
        return [
            new ExportSetMetadata('default', 'Standard export'),
            new ExportSetMetadata('gdpr', 'GDPR export', ['ROLE_ADMIN']),
            new ExportSetMetadata('finance', 'Finance export', ['ROLE_FINANCE']),
        ];
    }

    public static function getExportFields(?string $exportSet = null): array
    {
        return match ($exportSet) {
            'gdpr' => [
                TextExportField::new('email', 'Email')->mask(),
                TextExportField::new('phone', 'Phone')->partialMask(2, 2),
                DateTimeExportField::new('createdAt', 'Created at'),
            ],
            'finance' => [
                TextExportField::new('email', 'Email'),
                MoneyExportField::new('balance', 'Balance'),
                DateTimeExportField::new('createdAt', 'Created at'),
            ],
            default => [
                TextExportField::new('email', 'Email'),
                TextExportField::new('phone', 'Phone'),
                DateTimeExportField::new('createdAt', 'Created at'),
            ],
        };
    }
}
```

### Why use export sets?

Export sets are useful when different consumers need different export contracts:

* internal operations vs compliance
* finance vs support
* raw data vs masked data
* full export vs reduced export

👉 Export sets are resolved consistently across standard export, preview, and batch export flows.

---

## ⚡ Advanced Usage

### Custom computed values

```php
TextExportField::new('fullName')
    ->setTransformer(fn ($value, $entity) =>
        $entity->getFirstName().' '.$entity->getLastName()
    );
```

---

### Conditional fallback

```php
->setTransformer(fn ($value) => $value ?? 'N/A');
```

---

### 🧩 Nested properties & null safety

By default, property access is strict.

If a nested property path is invalid or contains a null value, an exception is thrown:

```php
TextExportField::new('company.address.city', 'City');
```

### ✅ Safe access with `nullSafe()`

You can safely access nested relations using `nullSafe()`:

```php
TextExportField::new('company.address.city', 'City')
    ->nullSafe();
```

If any part of the path is null or inaccessible, the value will be `null` instead of throwing an exception.

### 🔁 Combine with default values

```php
TextExportField::new('manager.email', 'Manager')
    ->nullSafe()
    ->setDefault('N/A');
```

Result:

* valid value → used as-is
* null or missing → `'N/A'`

---

### ⚠️ Important

`nullSafe()` catches property access errors, including:

* null intermediate relations
* invalid property paths (typos)
* inaccessible properties

👉 Use with caution during development to avoid hiding mistakes.

---

## 🔒 Masking and transformations

```php
TextExportField::new('email')->mask();
TextExportField::new('phone')->partialMask(2, 2);
TextExportField::new('ssn')->redact();
```

You can also combine masking with format-specific behavior and custom transformers.

---

## 🔐 Field visibility and labels

Fields can be customized depending on **roles** and **formats**.

### By role

You can:

* show fields only for some roles
* hide fields for some roles
* override labels depending on the current role

Typical helpers include:

* `onlyForRole()` / `onlyForRoles()`
* `hideForRole()` / `hideForRoles()`
* `showForRole()` / `showForRoles()`
* `setLabelForRole()` / `setLabelsForRoles()`

### By format

You can also customize field visibility and labels depending on the export format:

* show fields only in some formats
* hide fields in some formats
* override labels depending on the current format

Typical helpers include:

* `onlyOnFormat()` / `onlyOnFormats()`
* `hideOnFormat()` / `hideOnFormats()`
* `showOnFormat()` / `showOnFormats()`
* `setLabelForFormat()` / `setLabelsForFormats()`

This makes it possible to expose different columns and labels depending on the target audience and output format.

---

## 🧭 Field ordering

By default, fields are exported in declaration order.

You can override this using `position()`:

```php
TextExportField::new('email')->position(10);
TextExportField::new('name')->position(5);
```

Fields with a defined position are sorted first, then remaining fields keep their declaration order.

---

## 🔄 Custom row mapping

If you need full control over the exported row structure, you can implement `CustomExportRowMapperInterface`.

```php
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportRowMapperInterface;

class UserCrudController extends AbstractCrudController implements CustomExportRowMapperInterface
{
    public function mapExportRow(object $entity): array
    {
        return [
            'email' => $entity->getEmail(),
            'phone' => $entity->getPhone(),
            'createdAt' => $entity->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}
```

### Important behavior

* returned rows must be keyed by the configured export property names
* all configured export properties must be present
* missing keys trigger an explicit exception
* additional keys are ignored

👉 This contract is useful when you want to fully control how export rows are built while still reusing the bundle’s field ordering, labels, masking, and exporter pipeline.

---

## 🔔 Export events

The export lifecycle dispatches synchronous Symfony events:

* `BeforeExportEvent` before the export response is created
* `AfterExportEvent` after the export response is created
* `BeforeExportRowEvent` before an export row is yielded to the exporter
* `AfterExportRowEvent` after row preparation

`BeforeExportRowEvent` allows row mutation. This is useful for custom formatting or adding/removing row values before the row is written by the exporter.

These events are best suited for cross-cutting concerns such as audit logs, metrics, notifications, or custom integrations.

These events are synchronous and run in the current export request. They do not provide async export.

```php
namespace App\EventSubscriber;

use JorisDugue\EasyAdminExtraBundle\Event\Export\AfterExportEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ExportAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            AfterExportEvent::class => 'logExport',
        ];
    }

    public function logExport(AfterExportEvent $event): void
    {
        $this->logger->info('EasyAdmin export response created.', [
            'format' => $event->getContext()->format,
            'scope' => $event->getContext()->scope,
            'entity' => $event->getContext()->entityName,
            'filename' => $event->getPayload()->filename,
            'status_code' => $event->getResponse()->getStatusCode(),
        ]);
    }
}
```

---

## 📦 Batch export

You can export **only selected entities** directly from EasyAdmin using batch actions.

```php
#[AdminExport(
    formats: ['csv', 'xlsx'],
    batchExport: true,
)]
class UserCrudController extends AbstractCrudController
{
}
```

👉 This automatically adds batch export actions in EasyAdmin for supported formats.

### How batch export works

* select rows in the EasyAdmin list view
* use the batch action dropdown
* choose an export format
* only selected entities are exported

### Batch export behavior

* uses Doctrine metadata to detect identifier type
* supports integer and string identifiers
* rejects composite identifiers with an explicit exception
* fully reuses the export configuration (fields, masking, limits, sets, formats)

---

## 👀 Preview flow

You can enable an export preview page before download:

```php
#[AdminExport(
    formats: ['csv', 'xml'],
    previewEnabled: true,
    previewLimit: 20,
)]
class UserCrudController extends AbstractCrudController
{
}
```

Preview uses the same export configuration as the final export:

* field visibility
* masking
* labels
* formats
* export set selection

👉 What users validate is aligned with actual export output.

---

## 🔢 Custom export count

In some cases, the default export count strategy cannot reliably determine how many rows will be exported (for example grouped queries or complex joins).

To handle these situations, you can provide your own count query:

```php
use Doctrine\ORM\QueryBuilder;
use JorisDugue\EasyAdminExtraBundle\Contract\CustomExportCountQueryBuilderInterface;

class OrderCrudController extends AbstractCrudController implements CustomExportCountQueryBuilderInterface
{
    public function createExportCountQueryBuilder(): QueryBuilder
    {
        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(o.id)')
            ->from(Order::class, 'o');
    }
}
```

👉 This custom query always takes precedence over the default strategy.

---

## 🔗 EasyAdmin Integration

This bundle integrates directly with EasyAdmin:

* automatically uses current filters
* respects search queries
* respects sorting
* works directly with CRUD controllers
* supports exporting selected rows via batch actions
* supports export sets across the UI flow
* no manual query handling required

👉 Export reflects the current admin context.

---

## 🧠 How it works

```text
EasyAdmin CRUD
    ↓
Filters / Search / QueryBuilder
    ↓
Export Engine
    ↓
Export Fields (custom schema)
    ↓
Output (CSV / XLSX / JSON / XML)
```

---

## 📄 Supported Formats

| Format | Notes                             |
| ------ | --------------------------------- |
| CSV    | Streamed, best for large datasets |
| XLSX   | Spreadsheet export                |
| JSON   | Structured data                   |
| XML    | Structured, interoperable markup  |

---

## 🔒 Security

### Spreadsheet formula injection

By default, all exports are protected.

To allow formulas:

```php
allowSpreadsheetFormulas: true
```

⚠️ **Warning:** This can expose users to security risks if exported data is untrusted.

### Role restrictions

You can restrict:

* the export itself via `requiredRole` or `requiredRoles`
* individual export sets via metadata roles
* individual fields via field-level visibility rules

This makes it possible to expose different exports depending on the current user.

---

## ⚠️ Limitations

* CSV is recommended for large datasets
* XLSX uses more memory

### Export row count

The bundle uses a strict and safe row counting strategy before exporting data.

The default strategy supports:

* a single root entity
* a single scalar identifier

The following cases are **not supported by the default count strategy**:

* `GROUP BY`
* `HAVING`
* composite identifiers
* complex queries altering the root entity cardinality

In these situations, the export will fail with an explicit exception.

👉 To handle these cases, implement a custom count query (see above).

---

## 🧠 Design choices

### Strict counting strategy

The bundle intentionally uses a strict counting strategy.

Instead of returning potentially incorrect counts, it will:

* fail explicitly on ambiguous queries
* require a custom count implementation when needed

This ensures:

* accurate export limits (`maxRows`)
* predictable behavior
* safer data handling

---

## 🔍 Comparison

| Feature                      | Native EasyAdmin | This Bundle |
|------------------------------|------------------|-------------|
| Export                       | ❌                | ✅           |
| CSV streaming                | ❌                | ✅           |
| XLSX export                  | ❌                | ✅           |
| JSON export                  | ❌                | ✅           |
| XML export                   | ❌                | ✅           |
| Preview before export        | ❌                | ✅           |
| Data masking                 | ❌                | ✅           |
| Formula protection           | ❌                | ✅           |
| Custom export schema         | ❌                | ✅           |
| Export sets / profiles       | ❌                | ✅           |
| Batch export (selected rows) | ❌                | ✅           |

---

## 🧠 Philosophy

* Stay close to EasyAdmin conventions
* Avoid magic and hidden behaviors
* Keep behavior explicit and opt-in
* Provide safe defaults
* Focus on real-world backoffice needs

---

## 🛣️ Roadmap

* [x] Batch export (selected rows)
* [x] Export sets / profiles
* [x] Role-based field visibility
* [ ] Additional exporter-level configuration options
* [ ] Additional field helpers
* [ ] Advanced batch operations (update / delete / workflows)
* [ ] Audit trail for exports
* [ ] Async exports for heavy datasets

---

## 🤝 Contributing

PRs and feedback are welcome.

---

## 📄 License

MIT
