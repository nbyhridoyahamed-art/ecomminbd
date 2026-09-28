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
  type (varchar, default 'simple' — 'variable' is functional since
    Phase 5 Wave 2a, see section 1i; digital/service/bundle/combo remain
    reserved column values, not yet implemented — see section 2),
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

## 1c. Inventory Schema (Phase 6 Wave 1; `quantity_reserved` added Phase 8 Wave 1)

```
stock_levels
  id, product_id (FK→products, cascade),
  product_variant_id (FK→product_variants, restrictOnDelete, nullable — null
    means "the simple product itself"; a variable product's rows always carry
    one, so its stock is never mixed into a phantom no-variant row),
  warehouse_id (FK→warehouses, cascade),
  quantity (int, default 0),
  quantity_reserved (unsigned int, default 0 — reserved by pending/processing
    orders; "available to sell" = quantity - quantity_reserved; see section 1e),
  timestamps
  unique(product_id, product_variant_id, warehouse_id) — note that MySQL/SQLite
    both treat multiple NULLs in a unique index as distinct, so this constraint
    alone can't stop two concurrent inserts from creating duplicate simple-
    product rows; the real guarantee is every write path's `lockForUpdate()`
    read-then-write discipline (see below), not the DB constraint

stock_movements
  id, uuid, store_id (FK→stores, cascade), product_id (FK→products, cascade),
  product_variant_id (FK→product_variants, nullOnDelete, nullable — an audit
    row outlives the variant it was about; same reasoning as order_items below),
  warehouse_id (FK→warehouses, cascade),
  type (varchar: adjustment_increase/adjustment_decrease/transfer_in/transfer_out/purchase_receipt/sale/return),
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
  product_variant_id (FK→product_variants, nullOnDelete, nullable),
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

Stock is tracked per **product-or-variant**: every stock-touching table
above carries a nullable `product_variant_id` alongside `product_id` —
null for a simple product, set for a variable product's variant. A given
`(product_id, warehouse_id)` pair can now legitimately have several
`stock_levels` rows (one per variant), which is why the global Stock
Levels list (`StockLevelController::index()`) sums a variable product's
rows into one line rather than joining them naively — a plain left join
without the `GROUP BY`/`SUM()` would silently show the same product
multiple times, once per variant. Per-variant stock visibility and
adjustment instead live on the product's own Variants tab (see
`COMPONENT_INVENTORY.md`'s `VariantsManager` entry), a deliberate scope
line matching how the list was always product-centric even in Wave 1.

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

## 1e. Orders Schema (Phase 8 Wave 1)

```
customers
  id, uuid, store_id (FK→stores, cascade), name, email (nullable),
  phone, status, timestamps, deleted_at

customer_addresses
  id, customer_id (FK→customers, cascade), label (nullable),
  recipient_name, phone, address_line,
  bd_division_id, bd_district_id, bd_upazila_id (FK→bd_*, nullOnDelete),
  is_default (bool, default false), timestamps
  — exactly one address per customer has is_default = true, enforced in
    CustomerAddressController (not a DB constraint): adding/editing an
    address as default unsets the previous default in the same
    transaction; deleting the default promotes the next-oldest address.

orders
  id, uuid, store_id (FK→stores, cascade), order_number (e.g.
    ORD-20260927-AB12CD — same date+random-suffix scheme as
    stock_transfers.transfer_number, see section 1c),
  customer_id (FK→customers, cascade),
  warehouse_id (FK→warehouses, cascade — fulfilling warehouse, where
    stock is reserved/decremented),
  status (varchar: pending/processing/shipped/delivered/cancelled — see
    the state machine below), payment_method (varchar: cod/bkash/nagad/
    rocket/card/bank_transfer), payment_status (varchar, default
    'unpaid' — Wave 2, see below), currency_code (char(3), default 'BDT'),
  shipping_amount, discount_amount (bigint minor units, default 0 — real
    order-level inputs, unlike subtotal/total which are never stored,
    see section 3),
  customer_address_id (FK→customer_addresses, nullOnDelete — which saved
    address this came from, if any),
  shipping_recipient_name, shipping_phone, shipping_address_line,
  shipping_bd_division_id, shipping_bd_district_id, shipping_bd_upazila_id
    (FK→bd_*, nullOnDelete) — a snapshot of the shipping address at order
    time (from the saved address, or entered manually), so it survives
    the customer later editing or deleting that saved address,
  notes (nullable), created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, order_number), index(store_id, status)

order_items
  id, order_id (FK→orders, cascade), product_id (FK→products, cascade),
  quantity (unsigned int),
  unit_price_amount (bigint minor units — a price snapshot at order
    time; never re-read the live product price afterwards), timestamps

order_status_history
  id, order_id (FK→orders, cascade), from_status (nullable — null on the
    initial "created as pending" entry), to_status, note (nullable),
    created_by (FK→users, nullOnDelete), timestamps
  index(order_id)
  — an append-only audit trail, same ledger style as stock_movements;
    every status transition in OrderController writes one row.
```

**Status state machine:** `pending` (stock reserved atomically at
creation — see below; items freely editable, a PUT releases the old
reservation and re-reserves the new items, same wholesale-replace
pattern as `purchase_orders`/`stock_transfers`) → `processing` (explicit
`process()` action, a pure status change) → `shipped` (explicit `ship()`
action — converts the reservation into a real `sale` stock movement,
decrementing both `quantity` and `quantity_reserved`) → `delivered`
(explicit `deliver()` action, a pure status change). `cancelled` is
reachable only from `pending`/`processing` — it releases the reservation
via `quantity_reserved` without touching on-hand `quantity` (no stock
was ever removed). Once `shipped`, an order can no longer be edited or
cancelled — reversing a shipment is a Wave 2 problem (see section 2,
order returns/exchanges).

Creating or editing a `pending` order reserves stock by incrementing
`stock_levels.quantity_reserved` (not `quantity`) at the order's
`warehouse_id`, inside a DB transaction with `lockForUpdate()`, rejecting
(via `App\Support\InsufficientStockException`, reused from section 1c)
when the requested quantity exceeds what's currently *available*
(`quantity - quantity_reserved`) rather than raw on-hand quantity — the
same locked read/write discipline as every other stock mutation in
sections 1c/1d. Because reserved stock is committed to real orders,
`StockAdjustmentController`'s decrease path and `StockTransferController`'s
transfer-out path both also reject (added in Phase 8) any change that
would take on-hand `quantity` below the warehouse's `quantity_reserved`
— otherwise a manual adjustment or transfer could leave `ship()` unable
to decrement on-hand stock without going negative.

## 1f. Delivery Schema (Phase 9 Wave 1)

```
couriers
  id, uuid, store_id (FK→stores, cascade), name, contact_name (nullable),
  email (nullable), phone (nullable),
  tracking_url_template (nullable — e.g. "https://courier.example/track/
    {tracking_number}"; the frontend substitutes {tracking_number} client-side,
    so a shipment's tracking link never hard-codes a courier's URL scheme),
  status, timestamps, deleted_at
  index(store_id, name)

shipments
  id, uuid, store_id (FK→stores, cascade),
  order_id (FK→orders, cascade, unique — one shipment per order in Wave 1;
    a failed delivery that needs re-dispatching under a new shipment is a
    Wave 2 problem, see below),
  courier_id (FK→couriers, nullOnDelete), tracking_number,
  status (varchar: pending_pickup/picked_up/in_transit/delivered/
    failed_delivery/returned_to_seller — see the state machine below),
  delivery_charge_amount (bigint minor units, default 0 — a snapshot,
    independent of orders.shipping_amount, since what the courier actually
    charges can differ from what the customer was quoted),
  cod_amount_collected (bigint minor units, nullable — set only once status
    becomes delivered; null until then even for a COD order, since collection
    happens at the doorstep, not at dispatch),
  cod_settled (bool, default false), delivered_at (nullable),
  notes (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(store_id, status), index(courier_id)

shipment_status_history
  id, shipment_id (FK→shipments, cascade), from_status (nullable),
  to_status, note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(shipment_id)
  — same append-only audit-ledger pattern as order_status_history/
    stock_movements; every status transition in ShipmentController writes
    one row.

cod_settlements
  id, uuid, store_id (FK→stores, cascade), courier_id (FK→couriers, cascade),
  settlement_number (e.g. CODS-20260927-AB12CD — same date+random-suffix
    scheme as stock_transfers.transfer_number, see section 1c),
  amount_expected (bigint minor units — sum of the covered shipments'
    cod_amount_collected, computed once at creation),
  amount_received (bigint minor units — what the courier actually remitted;
    can differ from amount_expected on courier fees/discrepancies, and the
    gap is surfaced, not silently reconciled),
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, settlement_number), index(courier_id)
  — immutable once created, same reasoning as order_status_history/
    stock_movements: no update/destroy endpoint exists.

cod_settlement_shipments (pivot, plain belongsToMany — no extra columns,
    same pattern as store_user)
  id, cod_settlement_id (FK→cod_settlements, cascade),
  shipment_id (FK→shipments, cascade, unique — a shipment can be settled
    at most once), timestamps
```

**Status state machine:** `pending_pickup` (created by assigning a courier +
tracking number to an already-`shipped` order — see `OrderController::ship()`
in section 1e; this is additive, not a replacement: an order can also be
manually marked delivered without ever getting a shipment, e.g. store pickup
or self-delivery) → `picked_up` → `in_transit` → `delivered` (captures
`cod_amount_collected`, defaulting to the order's total when the order's
`payment_method` is `cod` and none is given, and — if the order isn't
already `delivered` — transitions the order to `delivered` too, setting
`payment_status` to `paid` for COD). `failed_delivery` is reachable from
`pending_pickup`/`picked_up`/`in_transit` and, from there, `returned_to_seller`
— neither touches the order's own status or its stock (`sale` movement/
on-hand quantity), since reversing those is a Wave 2 returns problem (see
below). Once an order has a shipment, `OrderController::deliver()` refuses
to mark it delivered directly — the shipment's own `delivered` action is
the only path, so the two can never disagree about the order's status.

## 1g. Returns Schema (Phase 10 Wave 1)

```
returns
  id, uuid, store_id (FK→stores, cascade),
  order_id (FK→orders, cascade — not unique; an order can have several
    returns, e.g. one per defective item discovered at different times),
  return_number (e.g. RET-20260927-AB12CD — same date+random-suffix scheme
    as stock_transfers.transfer_number/cod_settlements.settlement_number),
  status (varchar: requested/approved/rejected/received/refunded — see the
    state machine below), reason (nullable text),
  refund_amount (bigint minor units, nullable — set only once status
    becomes refunded), refunded_at (nullable timestamp),
  note (nullable text), created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, return_number), index(store_id, status), index(order_id)
  — the model is named `OrderReturn` (PHP reserves `return` as a class
    name) with an explicit `$table = 'returns'` override; the table/API/
    frontend all still just say "returns".

return_items
  id, return_id (FK→returns, cascade),
  order_item_id (FK→order_items, cascade),
  quantity (unsigned int), restock (bool, default true — the staff's
    restock decision, revisited/overridable at receive() time rather than
    fixed at request time, since a returned item's condition is only
    knowable once it's physically back), timestamps

return_status_history
  id, return_id (FK→returns, cascade), from_status (nullable),
  to_status, note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(return_id)
  — same append-only audit-ledger pattern as order_status_history/
    shipment_status_history/stock_movements.
```

**Status state machine:** `requested` (created against a `delivered` order
only; each item's quantity is validated against what remains eligible —
ordered quantity minus whatever's already covered by that order item's
other non-`rejected` returns, so a competing open return reserves its
quantity and a `rejected` one frees it back up) → `approved`/`rejected`
(rejection is reachable from `requested` or `approved`, never after) →
`received` (the state that actually moves stock: for each `return_item`
whose effective `restock` flag is true — the request-time default,
optionally overridden per item in this same call — the item's quantity is
added back to `stock_levels` at the order's warehouse and a `return`-type
`stock_movements` row records it, the same reserved column value Phase 6
left for this) → `refunded` (an amount defaulting to the sum of the
return's items' `quantity × order_item.unit_price_amount`, staff-
overridable — same "computed default, staff-overridable" pattern as
`ShipmentController::delivered()`'s COD amount and
`CodSettlementController`'s `amount_received`). A refund flips
`orders.payment_status` to `refunded` only when, summed across *all* of
that order's `refunded` returns, every order item's return-covered
quantity reaches its full ordered quantity — a deliberately conservative
reconciliation that never guesses at partial-refund semantics (see
`DEVELOPMENT_ROADMAP.md`'s Phase 10 scope note).

Also closed in this phase, not a new table: `ShipmentController::returned()`
(section 1f) now performs the identical restock-plus-`return`-movement
sequence when a `failed_delivery` shipment is marked `returned_to_seller`,
since `Order.ship()` had already decremented on-hand quantity before any
shipment existed and nothing was reversing it — this was the Delivery
Wave 2 gap Phase 9 flagged.

## 1h. Admin Dashboard (Phase 11 Wave 1)

No new tables — the two new endpoints (`DashboardController::salesTrend()`/
`orderStatusBreakdown()`) are pure read-side aggregation over `orders`/
`order_items`, the same rows sections 1e/1c already describe. Nothing here
introduces a derived/cached column: the trend and breakdown are computed
fresh on every request (`GROUP BY DATE(orders.created_at)` / `GROUP BY
orders.status`), same "never store a derivable total" reasoning as an
order's own `total_amount`, just applied across rows instead of within
one. A materialized/scheduled aggregate table only becomes worth it once
report queries get heavier than today's runtime aggregation (both here
and in section 1k below) can comfortably serve — see the
Reporting/Analytics bullet in section 2.

## 1i. Product Variants Schema (Phase 5 Wave 2a)

```
product_attributes
  id, uuid, store_id (FK→stores, cascade), name, slug, timestamps
  unique(store_id, slug)
  — store-scoped reference data, same shape as categories/brands (section
    1b), just without a status/soft-delete column: an attribute with no
    values yet is harmless, so there's no "inactive" state worth adding.

product_attribute_values
  id, product_attribute_id (FK→product_attributes, cascade),
  value, slug, sort_order (default 0), timestamps
  unique(product_attribute_id, slug)

product_variants
  id, uuid, store_id (FK→stores, cascade — denormalized, same
    reasoning as stock_movements.store_id: the sku-uniqueness check and
    store-scoped queries don't need a join through product_id),
  product_id (FK→products, cascade),
  sku, barcode (nullable),
  price_amount, sale_price_amount, cost_price_amount (all bigint minor
    units, nullable — null means "use the parent product's own price,"
    so most variants of a product don't need to repeat it),
  status (varchar, default 'active'), timestamps
  unique(store_id, sku), index(product_id)

product_variant_attribute_values (pivot, plain belongsToMany — no extra
    columns, same pattern as store_user/cod_settlement_shipments)
  id, product_variant_id (FK→product_variants, cascade),
  product_attribute_value_id (FK→product_attribute_values, cascade),
  timestamps
  unique(product_variant_id, product_attribute_value_id)
```

A variant's exact set of attribute-value ids (e.g. Color:Red + Size:XL)
is never stored as a separate "combination" record — it's just whichever
rows exist in the pivot table for that `product_variant_id`, compared by
their sorted id list when `ProductVariantController::generate()` needs
to skip a combination that already has a variant. There is deliberately
no "which attributes does this product use" table either: that set is
just derived from the union of its variants' own attribute values, so
selecting attributes in the UI and generating variants are the same
action rather than a selection step that has to stay in sync with a
separately persisted choice.

**Now variant-aware:** `order_items`, `purchase_order_items`,
`stock_transfer_items`, `stock_levels`, and `stock_movements` all carry a
nullable `product_variant_id` alongside `product_id` (see section 1c) —
a variable product's variants can be ordered, stocked, transferred, and
purchased against individually, not just catalogued. A shared
`App\Rules\VariantBelongsToProduct` validation rule (a `DataAwareRule`)
checks the submitted variant actually belongs to the submitted product on
every line-item form that accepts one; `ProductVariantController::destroy()`
refuses to delete a variant that has any `stock_levels` row at all (even a
zeroed-out one — same existence check the column's `restrictOnDelete` FK
would otherwise enforce as a raw SQL error), pointing the user at
deactivating it (`status = inactive`) instead. See
`DEVELOPMENT_ROADMAP.md`'s Phase 5 Wave 2a scope note for what motivated
this retrofit and its own variant-aware-retrofit scope note for what it
actually shipped.

## 1j. Product CSV Import/Export (Phase 5 Wave 2b)

No new tables — `GET /products/export` reads the existing `products`
columns (joined to `categories`/`brands` for their names) and streams
them as a CSV; `POST /products/import` reads one back and writes to the
same columns via the normal `Product::create()`/`update()` path, so every
existing constraint (the `(store_id, sku)`/`(store_id, slug)` unique
indexes from section 1b, `price_amount` as integer minor units via
`App\Support\Money`) applies exactly as it does to a manually-created
product. `App\Support\ProductCsv::HEADERS` is the single source of truth
for the column list both directions read, so a straight export → edit →
re-import round-trips; a header the app doesn't recognize is ignored
rather than failing the file, so extra spreadsheet columns are harmless.

Import resolves a `Category`/`Brand` name to its `id`, matching
case-insensitively first and creating a new one only when no match
exists — the same `(store_id, slug)`-unique tables from section 1b, so a
`Str::slug()` collision on auto-create dedupes with a `-2`/`-3` suffix,
same as a manually-typed duplicate name would. A new SKU always creates
a `type = simple` product; an existing SKU only ever updates that
product's own columns — `type` and its `product_variants` rows (section
1i) are never touched by import, so re-importing an edited export of a
variable product can't silently flatten it into a simple one. `status`/
`track_stock`/`featured` are the one exception to "blank cell empties
the column": since these are non-nullable columns with a real current
state (not optional content like `description`), a blank cell on an
update row leaves the existing value alone instead of resetting it to
the create-time default — every other nullable column is written
literally, including to `null` on blank, since the file is meant to be
the source of truth for whatever column it contains.

## 1k. Reporting (Phase 18 Wave 1 + Wave 2a + Wave 2b)

No new tables — `ReportController`'s three endpoints (sales, product
performance, low stock) are pure read-side aggregation over the same
`orders`/`order_items` (section 1e), `products` (section 1b), and
`stock_levels` (section 1c) rows every other phase already writes,
same "compute it fresh" reasoning as the Admin Dashboard's own
aggregates in section 1h. The sales report's day-level bucketing uses
`GROUP BY DATE(orders.created_at)` — the one date-truncation expression
MySQL and SQLite (the test suite's driver) both support — and folds that
into week/month buckets in PHP (`Carbon::startOfWeek()`/`startOfMonth()`)
rather than asking SQL to do dialect-specific truncation. Product
performance rolls a variable product's variant-level `order_items` rows
up to their shared `product_id` (`GROUP BY products.id`), and low stock
sums `stock_levels.quantity`/`quantity_reserved` across every warehouse
per product (`GROUP BY products.id` with a `HAVING` on the computed
available quantity vs. `products.low_stock_threshold`) — the same
cross-warehouse summing fix the variant-aware retrofit already applied to
the Stock Levels list (section 1i), reused here rather than re-derived.
All three gate on the `reports.view` permission the RBAC seeder has
seeded since Phase 3 (see `API_DESIGN.md`) but which sat unused until
this phase checked it for the first time.

**Wave 2a addendum:** the sales report also joins `orders` to
`shipments`/`couriers` (`courierQuery()`) for a `by_courier` breakdown —
still no new tables, and still an inner join, deliberately: it only
covers orders that reached a courier (`shipments.order_id` is unique per
section 1f, so this join can't fan out beyond what the earlier
`order_items` join already produces), so an order still awaiting
dispatch correctly has no courier row yet even though it's still counted
in the report's own `totals`.

**Wave 2b addendum:** the sales report also computes a `comparison` — the
same `totals` shape, over the immediately preceding period of equal
length (`previousPeriodRange()`), reusing the same `dailySalesRows()`
query and a newly-extracted `periodTotals()` helper (shared by both the
primary and comparison period, so there's exactly one place that sums a
day-row collection into revenue/orders/average). Computing the
comparison window's day count is the one subtlety worth recording: it
must diff two `startOfDay` instants, not `date_from` (`startOfDay`)
against `date_to` (`endOfDay`, i.e. `23:59:59.999999`) — Carbon's
`diffInDays` rounds that near-whole-day fraction up, which silently
extended the window by a day and shifted its start a day early. A
feature test asserting the exact comparison boundary caught it before
this addendum was written.

## 2. Target Schema for Future Phases (design intent, not yet migrated)

These are documented now so later phases don't have to re-derive the
shape, and so the foundation tables above (store_id placement, soft
deletes, currency as a table not a hardcoded symbol) are already
compatible with them.

- **Catalog Wave 2b:** `bundles`/`bundle_items` (a bundle's stock
  decrement needs to hit its component products, not itself — real
  Orders-integrated work, not just a new `products.type` value),
  `reviews` (Phase 8's `customers`/`orders` now exist to back "verified
  purchase", but the reviews table itself isn't built, and nothing lets
  a customer actually write one before a storefront/account portal
  exists — Phase 16/17), and a reusable/browsable `media` library with
  folders and cross-entity reuse (today, product/category/brand images
  upload directly against their own record — see section 1b).
  `products`, `categories`, `brands`, `product_images` are built — see
  section 1b; `product_attributes`, `product_attribute_values`,
  `product_variants`, `product_variant_attribute_values` are built — see
  section 1i. CSV bulk import/export, the one Wave 2b item this bullet
  used to list, is no longer deferred — no new tables, since it reads
  and writes the `products` columns above directly (see section 1j).
- **Inventory Wave 2:** a pending/in-transit/received transfer approval
  workflow, and a `stock_adjustments` header table for grouping a
  stocktake's many per-product adjustments under one reference (today
  each adjustment is its own `stock_movements` row — see section 1c).
  `stock_levels` (incl. `quantity_reserved`), `stock_movements`,
  `stock_transfers`, `stock_transfer_items` are built — see section 1c.
  Movements driven by purchase receipts, order reservation/shipment, and
  returns are also built — see sections 1d/1e/1g. The
  `product_variant_id` item this bullet used to list is no longer
  deferred — see section 1i's "Now variant-aware" note.
- **Purchasing Wave 2:** `purchase_returns` (returning received goods to
  a supplier — needs a real trigger from actual usage before its
  workflow can be designed with confidence), supplier payment
  terms/ledger and multi-currency POs (accounting-heavy, no consumer
  yet), a PO approval/sign-off workflow (no multi-user approval concept
  exists yet), and low-stock-driven reorder suggestions (needs Phase 18/20
  reporting infra). `suppliers`, `purchase_orders`, `purchase_order_items`,
  `purchase_receipts`, `purchase_receipt_items` are built — see section 1d.
- **Orders Wave 2:** `payments` (a real gateway reconciliation ledger for
  non-COD methods — Wave 1's `orders.payment_status` for `cod` orders is
  now set by the Phase 9 shipment-delivered flow, but `bkash`/`nagad`/
  `rocket`/`card`/`bank_transfer` have no producer yet), `coupons`/
  `coupon_usages` (no discount-code concept yet — Wave 1's
  `discount_amount` is a plain manual entry), and an order-edit UI for
  editing a pending order's items after creation (the `PUT` endpoint
  exists and is tested — see
  `API_DESIGN.md` — but no page consumes it yet, matching how
  purchase-order editing has no dedicated UI either). `customers`,
  `customer_addresses`, `orders`, `order_items`, `order_status_history`
  are built — see section 1e.
- **Delivery Wave 2:** `delivery_zones`/`delivery_zone_rates` (no
  automatic shipping-rate-calculation consumer yet — `orders.shipping_amount`
  is still a plain manual entry, same reasoning as Catalog/Purchasing/Orders
  Wave 2 items above), and multi-shipment orders (re-dispatching after a
  failed delivery currently has nowhere to go — `shipments.order_id` is
  unique). The stock-reversal-on-return item this bullet used to list is
  no longer deferred — Phase 10 built it, see section 1g. `couriers`,
  `shipments`, `shipment_status_history`, `cod_settlements`,
  `cod_settlement_shipments` are built — see section 1f.
- **Returns Wave 2:** `exchanges` (swap for a different product/variant
  — order line items are variant-aware now, see section 1i, so there's
  something to swap *to* within an order, but the exchange workflow
  itself — a return that creates a replacement order/line item and moves
  stock accordingly — is a separate, unbuilt feature, deferred until a
  real usage pattern exists to design it against), store credit as a
  refund method (no wallet/ledger concept exists), and reconciling
  `orders.payment_status` across *partial* refunds spread over multiple
  separate return records (today only a full-coverage refund reconciles
  it — see section 1g). `returns`, `return_items`, `return_status_history`
  are built — see section 1g.
- **CMS/Builder:** `pages`, `page_versions`, `navigation_menus`,
  `navigation_items`, `media`, `homepage_blocks` (ordered, `type` +
  `settings` JSON per the block registry pattern), `saved_sections`.
- **Blog:** `blog_posts`, `blog_post_versions`, `blog_categories`,
  `blog_tags`, `blog_post_tag` (pivot).
- **SEO:** `seo_metadata` (polymorphic: entity_type/entity_id, title,
  description, focus_keyword, og_*, twitter_*, schema_json, canonical,
  robots), `redirects`, `seo_templates`.
- **Reporting/Analytics:** Phase 18 Wave 1 + Wave 2a + Wave 2b (section 1k)
  shipped sales/product-performance/low-stock reports plus a by-courier
  breakdown and a period-over-period comparison, all as runtime
  aggregation — still open: PDF export, and materialized/aggregated
  tables populated by scheduled jobs once runtime aggregation gets too
  slow.

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
by the product pricing fields (Phase 5), purchase-order item unit
costs (Phase 7), and order item unit prices plus `shipping_amount`/
`discount_amount` (Phase 8) — each stores `currency_code` once on its
own header rather than repeating it per item, since a single order (or
PO) is always placed in one currency. Order `subtotal_amount`/
`total_amount` follow the same "never store a derivable total" rule as
`purchase_orders.total_amount` — computed from `order_items` in
`OrderResource`, not stored columns. Phase 9 added
`shipments.delivery_charge_amount`/`cod_amount_collected` and
`cod_settlements.amount_expected`/`amount_received` — all `Money`-backed
minor-unit columns; a `Shipment` has no `currency_code` of its own and
instead reads its order's, the same "one currency per header" reasoning.
