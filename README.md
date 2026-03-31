# EasyAdmin Extra Bundle

![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-8-black)
![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-orange)
![License](https://img.shields.io/badge/license-MIT-green)

Export and data safety tools for EasyAdmin 5 (Symfony 8, PHP 8.4+, PHP 8.5 ready)

---

## 🧠 Overview

EasyAdmin Extra Bundle extends EasyAdmin with **advanced export capabilities and data control tools**, while staying fully aligned with its ecosystem.

It provides:

* 📤 Structured data exports (CSV, XLSX, JSON)
* 🔒 Strong security defaults (GDPR-friendly)
* 🧩 Independent export field system
* ⚙️ High performance (streaming, large datasets)

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
* Built-in masking:

  * `mask()`
  * `redact()`
  * `partialMask()`

---

### ⚡ Performance

* CSV exports are streamed (`php://output`)
* Uses Doctrine `toIterable()` (no full dataset in memory)
* Optional EntityManager clearing
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

---

## 🚀 Quick Start

```php
use JorisDugue\EasyAdminExtraBundle\Attribute\AdminExport;

#[AdminExport(
    filename: 'users_export',
    formats: ['csv', 'xlsx', 'json'],
    maxRows: 10000
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

## 🔗 EasyAdmin Integration

This bundle integrates directly with EasyAdmin:

* automatically uses current filters
* respects search queries
* respects sorting
* works directly with CRUD controllers
* no manual query handling required

👉 Export reflects the current admin context.

---

## 🧠 How it works

```
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
* Automatic `COUNT(*)` may fail with:

  * `GROUP BY`
  * `HAVING`
  * `DISTINCT`
* Complex queries may require custom handling

---

## 🔍 Comparison

| Feature              | Native EasyAdmin | This Bundle |
|----------------------|------------------|-------------|
| Export               | ❌                | ✅           |
| CSV streaming        | ❌                | ✅           |
| XLSX export          | ❌                | ✅           |
| JSON export          | ❌                | ✅           |
| Data masking         | ❌                | ✅           |
| Formula protection   | ❌                | ✅           |
| Custom export schema | ❌                | ✅           |
| Bulk actions         | ❌                | 🚧          |

---

## 🧠 Philosophy

* Stay close to EasyAdmin conventions
* Avoid magic and hidden behaviors
* Provide safe defaults
* Focus on real-world backoffice needs

---

## 🛣️ Roadmap

* [ ] Bulk actions
* [ ] Export presets / profiles
* [ ] Role-based field visibility
* [ ] Additional field helpers

---

## 🤝 Contributing

PRs and feedback are welcome.

---

## 📄 License

MIT
