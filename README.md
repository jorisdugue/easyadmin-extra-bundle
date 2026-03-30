# EasyAdmin Extra Bundle

![PHP](https://img.shields.io/badge/PHP-8.4%2B-blue)
![Symfony](https://img.shields.io/badge/Symfony-8-black)
![EasyAdmin](https://img.shields.io/badge/EasyAdmin-5-orange)
![License](https://img.shields.io/badge/license-MIT-green)

Advanced data tools for EasyAdmin 5 (Symfony 8, PHP 8.4+, PHP 8.5 ready)

---

## 🧠 Overview

EasyAdmin Extra Bundle extends EasyAdmin with advanced data handling capabilities:

* 📤 **Data export** (CSV, XLSX, JSON)
* ⚙️ **Bulk operations** *(coming soon)*
* 🔒 **Security & compliance tools**
* 🧩 **Field-level transformations**

It is designed as a **non-intrusive extension** of EasyAdmin, keeping full compatibility with its ecosystem.

---

## ❓ Why this bundle?

EasyAdmin is great for CRUD operations, but real-world backoffices often need more:

* exporting large datasets safely
* masking sensitive data (GDPR, finance, healthcare…)
* applying transformations at export time
* handling bulk operations efficiently

👉 This bundle focuses on **data control, safety, and developer ergonomics**.

---

## ✨ Features

### 📤 Export

* CSV (streamed, memory efficient)
* XLSX (spreadsheet export)
* JSON export
* Full or filtered export (EasyAdmin context-aware)
* Custom filename
* Configurable max rows
* Field-level transformations
* Built-in masking:

    * `mask()`
    * `redact()`
    * `partialMask()`

---

### 🔒 Security

* Protection against spreadsheet formula injection (CSV & XLSX)
* Safe defaults (formulas disabled by default)
* Role-based access control (`requiredRole`)
* Opt-in export via attribute
* Sanitization of all exported values

---

### ⚙️ Bulk operations *(coming soon)*

Planned features:

* Select multiple rows directly in EasyAdmin
* Apply batch actions (update, delete, custom logic)
* Custom workflows for bulk processing

---

### 🧩 Developer Experience

* Attribute-based configuration (`#[AdminExport]`)
* Custom export field system
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

Customize how your data is exported:

```php
use JorisDugue\EasyAdminExtraBundle\Field\ExportField;

ExportField::new('email')
    ->mask();

ExportField::new('phone')
    ->partialMask(2, 2);

ExportField::new('salary')
    ->formatCurrency('EUR');
```

---

### Available field features

* `mask(string $replacement = '***')`
* `redact()`
* `partialMask(int $visibleStart, int $visibleEnd)`
* `format(...)`
* `transformer(callable $callback)`

---

## 🖼️ Example output

### CSV (safe)

```csv
email,phone,salary
***,12****89,€2,500.00
```

### JSON

```json
[
  {
    "email": "***",
    "phone": "12****89",
    "salary": "€2500"
  }
]
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

By default, all exports are protected against spreadsheet formula injection.

If you enable formulas:

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

## 🚀 Performance

* CSV exports are streamed (`php://output`)
* Uses Doctrine `toIterable()` (no full dataset in memory)
* Optional EntityManager clearing
* Configurable row limits

---

## 🔍 Comparison

| Feature            | Native EasyAdmin | This Bundle |
|--------------------|------------------|-------------|
| Export             | ❌                | ✅           |
| CSV streaming      | ❌                | ✅           |
| XLSX export        | ❌                | ✅           |
| JSON export        | ❌                | ✅           |
| Data masking       | ❌                | ✅           |
| Formula protection | ❌                | ✅           |
| Bulk actions       | ❌                | 🚧          |

---

## 🧠 Philosophy

* Stay close to EasyAdmin conventions
* Avoid magic and hidden behaviors
* Provide safe defaults
* Focus on real-world backoffice needs

---

## 🛣️ Roadmap

* [ ] Bulk actions
* [ ] Virtual / computed fields
* [ ] Role-based field visibility

---

## 🤝 Contributing

PRs and feedback are welcome.

---

## 📄 License

MIT
