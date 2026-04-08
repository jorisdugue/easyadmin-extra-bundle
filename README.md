# EasyAdmin Extra Bundle

![CI](https://github.com/jorisdugue/easyadmin-extra-bundle/actions/workflows/ci.yml/badge.svg)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-7.4%2b-black)
![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-orange)
![License](https://img.shields.io/badge/license-MIT-green)
![Packagist Version](https://img.shields.io/packagist/v/jorisdugue/easyadmin-extra-bundle)

Export and data safety tools for EasyAdmin 5 (Symfony 7.4+, PHP 8.2+, PHP 8.5 ready)

---

## 🧠 Overview

EasyAdmin Extra Bundle extends EasyAdmin with **advanced export capabilities and data control tools**, while staying fully aligned with its ecosystem.

It provides:

* 📤 Structured data exports (CSV, XLSX, JSON)
* 🔒 Strong security defaults (GDPR-friendly)
* 🧩 Independent export field system
* ⚙️ High performance (streaming, large datasets)
* 📦 Batch export for selected rows directly from EasyAdmin

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
* keep your admin UI simple while exposing richer data externally

---

## ❓ Why this bundle?

EasyAdmin is great for CRUD operations, but real-world backoffices often need more:

* exporting large datasets safely
* exporting only a selected subset of rows
* masking sensitive data (GDPR, finance, healthcare…)
* applying transformations at export time
* handling large datasets efficiently

👉 This bundle focuses on **data control, safety, and developer ergonomics**.

---

## ✨ Features

### 📤 Export

* CSV (streamed, memory efficient)
* XLSX (spreadsheet export)
* JSON export
* Full export or filtered export (EasyAdmin context-aware)
* Batch export for selected rows
* Custom filename support
* Configurable max rows
* Field-level transformations
* Custom export schema (independent from EasyAdmin fields)

---

### 🔒 Security

* Protection against spreadsheet formula injection (CSV & XLSX)
* Safe defaults (formulas disabled by default)
* Role-based access control (`requiredRole`)
* Opt-in export via attribute
* Full sanitization of exported values
* Strict row count validation to enforce export limits safely
* Built-in masking:

  * `mask()`
  * `redact()`
  * `partialMask()`

---

### ⚡ Performance

* CSV exports are streamed (`php://output`)
* Uses Doctrine `toIterable()` (no full dataset in memory)
* Optional EntityManager clearing
* Strict row count validation before export
* Designed for large datasets

---

### 🧩 Developer Experience

* Attribute-based configuration (`#[AdminExport]`)
* Independent export field system
* Transformers & formatters
* Extensible architecture

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

### ⚙️ Configuration (optional)

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
    formats: ['csv', 'xlsx', 'json'],
    maxRows: 10000,
    batchExport: true,
)]
class UserCrudController extends AbstractCrudController
{
}
```

👉 Export routes and actions are automatically generated.

---

## 🧩 Export Fields

Define your export schema independently from EasyAdmin:

```php
use JorisDugue\EasyAdminExtraBundle\Contract\ExportFieldsProviderInterface;
use JorisDugue\EasyAdminExtraBundle\Field\TextExportField;
use JorisDugue\EasyAdminExtraBundle\Field\DateTimeExportField;

class UserCrudController extends AbstractCrudController implements ExportFieldsProviderInterface
{
    public static function getExportFields(): array
    {
        return [
            TextExportField::new('email', 'Email'),
            TextExportField::new('phone')->partialMask(2, 2),
            DateTimeExportField::new('createdAt', 'Created at'),
        ];
    }
}
```

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

If any part of the path is null or inaccessible, the value will be null instead of throwing an exception.

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

### GDPR / masking

```php
TextExportField::new('email')->mask();
TextExportField::new('phone')->partialMask(2, 2);
```

---

### Field ordering

By default, fields are exported in declaration order.

You can override this using `position()`:

```php
TextExportField::new('email')->position(10);
TextExportField::new('name')->position(5);
```

Fields with a defined position are sorted first, then remaining fields keep their declaration order.

---

### Batch export

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
* fully reuses the export configuration (fields, masking, limits, formats)

---

### Custom export count

In some cases, the default export count strategy cannot reliably determine
how many rows will be exported (e.g. grouped queries or complex joins).

To handle these situations, you can provide your own count query:

```php
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
Output (CSV / XLSX / JSON)
```

---

## 📄 Supported Formats

| Format | Notes                             |
|--------|-----------------------------------|
| CSV    | Streamed, best for large datasets |
| XLSX   | Spreadsheet export                |
| JSON   | Structured data                   |

---

## 🔒 Security

### Spreadsheet formula injection

By default, all exports are protected.

To allow formulas:

```php
allowSpreadsheetFormulas: true
```

⚠️ **Warning:** This can expose users to security risks if exported data is untrusted.

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
| Data masking                 | ❌                | ✅           |
| Formula protection           | ❌                | ✅           |
| Custom export schema         | ❌                | ✅           |
| Batch export (selected rows) | ❌                | ✅           |

---

## 🧠 Philosophy

* Stay close to EasyAdmin conventions
* Avoid magic and hidden behaviors
* Provide safe defaults
* Focus on real-world backoffice needs

---

## 🛣️ Roadmap

* [x] Batch export (selected rows)
* [ ] Advanced batch operations (update / delete / workflows)
* [ ] Export presets / profiles
* [ ] Role-based field visibility
* [ ] Additional field helpers

---

## 🤝 Contributing

PRs and feedback are welcome.

---

## 📄 License

MIT
