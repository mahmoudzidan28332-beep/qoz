# Product Stock Movements — Full Documentation

## Overview

The `product_stock_movements` module tracks every change to product inventory.
It now supports full **multi-tenant isolation** and **entity-level scoping** via the
`tenant_id` and `entity_id` columns added with:

```sql
ALTER TABLE product_stock_movements
  ADD tenant_id INT(10) UNSIGNED NOT NULL AFTER id,
  ADD entity_id BIGINT(20) UNSIGNED NULL  AFTER tenant_id;
```

---

## Database Schema

### `product_stock_movements`

| Column            | Type                                               | Nullable | Notes                                |
|-------------------|----------------------------------------------------|----------|--------------------------------------|
| `id`              | `bigint(20) unsigned` PK AUTO_INCREMENT            | NO       | Primary key                          |
| `tenant_id`       | `int(10) unsigned`                                 | NO       | Tenant scope (required)              |
| `entity_id`       | `bigint(20) unsigned`                              | YES      | Entity scope (optional)              |
| `product_id`      | `bigint(20)`                                       | NO       | FK → `products.id`                   |
| `variant_id`      | `bigint(20)`                                       | YES      | FK → `product_variants.id`           |
| `change_quantity` | `int(11)`                                          | NO       | Positive = add, Negative = subtract  |
| `type`            | `enum('restock','sale','return','adjustment')`     | NO       | Movement reason                      |
| `reference_id`    | `bigint(20)`                                       | YES      | FK to order/return/etc.              |
| `notes`           | `text`                                             | YES      | Free-text note                       |
| `created_at`      | `datetime`                                         | NO       | `current_timestamp()`                |

### Related Tables

#### `entity_products`

| Column               | Type                    | Notes                                   |
|----------------------|-------------------------|-----------------------------------------|
| `id`                 | `bigint(20) unsigned`   | PK                                      |
| `tenant_id`          | `int(10) unsigned`      | Tenant scope                            |
| `entity_id`          | `bigint(20) unsigned`   | FK → `entities.id`                      |
| `product_id`         | `bigint(20) unsigned`   | FK → `products.id`                      |
| `stock_quantity`     | `int(11)`               | Entity-specific stock (updated on move) |
| `low_stock_threshold`| `int(11)`               | Default 5                               |
| `is_active`          | `tinyint(1)`            | Default 1                               |
| `is_featured`        | `tinyint(1)`            | Default 0                               |
| `created_at`         | `datetime`              |                                         |
| `updated_at`         | `datetime`              | `ON UPDATE current_timestamp()`         |

#### `product_variants`

| Column               | Type                   | Notes                            |
|----------------------|------------------------|----------------------------------|
| `id`                 | `bigint(20) unsigned`  | PK                               |
| `product_id`         | `bigint(20) unsigned`  | FK → `products.id`               |
| `sku`                | `varchar(100)`         | Unique                           |
| `barcode`            | `varchar(100)`         | Unique                           |
| `stock_quantity`     | `int(11)`              | Updated on stock movement        |
| `low_stock_threshold`| `int(11)`              | Default 5                        |
| `is_active`          | `tinyint(1)`           | Default 1                        |
| `is_default`         | `tinyint(1)`           | Default 0                        |

#### `product_variant_attributes`

| Column             | Type        | Notes                         |
|--------------------|-------------|-------------------------------|
| `id`               | `bigint(20)`| PK                            |
| `variant_id`       | `bigint(20)`| FK → `product_variants.id`    |
| `attribute_id`     | `bigint(20)`| FK → `attributes.id`          |
| `attribute_value_id`| `bigint(20)`| FK → `attribute_values.id`   |
| `created_at`       | `datetime`  |                               |

---

## API Endpoints

**Base URL:** `GET|POST|PUT|DELETE /api/v1/product_stock_movements`

### Authentication

All endpoints require a valid session. The `tenant_id` is resolved server-side from the
session — never trusted from client input.

- **Regular users / Tenant admins:** `tenant_id` is always their session tenant.
- **Platform Admins:** may pass `?tenant_id=X` to act on behalf of a tenant.

---

### GET — List movements

```
GET /api/v1/product_stock_movements
    ?tenant_id=1       (Platform Admin only; ignored for regular users)
    &entity_id=5       (optional entity filter)
    &type=restock      (optional; restock|sale|return|adjustment)
    &date_from=YYYY-MM-DD
    &date_to=YYYY-MM-DD
    &search=keyword
    &limit=20
    &offset=0
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "items": [
      {
        "id": 1,
        "tenant_id": 1,
        "entity_id": 5,
        "product_id": 10,
        "variant_id": null,
        "change_quantity": 50,
        "type": "restock",
        "reference_id": null,
        "notes": "Initial stock",
        "created_at": "2026-01-01 12:00:00",
        "product_name": "Product A"
      }
    ],
    "total": 1,
    "limit": 20,
    "offset": 0
  }
}
```

---

### GET — Single movement

```
GET /api/v1/product_stock_movements?id=1
```

---

### GET — Movements by product

```
GET /api/v1/product_stock_movements?product_id=10
```

---

### GET — Stats

```
GET /api/v1/product_stock_movements?stats=1
    &entity_id=5
    &type=restock
    &date_from=YYYY-MM-DD
    &date_to=YYYY-MM-DD
```

**Response:**
```json
{
  "status": "success",
  "data": {
    "total_movements": 150,
    "total_restocked": 500,
    "total_sold": 300,
    "total_returned": 20,
    "total_adjusted": 5
  }
}
```

---

### GET — Lookup by barcode

```
GET /api/v1/product_stock_movements?barcode=123456&entity_id=5
```

---

### GET — Lookup by SKU

```
GET /api/v1/product_stock_movements?sku=PROD-001&lang=ar&entity_id=5
```

---

### POST — Create movement

```json
POST /api/v1/product_stock_movements
Content-Type: application/json

{
  "product_id": 10,
  "variant_id": null,
  "entity_id": 5,
  "change_quantity": 50,
  "type": "restock",
  "reference_id": null,
  "notes": "Received from supplier"
}
```

> `tenant_id` is **always injected server-side** from the resolved session.
> `entity_id` may be sent by client but will be overridden by the session entity_id
> if not provided.

**Side effects:**
- `products.stock_quantity` is updated by `change_quantity`
- `product_variants.stock_quantity` updated if `variant_id` present
- `entity_products.stock_quantity` updated if `entity_id` present
- `products.stock_status` recomputed (`in_stock` / `out_of_stock`)

---

### PUT — Update movement

```json
PUT /api/v1/product_stock_movements?id=1
Content-Type: application/json

{
  "product_id": 10,
  "change_quantity": 60,
  "type": "restock",
  "notes": "Corrected quantity"
}
```

**Side effects:** Reverses old stock delta, applies new delta on all relevant tables.

---

### DELETE — Delete movement

```
DELETE /api/v1/product_stock_movements?id=1
```

**Side effects:** Reverses stock delta on `products`, `product_variants`, and `entity_products`.

---

## Security Model

| Rule                  | Implementation                                                                           |
|-----------------------|------------------------------------------------------------------------------------------|
| Tenant isolation      | Every SQL query has `AND sm.tenant_id = :tenant_id`; platform admin override via session |
| Entity isolation      | Optional `AND sm.entity_id = :entity_id` scope on all list/stats queries                 |
| Mass assignment       | `array_intersect_key()` whitelist in route and repository                                |
| SQL injection         | 100% prepared statements (`:param` only, no string interpolation)                        |
| Cross-tenant writes   | `tenant_id` injected server-side; client value is discarded                              |
| Tenant-scoped deletes | `DELETE … AND tenant_id = :tenant_id` — row not deleted if wrong tenant                 |

---

## Architecture

```
Route: api/v1/routes/product_stock_movements.php
  ↓ resolves tenant_id / entity_id
StockMovementsController
  ↓
StockMovementsService
  ↓
PdoStockMovementsRepository
  ↓ prepared statements
  → product_stock_movements
  → products (stock_quantity, stock_status)
  → product_variants (stock_quantity)
  → entity_products (stock_quantity)
```

### Files

| File | Purpose |
|------|---------|
| `api/v1/routes/product_stock_movements.php` | HTTP entry point; resolves tenant/entity; dispatches by method |
| `api/v1/models/stock_movements/repositories/PdoStockMovementsRepository.php` | All DB queries; multi-tenant scoped |
| `api/v1/models/stock_movements/services/StockMovementsService.php` | Business logic layer |
| `api/v1/models/stock_movements/controllers/StockMovementsController.php` | Thin pass-through |
| `api/v1/models/stock_movements/validators/StockMovementsValidator.php` | Input validation |

---

## Admin UI (stock_movements.php / stock_movements.js)

The admin fragment provides four tabs for platform admin / tenant admin views:

| Tab | Description |
|-----|-------------|
| **Stock Movements** | List of `product_stock_movements` with type/date/search filters |
| **Entity Products** | `entity_products` — per-entity stock levels |
| **Product Variants** | `product_variants` — variant SKU/barcode/stock |
| **Variant Attributes** | `product_variant_attributes` — variant attribute values |

**Platform Admin flow:**
1. Admin selects a `tenant_id` via the PA panel.
2. After tenant selection, the entity selector loads entities for that tenant.
3. All tabs lazy-load their data using `?tenant_id=X&entity_id=Y`.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-05 | Added `tenant_id` + `entity_id` columns to `product_stock_movements` |
| 2026-05 | Repository updated: all queries scoped by tenant_id/entity_id |
| 2026-05 | `entity_products.stock_quantity` now updated on create/delete movement |
| 2026-05 | Admin UI: 4-tab layout + platform admin tenant/entity selector |
