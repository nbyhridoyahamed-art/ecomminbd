# DATABASE DESIGN

Target: MySQL 8+. Every table follows the base conventions from spec
section 4: `id` (bigint autoincrement PK), `created_at`/`updated_at`,
`deleted_at` where soft-deletion makes sense, `created_by`/`updated_by`
where auditability matters, `status` where the entity has a lifecycle,
and `store_id` where the entity is store-scoped. Foreign keys, indexes,
unique constraints and cascade rules are explicit — JSON columns are
used only for genuinely flexible/unstructured data (e.g. block
`settings` in the page builder, gateway `metadata`), never as a
substitute for relational design.

## 1. Foundation Schema (Phase 1–4)

```
organizations
  id, uuid, name, slug (unique), email, phone, status, timestamps

stores
  id, uuid, organization_id (FK→organizations, cascade),
  name, slug (unique), domain (nullable, unique),
  default_currency_id (FK→currencies), default_timezone (varchar, default 'Asia/Dhaka'),
  default_locale (varchar, default 'en'), status, timestamps, deleted_at

warehouses
  id, uuid, store_id (FK→stores, cascade), name, code (unique per store),
  type (enum: main, branch, pickup_point, temporary),
  manager_name, phone, address_line, division_id, district_id, upazila_id,
  status, timestamps, deleted_at

users
  id, uuid, name, email (unique), phone (unique, nullable, normalized BD format),
  password, current_store_id (FK→stores, nullable),
  locale, timezone, avatar_path, status (active/suspended),
  email_verified_at, remember_token, timestamps, deleted_at

store_user (pivot)
  id, store_id (FK), user_id (FK), is_owner (bool), status, timestamps
  unique(store_id, user_id)

roles / permissions / model_has_roles / model_has_permissions /
role_has_permissions
  Standard spatie/laravel-permission tables (team-aware: `team_id` maps
  to `store_id` so the same user can hold different roles per store).

personal_access_tokens
  Standard Sanctum table.

password_reset_tokens, sessions
  Standard Laravel auth tables.

currencies
  id, code (ISO 4217, unique, e.g. BDT), symbol (৳), name,
  decimal_places (default 2), is_default, status, timestamps

settings
  id, store_id (FK, nullable = platform-level), group (varchar, e.g.
  'general', 'localization', 'checkout'), key, value (json),
  timestamps
  unique(store_id, group, key)

bd_divisions
  id, name_en, name_bn, code (unique), timestamps

bd_districts
  id, division_id (FK→bd_divisions, cascade), name_en, name_bn, code,
  timestamps
  unique(division_id, code)

bd_upazilas
  id, district_id (FK→bd_districts, cascade), name_en, name_bn, code,
  timestamps
  unique(district_id, code)

activity_logs
  id, uuid, store_id (nullable), user_id (nullable, FK, set null on delete),
  action (varchar, e.g. 'product.price_updated'), entity_type, entity_id,
  old_values (json, nullable), new_values (json, nullable),
  ip_address, user_agent, timestamps
  index(entity_type, entity_id), index(store_id, created_at)
```

## 1b. Catalog Schema (Phase 5 Wave 1)

```
categories
  id, uuid, store_id (FK→stores, cascade),
  parent_id (FK→categories, nullOnDelete — self-referencing, unlimited depth),
  name, slug, description (nullable), image_path (nullable),
  sort_order (default 0), status, timestamps, deleted_at
  unique(store_id, slug)

brands
  id, uuid, store_id (FK→stores, cascade), name, slug,
  description (nullable), logo_path (nullable), status, timestamps, deleted_at
  unique(store_id, slug)

products
  id, uuid, store_id (FK→stores, cascade),
  category_id (FK→categories, nullOnDelete), brand_id (FK→brands, nullOnDelete),
  name, slug, sku, barcode (nullable),
  type (varchar, default 'simple' — variable/digital/service/bundle/combo
    are reserved column values, not yet implemented; see section 2),
  description (nullable), short_description (nullable),
  currency_code (char(3), default 'BDT'),
  price_amount, sale_price_amount (nullable), cost_price_amount (nullable),
  compare_at_price_amount (nullable) — all bigint minor units,
  weight (decimal, nullable), weight_unit (nullable),
  track_stock (bool, default true), low_stock_threshold (nullable — the
    setting only; actual on-hand stock is Phase 6's stock_levels table),
  status (draft/active/archived), featured (bool),
  seo_title, seo_description, focus_keyword (all nullable),
  published_at (nullable, set the first time status becomes 'active'),
  created_by, updated_by (FK→users, nullOnDelete),
  timestamps, deleted_at
  unique(store_id, slug), unique(store_id, sku)
  index(store_id, status), index(category_id), index(brand_id)

product_images
  id, product_id (FK→products, cascade), path, alt_text (nullable),
  sort_order (default 0), is_primary (bool, default false), timestamps
  index(product_id, sort_order)
```

Uploaded files (product images, category images, brand logos) are stored
on the `public` disk (`storage/app/public`, symlinked to
`public/storage`) — fine for local/single-server deployment; a future
phase swaps the disk to S3-compatible storage via the `filesystems.php`
config without touching any controller (the abstraction already exists
in Laravel's `Storage` facade, which is all every catalog controller
uses).

### Indexing notes
- `stores.slug`, `stores.domain`: unique — storefront routing depends on these.
- `users.email` unique; `users.phone` unique-but-nullable (normalized
  before the unique check so `01712345678` and `+8801712345678` collide
  as intended — spec section 9).
- `settings` composite unique `(store_id, group, key)` supports both
  platform-wide and per-store overrides with one table.
- `activity_logs` indexed by `(entity_type, entity_id)` for "show history
  of this record" queries and by `(store_id, created_at)` for the audit
  log list view.

## 1c. Inventory Schema (Phase 6 Wave 1)

```
stock_levels
  id, product_id (FK→products, cascade), warehouse_id (FK→warehouses, cascade),
  quantity (int, default 0), timestamps
  unique(product_id, warehouse_id)

stock_movements
  id, uuid, store_id (FK→stores, cascade), product_id (FK→products, cascade),
  warehouse_id (FK→warehouses, cascade),
  type (varchar: adjustment_increase/adjustment_decrease/transfer_in/transfer_out/purchase_receipt),
  quantity (unsigned int — the delta magnitude, always positive; direction is in `type`),
  quantity_before, quantity_after (int — snapshot either side of this movement),
  reason (nullable), reference_type/reference_id (nullable — points at the
    stock_transfer that produced a transfer_in/transfer_out pair; unused by
    adjustments), created_by (FK→users, nullOnDelete), timestamps
  index(product_id, warehouse_id), index(reference_type, reference_id),
  index(store_id, created_at)

stock_transfers
  id, uuid, store_id (FK→stores, cascade), transfer_number (e.g. TRF-20260927-AB12CD —
    date + random suffix, not a per-store sequence counter, to avoid needing a
    counter table for a Wave 1 feature),
  from_warehouse_id, to_warehouse_id (FK→warehouses, cascade),
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, transfer_number)

stock_transfer_items
  id, stock_transfer_id (FK→stock_transfers, cascade), product_id (FK→products, cascade),
  quantity (unsigned int), timestamps
```

Every stock mutation (adjustment or transfer) runs inside a DB transaction
with `lockForUpdate()` on the `stock_levels` row and never lets quantity go
negative — a decrease/transfer-out that would requires more stock than is on
hand throws `App\Support\InsufficientStockException`, which rolls the whole
transaction back (see `StockAdjustmentController`/`StockTransferController`).
A transfer is executed immediately and atomically (source decremented,
destination incremented, one `transfer_out` + one `transfer_in` movement
written) — there is no draft/pending/in-transit workflow in Wave 1, since
nothing yet needs multi-step transfer approval (see section 2).

Stock is tracked per **product**, not per variant — `products.type` is
still only `simple` (Phase 5 Wave 2 hasn't shipped variants). When variants
land, `stock_levels`/`stock_movements` gain a `product_variant_id` and the
existing `product_id` rows migrate to "the simple product's only variant."

## 1d. Purchasing Schema (Phase 7 Wave 1)

```
suppliers
  id, uuid, store_id (FK→stores, cascade), name, contact_name (nullable),
  email (nullable), phone (nullable), address (nullable), status,
  timestamps, deleted_at
  index(store_id, name)

purchase_orders
  id, uuid, store_id (FK→stores, cascade), warehouse_id (FK→warehouses,
    cascade — where the goods will be received), supplier_id (FK→suppliers,
    cascade), po_number (e.g. PO-20260927-AB12CD — same date+random-suffix
    scheme as stock_transfers.transfer_number, see section 1c),
  status (varchar: draft/ordered/partially_received/received/cancelled —
    see the state machine below), currency_code (char(3), default 'BDT'),
  notes (nullable), created_by (FK→users, nullOnDelete), timestamps, deleted_at
  unique(store_id, po_number), index(store_id, status)

purchase_order_items
  id, purchase_order_id (FK→purchase_orders, cascade),
  product_id (FK→products, cascade), quantity_ordered (unsigned int),
  quantity_received (unsigned int, default 0 — running tally, incremented
    by each receipt against this line), unit_cost_amount (bigint minor
    units — no separate currency_code column; a PO uses one currency,
    stored on the header), timestamps

purchase_receipts
  id, uuid, store_id (FK→stores, cascade), purchase_order_id
    (FK→purchase_orders, cascade), receipt_number (e.g. GRN-20260927-AB12CD),
  note (nullable), received_by (FK→users, nullOnDelete), timestamps
  unique(store_id, receipt_number)

purchase_receipt_items
  id, purchase_receipt_id (FK→purchase_receipts, cascade),
  purchase_order_item_id (FK→purchase_order_items, cascade),
  quantity_received (unsigned int), timestamps
```

**Status state machine:** `draft` (items freely editable — a PUT
replaces them wholesale, same pattern as `stock_transfers`' one-shot
create) → `ordered` (explicit `place()` action; items locked from
further edits) → `partially_received` / `received` (set automatically
by `PurchaseReceiptController` after each receipt, based on whether
every line's `quantity_received` has reached its `quantity_ordered`).
`cancelled` is reachable only from `draft` or `ordered` — once any
stock has been received against an order, cancelling it would leave
the received stock unaccounted for, so that's a Wave 2 problem (see
section 2, purchase returns).

Recording a receipt is the first real producer of the `purchase_receipt`
stock-movement type reserved in section 1c: `PurchaseReceiptController`
increases `stock_levels.quantity` at the PO's `warehouse_id` and writes
a `stock_movements` row with `reference_type`/`reference_id` pointing at
the `purchase_receipt`, inside the same DB transaction (with
`lockForUpdate()`) as the `purchase_order_items.quantity_received`
increment and the PO's status recompute — the same locked read/write
discipline as `stock_transfers`, minus the negative-quantity guard,
since receiving only ever increases stock.

## 2. Target Schema for Future Phases (design intent, not yet migrated)

These are documented now so later phases don't have to re-derive the
shape, and so the foundation tables above (store_id placement, soft
deletes, currency as a table not a hardcoded symbol) are already
compatible with them.

- **Catalog Wave 2:** `product_variants`, `product_attributes`,
  `product_attribute_values` (variable products — `products.type` already
  reserves the column value, schema not yet built), `reviews` (needs
  Phase 8 customers/orders for "verified purchase"), a reusable/browsable
  `media` library with folders and cross-entity reuse (today, product/
  category/brand images upload directly against their own record — see
  section 1b). `products`, `categories`, `brands`, `product_images` are
  built — see section 1b.
- **Inventory Wave 2:** `stock_levels.quantity_reserved` (needs Phase 8
  orders to reserve against — Wave 1 only tracks on-hand quantity),
  `product_variant_id` on `stock_levels`/`stock_movements` (needs Phase 5
  Wave 2 variants), automatic movements from order fulfillment/
  cancellation/returns (Phase 8/10), a pending/in-transit/received
  transfer approval workflow, and a `stock_adjustments` header table for
  grouping a stocktake's many per-product adjustments under one
  reference (today each adjustment is its own `stock_movements` row —
  see section 1c). `stock_levels`, `stock_movements`, `stock_transfers`,
  `stock_transfer_items` are built — see section 1c. Movements driven by
  purchase receipts are also built — see section 1d.
- **Purchasing Wave 2:** `purchase_returns` (returning received goods to
  a supplier — needs a real trigger from actual usage before its
  workflow can be designed with confidence), supplier payment
  terms/ledger and multi-currency POs (accounting-heavy, no consumer
  yet), a PO approval/sign-off workflow (no multi-user approval concept
  exists yet), and low-stock-driven reorder suggestions (needs Phase 18/20
  reporting infra). `suppliers`, `purchase_orders`, `purchase_order_items`,
  `purchase_receipts`, `purchase_receipt_items` are built — see section 1d.
- **Orders:** `customers`, `customer_addresses`, `orders`, `order_items`,
  `order_status_history`, `payments`, `cod_settlements`, `coupons`,
  `coupon_usages`.
- **Delivery:** `couriers` (config per provider), `shipments`,
  `delivery_zones`, `delivery_zone_rates`.
- **Returns:** `returns`, `return_items`, `refunds`, `exchanges`.
- **CMS/Builder:** `pages`, `page_versions`, `navigation_menus`,
  `navigation_items`, `media`, `homepage_blocks` (ordered, `type` +
  `settings` JSON per the block registry pattern), `saved_sections`.
- **Blog:** `blog_posts`, `blog_post_versions`, `blog_categories`,
  `blog_tags`, `blog_post_tag` (pivot).
- **SEO:** `seo_metadata` (polymorphic: entity_type/entity_id, title,
  description, focus_keyword, og_*, twitter_*, schema_json, canonical,
  robots), `redirects`, `seo_templates`.
- **Reporting/Analytics:** materialized/aggregated tables populated by
  scheduled jobs rather than heavy runtime aggregation on raw tables.

All money columns in future phases use integer minor-unit columns
(`*_amount` in paisa) — never `float`/`double` — per spec rule 27.
All polymorphic SEO/media relations use `entity_type` + `entity_id`
rather than one FK column per entity type, to avoid N nullable FK
columns on a shared table.

## 3. Money Representation

`*_amount` (bigint, paisa) + `currency_code` (char(3)) on every
financial column. `App\Support\Money` (backend) and `formatMoney()`
(`frontend/src/lib/money.ts`) are the *only* places that convert
between minor units and display strings — every controller and
component goes through one of these rather than doing `* 100` or
interpolating a currency symbol itself. Both are implemented and in use
by the product pricing fields (Phase 5) and purchase-order item unit
costs (Phase 7) — the latter stores `currency_code` once on the
`purchase_orders` header rather than repeating it per item, since a
single order is always placed in one currency.
