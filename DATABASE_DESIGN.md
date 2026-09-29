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
    Phase 5 Wave 2a (see section 1i) and 'bundle' since Phase 5 Wave 2c
    (see section 1l); digital/service/combo remain reserved column
    values, not yet implemented — see section 2),
  description (nullable), short_description (nullable),
  currency_code (char(3), default 'BDT'),
  price_amount, sale_price_amount (nullable), cost_price_amount (nullable),
  compare_at_price_amount (nullable) — all bigint minor units,
  weight (decimal, nullable), weight_unit (nullable),
  track_stock (bool, default true), low_stock_threshold (nullable — the
    setting only; actual on-hand stock is Phase 6's stock_levels table),
  status (draft/active/archived), featured (bool),
  -- seo_title/seo_description/focus_keyword dropped Phase 15 — superseded
  -- by the polymorphic seo_metadata table, see section 1t
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

## 1c. Inventory Schema (Phase 6 Wave 1; `quantity_reserved` added Phase 8 Wave 1; transfer workflow + stocktake sessions added Phase 6 Wave 2)

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
  reason (nullable), reference_type/reference_id (nullable — a polymorphic
    `reference()` pointing at the stock_transfer that produced a
    transfer_in/transfer_out pair, or (Wave 2) the stock_adjustment_session a
    stocktake line belongs to; null for a one-off quick adjustment),
  created_by (FK→users, nullOnDelete), timestamps
  index(product_id, warehouse_id), index(reference_type, reference_id),
  index(store_id, created_at)

stock_transfers
  id, uuid, store_id (FK→stores, cascade), transfer_number (e.g. TRF-20260927-AB12CD —
    date + random suffix, not a per-store sequence counter, to avoid needing a
    counter table for a Wave 1 feature),
  from_warehouse_id, to_warehouse_id (FK→warehouses, cascade),
  status (varchar, default 'pending': pending/in_transit/received/cancelled
    — Wave 2, see below),
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, transfer_number)

stock_transfer_items
  id, stock_transfer_id (FK→stock_transfers, cascade), product_id (FK→products, cascade),
  product_variant_id (FK→product_variants, nullOnDelete, nullable),
  quantity (unsigned int), timestamps

stock_transfer_status_history (Wave 2 — mirrors shipment_status_history exactly)
  id, stock_transfer_id (FK→stock_transfers, cascade),
  from_status (nullable), to_status,
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(stock_transfer_id)

stock_adjustment_sessions (Wave 2 — groups a stocktake's many per-product
    corrections under one reference; deliberately not named `stock_adjustments`,
    since that table/route already belongs to Wave 1's single-shot quick-
    adjustment endpoint below — the two are separate, coexisting workflows)
  id, uuid, store_id (FK→stores, cascade), warehouse_id (FK→warehouses, cascade),
  reference (varchar), note (nullable), created_by (FK→users, nullOnDelete),
  timestamps
  unique(store_id, reference)
```

Every stock mutation (adjustment, transfer, or stocktake line) runs inside a
DB transaction with `lockForUpdate()` on the `stock_levels` row and never lets
quantity go negative — a decrease that would require more stock than is on
hand throws `App\Support\InsufficientStockException`, which rolls the whole
transaction back. This lock/compute-delta/guard/write-movement sequence lives
once in `App\Support\StockAdjuster::apply()` (Wave 2 extracted it out of
`StockAdjustmentController` and `StockTransferController`, which had each
grown their own copy), taking resolved `Product`/`Warehouse` models (so error
messages can name them) and an optional `movementType` override so a transfer
can log `transfer_out`/`transfer_in` instead of the generic
`adjustment_increase`/`adjustment_decrease` the two adjustment-style callers
default to.

A transfer is no longer executed immediately (Wave 2): `POST /stock-transfers`
only creates a `pending` row — no stock movement yet, the same "draft holds
nothing until a real event" shape `purchase_orders` established (section 1d)
— and three explicit actions drive it forward: `POST .../{id}/ship`
(`pending` → `in_transit`, decrements the source warehouse, writes a
`transfer_out` movement), `POST .../{id}/receive` (`in_transit` → `received`,
increments the destination warehouse, writes a `transfer_in` movement), and
`POST .../{id}/cancel` (`pending` → `cancelled` only — an already-shipped
transfer must be received, not reversed). Each transition appends a
`stock_transfer_status_history` row via the same private `transition()`
helper pattern `ShipmentController` established. Deliberately not reserved:
a `pending` transfer's source stock (unlike an order, a staff-created
transfer between the same store's own warehouses isn't racing other
customers for it), and multi-call partial receiving (unlike a
`purchase_receipt` against an external supplier, a transfer is received
whole, in one action).

A stocktake session (`stock_adjustment_sessions`) groups several per-product
`StockAdjuster::apply()` calls under one reference instead of each being its
own untraceable `stock_movements` row — `POST /stock-adjustment-sessions`
takes a warehouse and a list of {product, variant?, direction, quantity,
reason?} lines, applies each atomically, and tags every resulting
`stock_movements` row back to the session. Both this and `stock_transfers`
tag their movements through the ledger's pre-existing
`reference_type`/`reference_id` columns below — already shaped like a
Laravel polymorphic relation (`reference()`/`morphMany`), so neither new
resource needed its own FK column on `stock_movements`.

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

## 1d. Purchasing Schema (Phase 7 Wave 1; approval workflow + supplier
ledger + reorder suggestions added Phase 7 Wave 2b)

```
suppliers
  id, uuid, store_id (FK→stores, cascade), name, contact_name (nullable),
  email (nullable), phone (nullable), address (nullable), status,
  payment_terms (varchar, nullable: due_on_receipt/net_15/net_30/net_60 —
    Wave 2b, purely informational, no automatic due-date/overdue math),
  timestamps, deleted_at
  index(store_id, name)

purchase_orders
  id, uuid, store_id (FK→stores, cascade), warehouse_id (FK→warehouses,
    cascade — where the goods will be received), supplier_id (FK→suppliers,
    cascade), po_number (e.g. PO-20260927-AB12CD — same date+random-suffix
    scheme as stock_transfers.transfer_number, see section 1c),
  status (varchar: draft/pending_approval/ordered/partially_received/
    received/cancelled — see the state machine below), currency_code
    (char(3), default 'BDT'),
  notes (nullable), created_by (FK→users, nullOnDelete), timestamps, deleted_at
  unique(store_id, po_number), index(store_id, status)

purchase_order_items
  id, purchase_order_id (FK→purchase_orders, cascade),
  product_id (FK→products, cascade), quantity_ordered (unsigned int),
  quantity_received (unsigned int, default 0 — running tally, incremented
    by each receipt against this line), unit_cost_amount (bigint minor
    units — no separate currency_code column; a PO uses one currency,
    stored on the header), timestamps

purchase_order_status_history (Wave 2b — mirrors stock_transfer_status_history)
  id, purchase_order_id (FK→purchase_orders, cascade),
  from_status (nullable), to_status,
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(purchase_order_id)

purchase_receipts
  id, uuid, store_id (FK→stores, cascade), purchase_order_id
    (FK→purchase_orders, cascade), receipt_number (e.g. GRN-20260927-AB12CD),
  note (nullable), received_by (FK→users, nullOnDelete), timestamps
  unique(store_id, receipt_number)

purchase_receipt_items
  id, purchase_receipt_id (FK→purchase_receipts, cascade),
  purchase_order_item_id (FK→purchase_order_items, cascade),
  quantity_received (unsigned int), timestamps

supplier_payments (Wave 2b)
  id, uuid, store_id (FK→stores, cascade), supplier_id (FK→suppliers, cascade),
  purchase_order_id (FK→purchase_orders, nullable, nullOnDelete — a payment
    can settle a supplier's overall balance rather than one specific order),
  amount_amount (bigint minor units), currency_code (char(3), default 'BDT'),
  method (varchar: cash/bank_transfer/bkash/nagad/cheque), reference (nullable),
  note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(supplier_id, created_at)
```

**Status state machine:** `draft` (items freely editable — a PUT
replaces them wholesale, same pattern as `stock_transfers`' one-shot
create) → `pending_approval` (Wave 2b — explicit `submitForApproval()`
action, replacing Wave 1's direct `place()`; items locked from further
edits, same as `ordered` below) → `ordered` (explicit `approve()`
action, gated by a permission distinct from create/update — see section
2 — the real "committed to the supplier" moment) → `partially_received`
/ `received` (set automatically by `PurchaseReceiptController` after
each receipt, based on whether every line's `quantity_received` has
reached its `quantity_ordered`). `pending_approval` can also go back to
`draft` via `reject()` (optional note) rather than forward. `cancelled`
is reachable from `draft`, `pending_approval`, or `ordered` — once any
stock has been received against an order, cancelling the order itself
is still a later problem (see section 2). Purchase returns (section 1m)
solve the adjacent but distinct need — sending specific already-received
quantities back to the supplier without touching the order's own status
— not this one. Every transition, including the two automatic receipt-
driven ones, now appends a `purchase_order_status_history` row via the
same private `transition()` helper pattern `ShipmentController`/
`StockTransferController` established.

Recording a receipt is the first real producer of the `purchase_receipt`
stock-movement type reserved in section 1c: `PurchaseReceiptController`
increases `stock_levels.quantity` at the PO's `warehouse_id` and writes
a `stock_movements` row with `reference_type`/`reference_id` pointing at
the `purchase_receipt`, inside the same DB transaction (with
`lockForUpdate()`) as the `purchase_order_items.quantity_received`
increment and the PO's status recompute — the same locked read/write
discipline as `stock_transfers`, minus the negative-quantity guard,
since receiving only ever increases stock.

**Supplier ledger (Wave 2b):** `GET /suppliers/{id}/ledger` answers "how
much do we currently owe this supplier" without being a general
accounting module. Debits are recognized per `purchase_receipts` row —
`SUM(receipt_items.quantity_received × order_item.unit_cost_amount)` —
not the whole PO total, which would overstate the liability on a still
`partially_received` order. Credits are `supplier_payments` (cash out)
and any `purchase_returns` already `credited` (section 1m's credit
note, finally applied against something real). The response sorts every
entry by date and folds a running balance in PHP rather than SQL, the
same "compute it in the app, not a portability-risking query" call
`ReportController::foldByGranularity()` already made.

## 1e. Orders Schema (Phase 8 Wave 1; payments/coupons added Phase 8 Wave 2)

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
    'unpaid' — unpaid/partially_paid/paid/refunded, recomputed from
    `payments` below after every new payment; COD's own path to 'paid'
    is still the Phase 9 shipment-delivered flow, untouched by this),
    currency_code (char(3), default 'BDT'),
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

payments (Phase 8 Wave 2)
  id, uuid, store_id (FK→stores, cascade), order_id (FK→orders, cascade),
  amount_amount (bigint minor units), currency_code (char(3), default
    'BDT'), method (varchar — same cod/bkash/nagad/rocket/card/
    bank_transfer set as orders.payment_method), reference (nullable),
    note (nullable), created_by (FK→users, nullOnDelete), timestamps
  index(order_id)
  — the non-COD reconciliation ledger orders.payment_status recomputes
    from; an append-only record of what's actually been collected, the
    same "manual entry, no real gateway" shape as supplier_payments
    (section 1d) — see ARCHITECTURE.md section 6 for why no
    PaymentGatewayInterface exists yet.

coupons (Phase 8 Wave 2)
  id, store_id (FK→stores, cascade), code, description (nullable),
  discount_type (varchar: percentage/fixed), percentage_value
    (unsigned smallint, nullable — 1-100, set when discount_type is
    percentage), fixed_discount_amount (bigint minor units, nullable —
    set when discount_type is fixed), currency_code (char(3), default
    'BDT'), minimum_order_amount (bigint minor units, default 0),
  usage_limit (unsigned int, nullable — null means unlimited),
  used_count (unsigned int, default 0), per_customer_limit (unsigned
    int, nullable — null means unlimited), starts_at, expires_at
    (nullable timestamps), status (varchar: active/inactive), timestamps
  unique(store_id, code)
  — two nullable discount-amount columns rather than one dual-meaning
    one: a percentage isn't money, so overloading a single `value`
    column across both types would fight `App\Support\Money`'s own
    minor-unit convention for no real benefit.

coupon_usages (Phase 8 Wave 2)
  id, coupon_id (FK→coupons, nullOnDelete — not cascade, see below),
  code (a snapshot of the coupon's code at the time it was used),
  order_id (FK→orders, cascade), customer_id (FK→customers, cascade),
  discount_amount (bigint minor units — the actual amount discounted on
    this specific order, since a percentage coupon's effect varies per
    order), currency_code (char(3), default 'BDT'), timestamps
  unique(order_id), index(coupon_id, customer_id)
  — the per-redemption audit ledger a coupon's own usage_limit/
    used_count and per_customer_limit are checked against; coupon_id is
    nullOnDelete (with a denormalized `code` snapshot) rather than
    cascade specifically so deleting a coupon definition never erases
    the historical record of what a past order was actually discounted —
    the same snapshot-survives-the-parent reasoning as
    order_items.unit_price_amount.
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

## 1k. Reporting (Phase 18 Wave 1 + Wave 2)

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

**Wave 2c addendum:** all three reports gain a PDF twin alongside their
CSV export, rendered via `barryvdh/laravel-dompdf` from a Blade view
under `resources/views/reports/` — still no new tables, and each PDF
controller method calls the exact same private query helpers
(`dailySalesRows()`/`periodTotals()`/`paymentMethodQuery()`/
`courierQuery()`/`productPerformanceQuery()`/`lowStockQuery()`) the
JSON/CSV endpoints already use, so there is exactly one place each
number is computed and the PDF can't drift from the on-screen report.
Money renders as `{currency code} {amount}` rather than the ৳ glyph,
since dompdf's bundled fonts have no Bengali-script coverage.

## 1l. Bundles/Combos Schema (Phase 5 Wave 2c)

```
bundle_items
  id, bundle_product_id (FK→products, cascade),
  component_product_id (FK→products, restrict — a component can't be
    deleted while a bundle still lists it),
  component_variant_id (FK→product_variants, restrict, nullable),
  quantity (unsigned int, default 1), sort_order (unsigned int, default 0),
  timestamps
  unique(bundle_product_id, component_product_id, component_variant_id)
  index(component_product_id)

order_item_components
  id, order_item_id (FK→order_items, cascade),
  product_id (FK→products, cascade),
  product_variant_id (FK→product_variants, nullOnDelete, nullable),
  quantity (unsigned int), timestamps
  index(order_item_id)
```

A bundle is a `products` row with `type='bundle'` (section 1b's `type`
column), not a separate `bundles` table — the `product_variants`
precedent above (section 1i: a `variable` product doesn't get its own
table either) is the pattern this follows, superseding an older note in
section 2 that assumed a `bundles`/`bundle_items` pair. `bundle_items`
still exists, just as a bundle's *components* table, not a
bundle-header table.

`order_item_components` snapshots what `App\Support\BundleExpander::expand()`
resolves a line item's components to be at order-creation time — an
identity row (unchanged product/variant/quantity) for a simple or
variable product, one row per `bundle_items` row (quantity multiplied by
however many bundles were ordered) for a bundle. Every downstream stock
operation (`OrderController::reserveItems()`/`releaseReservation()`/
`ship()`, `ReturnController::receive()`, `ShipmentController::returned()`)
reads this snapshot via `OrderItem::resolvedComponents()` rather than
re-deriving it live, so a bundle's composition can be edited after an
order is placed without splitting that order between two different
resolutions. `resolvedComponents()` falls back to a live `expand()` call
only when no snapshot rows exist — true only for a handful of
pre-existing tests that construct an `OrderItem` directly and bypass the
real order-creation flow the snapshot protects. A bundle's own
"available to sell" quantity (`App\Support\BundleExpander::availability()`,
surfaced on `ProductResource` as `bundle_availability`) is derived, never
stored: per warehouse, the minimum across every component of
`floor(component_available / component_quantity_needed)`, treating a
component with no stock at a warehouse as zero there. See
`DEVELOPMENT_ROADMAP.md`'s Phase 5 Wave 2c scope note for the full design
rationale and every deliberate scope cut (no nested bundles, Purchasing/
adjustments/transfers excluded via `App\Rules\ProductIsNotBundle`, Low
Stock report and Stock Levels list exclusion, CSV import/export unchanged).

## 1m. Purchasing Returns Schema (Phase 7 Wave 2a)

```
purchase_returns
  id, uuid, store_id (FK→stores, cascade),
  purchase_order_id (FK→purchase_orders, cascade — not unique, a PO can
    have several returns over time),
  return_number (e.g. PRET-20260928-AB12CD, same scheme as
    stock_transfers.transfer_number, see section 1c),
  status (varchar, default 'requested' — requested -> approved ->
    shipped_back (drives the stock decrement — see
    PurchaseReturnController::shipBack()) -> credited, or rejected
    (terminal, from requested/approved)),
  reason (nullable),
  credit_amount (bigint, nullable — set only once status becomes
    credited; a suggested default (sum of the covered items' original
    unit cost) staff can override, same "computed default, editable"
    pattern as returns.refund_amount — but a supplier *credit note*, not
    a cash refund: nothing exists yet to apply it against, since no
    accounts-payable ledger is built (see section 2, supplier ledger)),
  credited_at (nullable), note (nullable),
  created_by (FK→users, nullOnDelete), timestamps
  unique(store_id, return_number), index(store_id, status),
  index(purchase_order_id)

purchase_return_items
  id, purchase_return_id (FK→purchase_returns, cascade),
  purchase_order_item_id (FK→purchase_order_items, cascade),
  quantity (unsigned int), timestamps
  — no restock-decision column like return_items.restock: a purchase
    return item is definitionally leaving stock, never a condition-based
    choice to keep or discard it.

purchase_return_status_history
  id, purchase_return_id (FK→purchase_returns, cascade),
  from_status (nullable), to_status, note (nullable),
  created_by (FK→users, nullOnDelete), timestamps
  index(purchase_return_id)
```

Mirrors the Returns schema (section 1g) closely, with the goods flow
reversed: a return here is only eligible against a purchase order with
something actually received (`partially_received`/`received`), and its
per-line quantity is capped by `quantity_received` minus whatever a
non-rejected return already covers on that line — not `quantity_ordered`,
since goods still in transit can't physically go back. `shipBack()`
decrements `stock_levels` at the PO's own `warehouse_id` and writes a new
`purchase_return` stock-movement type (the mirror image of
`purchase_receipt`), guarded against a negative result the same way
`OrderController::ship()` is, in case stock moved elsewhere between
approval and physically packing the return. See
`DEVELOPMENT_ROADMAP.md`'s Phase 7 Wave 2a scope note for what's still
deferred (supplier ledger, PO approval workflow, reorder suggestions) and
why.

## 1n. Storefront Schema (Phase 16 Wave 1)

```
orders
  ... (unchanged from section 1e, plus:)
  source (varchar, default 'admin' — 'admin' or 'storefront'; lets staff
    tell a guest-placed order apart from one they entered themselves in
    the admin Orders list/detail page)
```

No other new tables. Every storefront read (`GET store`/
`categories(/{slug})`/`brands(/{slug})`/`products(/{slug})`/
`locations/*`) is a new query over tables sections 1a/1b/1e/1i/1l already
built — `stores`, `categories`, `brands`, `products`, `product_variants`,
`bundle_items`, `bd_divisions`/`bd_districts`/`bd_upazilas` — returned
through new `App\Http\Resources\Storefront\*` classes rather than the
admin ones, since the admin resources expose `cost_price`, per-warehouse
stock breakdowns, `created_by`, and other operator-internal fields a
public, unauthenticated caller must never see. `in_stock` is a computed
boolean, not a stored column: one grouped `SUM(quantity -
quantity_reserved)` query covers every non-bundle product on a listing
page, falling back to `App\Support\BundleExpander::availability()`
per-bundle only for the (typically few) bundle rows, so a browsing page
never pays an N+1 query for it.

A guest checkout (`POST checkout`) writes a normal `orders`/`order_items`/
`order_status_history` row via the same `App\Support\OrderPlacement`
(reservation/bundle-snapshot logic) the admin `OrderController` uses —
see `ARCHITECTURE.md` for why that got extracted — with `source` set to
`storefront`, `created_by` left `null` (there is no staff user), and
`payment_method` hardcoded to `cod` (Wave 1 has no other payment method
to choose from — see section 2). The guest is matched to an existing
`customers` row by `(store_id, phone)` via `firstOrCreate`, never a new
identity table: a repeat guest checkout reuses their existing `Customer`
record and never overwrites its `name`/`email` on a match, so submitting
someone else's real phone number with a different name can't rewrite
their record. No `carts`/`cart_items` table — the cart is client-side
only (`zustand` + `persist`, browser `localStorage`), and `POST checkout`
always re-resolves and re-prices every line from the live `products`/
`product_variants` rows regardless of what the client sends, so nothing
about a stale or tampered client-side cart ever reaches the database (see
`API_DESIGN.md` for the exact contract). Warehouse selection is
automatic — the first active warehouse (by `id`) whose `stock_levels`
can fully cover the resolved cart, expanded through
`BundleExpander::expand()` the same way `OrderPlacement::reserveItems()`
does — with no order-level warehouse input from the guest and no
splitting one order's fulfilment across warehouses (Wave 1 doesn't
invent a capability the admin flow doesn't have either).

Deliberately still resolves to a single store
(`StorefrontController::currentStore(): Store::where('status',
'active')->firstOrFail()`), not by `stores.domain`/`slug` — both columns
already exist (section 1a) for real multi-tenant routing, but no
environment this project runs in seeds a second store to route between
yet, so building that dispatch logic now would have nothing real to test
it against. See `DEVELOPMENT_ROADMAP.md`'s Phase 16 Wave 1 scope note for
the rest of what's deliberately deferred (non-COD payment,
homepage-builder-driven content, per-page SEO metadata) and why.

## 1o. Customer Account Schema (Phase 17 Wave 1)

```
customers
  ... (unchanged from section 1e, plus:)
  password (varchar, nullable, hashed cast — null means a guest-checkout-
    only record nobody has ever registered against; set the moment a
    registration claims it)
```

No other new tables, and no new guard in `config/auth.php`. `Customer`
now extends `Illuminate\Foundation\Auth\User` (`Authenticatable`) and
uses `Laravel\Sanctum\HasApiTokens`, exactly like `App\Models\User`
already did — the same `personal_access_tokens` table now holds both
staff and customer tokens, told apart by its existing polymorphic
`tokenable_type`/`tokenable_id` columns, which is what lets one
`auth:sanctum` middleware keep authenticating both without a second
guard. See `ARCHITECTURE.md` for the `staff`/`customer` middleware pair
that does the actual access-separation work `config/auth.php` isn't
doing here.

`POST account/auth/register` is the one write path that matters for this
section: it looks up `customers` by `(store_id, phone)` before deciding
whether to `UPDATE` (claiming an unclaimed guest row — `password` was
`null`) or `INSERT` (no existing row), never both, so a returning guest
never ends up with two disconnected `customers` rows for the same real
person. That lookup is why section 1n's guest-checkout `firstOrCreate` by
`(store_id, phone)` and this registration lookup have to agree on phone
formatting bit-for-bit — see `DEVELOPMENT_ROADMAP.md`'s Phase 17 Wave 1
scope note for the `BdPhone`/`BdPhoneNumber::normalize()` gap that fix
closed. `customer_addresses` (section 1e) and `orders`/`order_items`/
`order_status_history` (sections 1e/1n) needed no schema change at all to
become customer-visible — `api/v1/account/*` just scopes the same rows to
`Auth::id()` instead of an admin-supplied `customer_id`/`{customer}` route
parameter.

## 1p. Notifications Schema (Phase 19 Wave 1)

```
notifications
  id (uuid, primary key)
  type (varchar — the notification class's FQCN, Laravel's own convention)
  notifiable_type, notifiable_id (polymorphic — a User or a Customer)
  data (json — see below)
  read_at (timestamp, nullable)
  created_at, updated_at
```

Laravel's own stock table (`php artisan notifications:table`), not a
hand-designed one — nothing here is BD- or app-specific enough to need a
custom shape, and every `Notifiable` model (`User`, and now `Customer`
too — see below) gets `->notifications()`/`->unreadNotifications()` for
free from the trait. `data` is deliberately raw, structured fields
(`order_id`, `order_uuid`, `order_number`, `customer_name`, a `type`
discriminator like `order.placed`), never a pre-formatted display string —
the frontend renders it, the same "backend returns data, frontend
formats it" split every other resource in this API already follows (see
`API_DESIGN.md`). Only one notification class writes to this table today
(`NewOrderPlacedNotification`, staff-facing); the customer-facing ones
(`OrderPlacedNotification`, `OrderStatusChangedNotification`,
`ReturnStatusChangedNotification`) use the `mail` and a custom `sms`
channel instead, neither of which persists anything — see
`ARCHITECTURE.md` section 6 for the `SmsGateway` contract behind the SMS
side, and `DEVELOPMENT_ROADMAP.md`'s Phase 19 Wave 1 scope note for
exactly which controller method fires which class.

The one model change: `Customer` (section 1o) gained Laravel's
`Notifiable` trait — `User` already had it, unused, since nothing in this
app sent a notification to anyone before now.

## 1q. CMS Pages Schema (Phase 12 Wave 1)

```
pages
  id, uuid
  store_id (FK stores, cascade)
  title
  slug
  content (text, nullable — plain text, not HTML/Markdown)
  -- meta_title/meta_description dropped Phase 15 — superseded by the
  -- polymorphic seo_metadata table, see section 1t
  status (varchar, default 'draft' — 'draft'|'published')
  created_by (FK users, nullOnDelete)
  timestamps, soft deletes
  unique(store_id, slug)
```

Deliberately the smallest possible shape for a static content page — the
About Us/Terms & Conditions/Privacy Policy kind, not a page builder (that
target schema is still `homepage_blocks`/`saved_sections` below,
unbuilt). `slug` is unique per `store_id`, not globally, the exact
`Category`/`Product` convention (`Rule::unique('pages', 'slug')->where('store_id',
$storeId)`). `content` is a plain nullable text column — no
`page_versions` history table yet (see section 2) — rendered on the
storefront with the same `whitespace-pre-line` treatment
`products.description` already gets, since `COMPONENT_INVENTORY.md`
reserves a rich-text editor (TipTap) for Phase 14's blog, not this phase.
No new permission table: `PagePolicy` maps all five abilities to one
`pages.manage` permission the RBAC seeder had already committed to (wired
to the SEO Manager and Content Manager roles) since Phase 3, dormant until
this phase activated it — see `DEVELOPMENT_ROADMAP.md`'s Phase 12 Wave 1
scope note.

## 1r. Homepage Builder Schema (Phase 13)

```
homepage_blocks
  id, uuid
  store_id (FK stores, cascade)
  type (varchar — one of ~30 registry keys, App\Support\HomepageBlockTypes::ALL)
  settings (json — the one type-specific column; shape validated per `type`)
  styles, responsive, visibility (json, nullable — shared by every type)
  animation (varchar, nullable — 'fade'|'slide'|'scale'|'reveal')
  sort_order (integer)
  is_active (boolean, default false — every new block is a draft)
  scheduled_at (timestamp, nullable)
  created_by (FK users, nullOnDelete)
  timestamps — no soft deletes

homepage_block_revisions
  id
  homepage_block_id (FK homepage_blocks, cascade)
  store_id (FK stores, cascade)
  snapshot (json — {type, settings, styles, responsive, visibility, animation, is_active})
  created_by (FK users, nullOnDelete)
  timestamps

saved_sections
  id, uuid
  store_id (FK stores, cascade)
  name
  type, settings, styles, responsive, visibility, animation (same shape as homepage_blocks, minus is_active/sort_order/scheduled_at — a saved section is a template, not a positioned instance)
  created_by (FK users, nullOnDelete)
  timestamps

testimonials
  id
  store_id (FK stores, cascade)
  name, role (nullable), quote, avatar_url (nullable), rating (nullable, 1-5)
  sort_order, is_active (default true)
  timestamps

blog_posts
  id, uuid
  store_id (FK stores, cascade)
  title, slug, excerpt (nullable), featured_image_url (nullable)
  published_at (nullable), is_active (default true)
  timestamps
  unique(store_id, slug)

newsletter_subscribers
  id
  store_id (FK stores, cascade)
  email
  timestamps
  unique(store_id, email)
```

One column pair (`type` + `settings`) drives all ~30 block types — the
block-registry pattern spec section 60 calls for ("create a block
registry, not one giant conditional") rather than a table per type or a
column per possible field. `settings` validation rules and default
values both live centrally in `App\Support\HomepageBlockTypes`, keyed by
`type`; adding a future block type means adding one case there, no
migration. `styles`/`responsive`/`visibility`/`animation` are columns
every type shares (spec sections 61-62's design/responsive/animation
system), kept separate from `settings` specifically so the generic
Design/Layout/Animation/Advanced panels (`COMPONENT_INVENTORY.md`) never
need to know a block's `type` at all.

`homepage_block_revisions` is the durable, server-side "already-saved
change" history (spec section 62) — a row is inserted before every
settings/publish/unpublish/restore mutation, restorable via a dedicated
endpoint (itself snapshotting first, so a restore is itself undoable).
Deliberately separate from the frontend's own local undo/redo, which
only steps through one editing session's not-yet-saved keystrokes — see
`DEVELOPMENT_ROADMAP.md`'s Phase 13 scope note for why one mechanism
doesn't try to do both jobs. `saved_sections` (spec section 63) is a
reusable library: any block can be saved into it and inserted back onto
the page (or, in principle, any future page) any number of times: a
template, not a live instance, which is why it carries no
`is_active`/`sort_order`/`scheduled_at`.

`testimonials`, `blog_posts`, and `newsletter_subscribers` are
deliberately minimal placeholder models, each existing only to back one
or two block types' real (not fabricated) data, explicitly not their
eventual real feature: `testimonials` backs both the Testimonials and
Reviews blocks (same underlying content, different card emphasis on the
storefront) and is explicitly not Catalog Wave 2's still-unbuilt
verified-purchase review system (section 2 below) — a real product
review needs an order to attach to and belongs to that feature, not this
one; `blog_posts` is intentionally just enough for the Blog Posts block
(title/slug/excerpt/image/published_at, no body/categories/tags) and is
expected to be absorbed or replaced outright once Phase 14 builds the
real blog CMS (see section 2's Blog bullet); `newsletter_subscribers` is
plain email capture with no confirmation/unsubscribe-token flow, since
nothing yet needs one.

## 1s. Blog Schema (Phase 14)

```
blog_posts (altered — absorbs the Phase 13 placeholder, see 1r above)
  id, uuid
  store_id (FK stores, cascade)
  title, slug, excerpt (nullable)
  body (longtext, nullable — new)
  blog_category_id (FK blog_categories, nullOnDelete — new)
  created_by (FK users, nullOnDelete — new)
  -- meta_title/meta_description (added this phase) dropped again Phase 15
  -- — superseded by the polymorphic seo_metadata table, see section 1t
  status (varchar, default 'draft' — new, supersedes the dropped `is_active`)
  featured_image_url (nullable)
  published_at (nullable — doubles as the scheduling gate, see below)
  timestamps, soft deletes (new)
  unique(store_id, slug)

blog_categories
  id, uuid
  store_id (FK stores, cascade)
  name, slug, description (nullable)
  timestamps, soft deletes
  unique(store_id, slug)

blog_tags
  id
  store_id (FK stores, cascade)
  name, slug
  timestamps, soft deletes
  unique(store_id, slug)

blog_post_tag (pivot)
  blog_post_id (FK blog_posts, cascade)
  blog_tag_id (FK blog_tags, cascade)
  primary key (blog_post_id, blog_tag_id) — no extra columns, no timestamps

blog_post_versions
  id
  blog_post_id (FK blog_posts, cascade)
  store_id (FK stores, cascade)
  snapshot (json — {title, slug, excerpt, body, featured_image_url, status}
    — meta_title/meta_description were part of this shape before Phase 15
    dropped the columns; an old snapshot row may still have them in its
    JSON blob, harmlessly ignored on restore since they're no longer in
    BlogPostController::SNAPSHOT_FIELDS)
  created_by (FK users, nullOnDelete)
  timestamps
```

The `is_active` boolean Phase 13's placeholder used is dropped entirely
(not run in parallel with `status`) — the ALTER migration backfills
`is_active = true` rows to `status = 'published'` before dropping the
column, so the 3 existing demo posts stay visible. `blog_categories` is
deliberately flat (no `parent_id`), unlike products' `Category` (section
1c) — the near-universal blog convention (broad topic buckets, not a
taxonomy needing unlimited nesting). Real scheduled publishing reuses
`published_at` as both the display timestamp and the scheduling gate: a
new `BlogPost::scopePublished()` local scope requires `status =
'published' AND published_at <= now()`, so a future-dated `published_at`
on an already-published post is naturally invisible until due, with zero
extra background-job infrastructure (unlike Phase 13's dedicated
`homepage-blocks:publish-scheduled` Artisan command) — and the storefront
index/detail/category/tag reads and the homepage builder's own Blog
Posts block resolver all share this one scope rather than five copies of
the same condition. `blog_post_versions` mirrors
`homepage_block_revisions` (section 1r) exactly: one row per save, and
restoring a version snapshots first so the restore is itself undoable —
sized for a Save-button form rather than a live-autosave canvas, so no
separate local-undo/redo layer sits alongside it the way the homepage
builder's own does. Reading time and the excerpt fallback (when the
manual `excerpt` is blank) are computed at the API Resource layer at
read time — `str_word_count(strip_tags($body)) / 200` and
`Str::limit(strip_tags($body), 200)` respectively — never stored, so
editing `body` keeps both fresh automatically. Deliberately cut: a
comments/moderation subsystem (its own table, spam/moderation states, a
public submission UI, notification hooks) — not part of this schema's
own prior design intent below, a genuinely large separate feature that
would roughly double this phase's size, and real spec-rule-178 risk if
built without genuine safeguards.

## 1t. SEO Schema (Phase 15)

```
seo_metadata (polymorphic — entity_type/entity_id, section 1's
  activity_logs convention, not a nullable FK per entity type)
  id, store_id (FK stores, cascade)
  entity_type (FQCN string, e.g. "App\Models\Product" — no morph map
    registered anywhere in this app, so this is always the raw class name)
  entity_id (unsignedBigInteger)
  title, description, focus_keyword (nullable)
  og_title, og_description, og_image (nullable)
  twitter_title, twitter_description, twitter_image (nullable)
  canonical_url, robots (nullable)
  schema_json (json, nullable — a raw JSON-LD override escape hatch; unset
    by default, no admin UI yet, matching the Homepage Builder's own
    Custom HTML/CSS "escape hatch" precedent)
  timestamps
  unique(entity_type, entity_id)

redirects
  id, store_id (FK stores, cascade)
  from_path, to_path
  status_code (unsignedSmallInteger, default 301 — 301|302|307|308)
  hits_count (unsignedInteger, default 0 — incremented by the storefront
    lookup endpoint each time it matches)
  timestamps
  unique(store_id, from_path)

seo_templates
  id, store_id (FK stores, cascade)
  entity_type (FQCN string, same convention as seo_metadata.entity_type —
    one of Product/Category/Brand/Page/BlogPost/BlogCategory/BlogTag)
  title_template, description_template (nullable — free-text hints like
    "{{title}} | {{store_name}}"; no templating engine reads these yet)
  timestamps
  unique(store_id, entity_type)
```

One polymorphic table backs every SEO-bearing entity — Product, Category,
Brand, Page, BlogPost, BlogCategory, BlogTag, and Store itself (for
site-wide/homepage SEO, via `entity_type = 'App\Models\Store'`) — each via
a `seoMetadata(): MorphOne` relation (`morphOne(SeoMetadata::class,
'entity')`, resolving to `entity_type`/`entity_id` by Laravel's own
default naming convention). This supersedes three ad-hoc SEO field sets
three earlier phases each grew independently rather than running
alongside them: `products.seo_title`/`seo_description`/`focus_keyword`
(section 1b, Phase 5), `pages.meta_title`/`meta_description` (section 1q,
Phase 12), and `blog_posts.meta_title`/`meta_description` (section 1s,
Phase 14). A single migration backfills every existing non-null value
from all three into `seo_metadata` rows, then drops all five legacy
columns in the same migration — the identical "supersede, don't
parallel" discipline Phase 14 used for `blog_posts.is_active` → `status`.
No dedicated seo-metadata REST resource exists: every owning entity's own
existing controller/request/resource accepts and returns its SEO data as
a nested `seo` object on its own normal create/update call, via a new
shared `App\Http\Controllers\Concerns\SyncsSeoMetadata` trait
(`$entity->seoMetadata()->updateOrCreate([], ['store_id' => ..., ...$request->input('seo')])`)
— mirroring how `BlogPost` already accepts `tag_ids` and syncs its tags
pivot as part of one save, not a separate endpoint. `redirects` and
`seo_templates`, by contrast, are genuinely independent resources with
their own standalone admin CRUD (`/content/seo/redirects`,
`/content/seo/templates`), reusing the `seo.manage` permission the RBAC
seeder had already committed to (SEO Manager/Content Manager roles) since
Phase 3 — another dormant-permission activation, the same pattern
`pages.manage` and `blog.manage` each followed. A public
`GET storefront/redirects/lookup?path=X` endpoint backs redirect
resolution, checked inline by a storefront leaf page only when its own
by-slug lookup 404s — never global middleware, so an ordinary request
never pays for a redirects-table lookup it doesn't need.

## 1u. Analytics Events (Phase 20)

```
analytics_events
  id, store_id (FK→stores, cascade),
  session_id (string, indexed with store_id — a client-generated,
    localStorage-persisted UUID; anonymous by design, no FK to customers:
    no storefront route runs optional Sanctum auth today, so there's
    nothing real to attribute an event to a logged-in customer with — see
    the roadmap's Phase 20 scope note),
  event_type (string: page_view/product_view/category_view/search/
    add_to_cart/remove_from_cart/checkout_start/purchase),
  entity_type, entity_id (nullable — same polymorphic convention
    activity_logs/seo_metadata already use, but unlike seo_metadata this
    is never client-supplied: the storefront's public ingestion endpoint
    only ever accepts a plain `product_id`/`category_id`, validated to
    exist, and resolves entity_type/entity_id server-side, so nothing
    here ever stores a raw class name a client sent),
  path (nullable, string — the storefront URL, mainly for page_view),
  metadata (json, nullable — a search's query/results_count, or a
    purchase's order_uuid + total_amount; the latter is always the real
    order's own total_amount, looked up server-side by order_uuid, never
    a value the client claims — a spoofed purchase ping can inflate a
    conversion count, the same inherent limitation any client-fired
    analytics pixel has, but never a reported revenue figure),
  timestamps()
  index(store_id, event_type, created_at), index(store_id, session_id),
  index(entity_type, entity_id)
  — append-only, no soft deletes; indexed for the two access patterns
    every report needs (a store+type+date-range scan, and a
    store+session distinct count for the funnel).
```

Written by one public, unauthenticated, throttled (`throttle:120,1`)
endpoint, `POST storefront/analytics/events`, that the storefront's own
pages call fire-and-forget. Five admin reports read it back (`analytics/
overview`/`products`/`searches`/`funnel`) — all pure runtime aggregation,
same "compute it fresh" reasoning as `DashboardController` and
`ReportController`, no materialized table. The one exception,
`analytics/customers` ("new vs returning"), reads straight from
`orders`/`customers` instead — see the Reporting/Analytics bullet in
section 2 below, now split into its two real halves.

## 1v. Reviews & Media Library Schema (Phase 5 Wave 3)

```
reviews
  id, uuid, store_id (FK→stores, cascade),
  product_id (FK→products, cascade), customer_id (FK→customers, cascade),
  order_id (FK→orders, cascade — the delivered order the review was
    verified against; recorded for audit, never trusted on re-read —
    eligibility is re-derived fresh from customer_id/product_id every
    time, see below),
  rating (unsignedTinyInteger, 1-5), title (nullable, string),
  body (text), status (string, default 'pending': pending/approved/
    rejected), timestamps(), softDeletes()
  unique(product_id, customer_id) — one review per customer per product,
    however many qualifying orders exist
  index(store_id, product_id, status) — the storefront's "approved
    reviews for this product" read; index(customer_id) — "my reviews"

media
  id, uuid, store_id (FK→stores, cascade),
  disk (string, default 'public'), path, filename, mime_type,
  size (unsignedBigInteger), alt_text (nullable),
  uploaded_by (nullable FK→users, null on delete — keep the file if the
    uploading staff account is later removed),
  timestamps() — deliberately NO softDeletes(), unlike every other table
    on this page: destroy() removes the real file from disk too (the
    whole point of deleting a library entry), so a soft-deleted row
    promising recoverability while its file is already gone would be
    misleading, not a real safety net
  index(store_id, created_at) — the library's own paginated/searchable
    listing
```

A review requires a **verified purchase**, checked entirely server-side
at submission time (`Account\ReviewController::store()`): the
authenticated customer must have an `Order` with `status = 'delivered'`
whose `items` include the `product_id` being reviewed — resolved via
`whereHas('items', ...)` against that customer's own orders, never from
a client-supplied `order_id` (accepting one would let a customer forge a
review against an order that isn't theirs). Every new review starts
`status = 'pending'` and only counts toward a product's public
`average_rating`/`reviews_count` once a staff member with
`reviews.moderate` approves it — computed via a `Product::
approvedReviews()` relation (`hasMany(Review::class)->where('status',
'approved')`) and Eloquent's `withCount`/`withAvg` at the query-builder
level, so listing products never pays an N+1 for it.

`media` is the one physical file behind what can otherwise look like
several separate uploads: `App\Support\MediaLibrary::store()` is the
single place a file lands on disk and gets a `media` row, called from
three entry points — the library's own upload (`MediaController`), the
legacy per-folder category/brand upload (`UploadController`, unchanged
route, now also registering a `media` row), and the product image
gallery (`ProductImageController`). Because the same file can now be
picked for more than one entity, only the library's own explicit delete
(`MediaController::destroy()`) removes it from disk — every other
consumer unlinking its own reference (e.g.
`ProductImageController::destroy()`) deletes only its own row
(`product_images`/a category's `image_path`, etc.), never the shared
file, or unlinking one entity would silently break every other
reference still pointing at it. `ProductImageController::attach()` (see
`API_DESIGN.md`) lets the product gallery pick an existing `media` row
without a new upload, scoped to `media.store_id === product.store_id` —
cross-store attachment 404s rather than leaking another store's file.

## 2. Target Schema for Future Phases (design intent, not yet migrated)

These are documented now so later phases don't have to re-derive the
shape, and so the foundation tables above (store_id placement, soft
deletes, currency as a table not a hardcoded symbol) are already
compatible with them.

- **Catalog (now fully shipped, nothing deferred):** `products`,
  `categories`, `brands`, `product_images` — see section 1b;
  `product_attributes`, `product_attribute_values`, `product_variants`,
  `product_variant_attribute_values` — see section 1i; CSV bulk
  import/export — see section 1j; `bundle_items`,
  `order_item_components` (a bundle's stock decrement hits its component
  products, not a `bundles`/`bundle_items` pair as this bullet used to
  assume) — see section 1l; `reviews` and a reusable/browsable `media`
  library — see section 1v. This bullet is kept only as a pointer for
  anyone still holding an older mental model of this section; there is
  no remaining Catalog work to pick.
- **Inventory (now fully shipped, nothing deferred):** a real
  pending/in_transit/received/cancelled transfer approval workflow
  (`stock_transfer_status_history`), and `stock_adjustment_sessions` for
  grouping a stocktake's many per-product corrections under one reference
  (deliberately not the `stock_adjustments` name this bullet used to
  speculate, since that table/route already belongs to the pre-existing
  single-shot quick-adjustment endpoint) — see section 1c. This bullet is
  kept only as a pointer for anyone still holding an older mental model of
  this section; there is no remaining Inventory work to pick.
  `stock_levels` (incl. `quantity_reserved`), `stock_movements`,
  `stock_transfers`, `stock_transfer_items` are built — see section 1c.
  Movements driven by purchase receipts, order reservation/shipment, and
  returns are also built — see sections 1d/1e/1g. The
  `product_variant_id` item this bullet used to list is no longer
  deferred — see section 1i's "Now variant-aware" note.
- **Purchasing (now fully shipped except multi-currency POs, a
  deliberate scope boundary — see below):** a PO approval workflow
  (`purchase_order_status_history`), a supplier ledger
  (`suppliers.payment_terms`, `supplier_payments`), and reorder
  suggestions are built — see section 1d. Multi-currency POs remain
  permanently out of scope: `purchase_orders.currency_code` has always
  been accepted but every store here only ever uses one (BDT), no
  exchange-rate concept exists anywhere, and this app has no evidence of
  a real need for it — unlike every other item this bullet used to list,
  this one isn't "not yet picked," it's a boundary. `suppliers`,
  `purchase_orders`, `purchase_order_items`, `purchase_receipts`,
  `purchase_receipt_items` are built — see section 1d; `purchase_returns`,
  `purchase_return_items`, `purchase_return_status_history` (Wave 2a) are
  built — see section 1m. This bullet is kept only as a pointer for
  anyone still holding an older mental model of this section.
- **Orders (now fully shipped, nothing deferred):** a `payments`
  reconciliation ledger for non-COD methods, `coupons`/`coupon_usages`
  (a real percentage-or-fixed discount-code system with minimum-order/
  usage-limit/per-customer-limit enforcement, shared by the admin and
  storefront checkout entry points via one `CouponResolver`), and an
  order-edit-while-pending UI reusing the existing `OrderForm` are all
  built — see section 1e. `customers`, `customer_addresses`, `orders`,
  `order_items`, `order_status_history` are built too. This bullet is
  kept only as a pointer for anyone still holding an older mental model
  of this section; there is no remaining Orders work to pick.
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
- **CMS/Builder (Phase 12 + full Phase 13 shipped — sections 1q/1r):**
  `pages` (simple content pages) and the full Homepage Builder
  (`homepage_blocks`, `homepage_block_revisions`, `saved_sections`,
  plus the `testimonials`/`newsletter_subscribers` placeholder models and
  `blog_posts` — since promoted to the real table Phase 14 built, section
  1s — that three of its block types resolve real data from) are built.
  Still deferred: `page_versions` (edit history for
  *CMS pages* specifically — `homepage_blocks` already got its own
  revision history in Phase 13, this is the still-missing equivalent for
  `pages`), and `navigation_menus`/`navigation_items` (today's page links
  are a flat, unordered footer list via `GET storefront/pages` — no
  menu/ordering concept). A reusable `media` library (Catalog Wave 2
  above lists this same gap) remains unbuilt for the builder's own
  image-URL fields too — every image field across all ~30 block types is
  a plain URL string, no upload-and-browse picker yet.
- **Blog (Phase 14 shipped — section 1s):** the real blog CMS is built —
  `blog_posts` (altered, absorbing Phase 13's placeholder),
  `blog_categories`, `blog_tags`, `blog_post_tag`, `blog_post_versions`.
  Deliberately cut, not deferred to a numbered Wave: a comments/
  moderation subsystem (see section 1s's own note on why).
- **SEO (Phase 15 shipped — section 1t):** `seo_metadata`, `redirects`,
  `seo_templates` are all built, exactly as originally sketched here.
- **Storefront Wave 2 (Wave 1 shipped — section 1n):** no new tables
  expected here either. Multi-store domain/slug-based routing needs a
  second seeded store to route between before it can be built against
  anything real (`stores.domain`/`slug` already exist — section 1a); a
  real payment gateway ledger is the same `payments` table Orders Wave 2
  above already lists, just with a storefront producer once Phase 19's
  adapters exist (Wave 1's checkout is COD-only, no ledger needed yet).
  Real per-page SEO metadata itself shipped with Phase 15 (section 1t) —
  the `generateMetadata()` server-fetch design pass this bullet used to
  wait on is done for every storefront leaf page.
- **Customer Dashboard Wave 2 (Wave 1 shipped — section 1o):** a
  `wishlists`/`wishlist_items` pair (no backing table or consumer exists
  anywhere yet), customer-initiated return requests from `/account/orders`
  (no new schema — reuses `returns`/`return_items` from section 1g, just
  needs `POST account/orders/{uuid}/returns` scoped to `Auth::id()`
  instead of today's staff-only admin flow), and wiring
  `customer_addresses` (section 1e, already customer-visible via section
  1o) into a saved-address picker on checkout (no schema change either —
  a UI/flow pass on top of what section 1n's `CheckoutRequest` already
  accepts).
- **Reporting (Phase 18 Wave 1 + Wave 2 — section 1k):** sales/product-
  performance/low-stock reports plus a by-courier breakdown, a
  period-over-period comparison, and a PDF export twin alongside each
  CSV, all as runtime aggregation — still open: materialized/aggregated
  tables populated by scheduled jobs, once runtime aggregation actually
  gets too slow to justify them.
- **Analytics (Phase 20 shipped — section 1u):** `analytics_events` is
  built, exactly as sketched here, backing traffic/products/searches/
  funnel reports; `customers` (new vs returning) reads `orders`/
  `customers` directly, no event needed. Deliberately cut, not deferred
  to a numbered Wave: a `customer_id` column on the events table (no
  storefront route runs optional auth to populate it), real-time/live
  visitor counts (no WebSocket infra), third-party pixel integrations (no
  ad platform credentials), and IP-based geolocation (no geo-IP service)
  — each blocked on real infra this environment doesn't have, not merely
  unpicked.
- **Integrations Wave 2 (Wave 1 shipped — section 1p):** no new tables
  expected for the SMS side either — swapping `LogSmsGateway` for a real
  BD provider is a container-binding change, not a schema one. A real
  payment gateway ledger is the same `payments` table Orders Wave 2 above
  already lists; a real courier API integration needs no new table
  either, just an outbound call added to the existing `shipments`
  lifecycle (section 1f) once `CourierInterface` (`ARCHITECTURE.md`
  section 6) has a real implementation to bind; a WhatsApp channel is
  another `via()` entry on the four notification classes section 1p
  already lists, the same shape as the `sms` channel. None of these have
  real provider credentials in this environment yet (`PROJECT_AUDIT.md`).

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
Phase 8 Wave 2 added `payments.amount_amount` and, on `coupons`,
`fixed_discount_amount`/`minimum_order_amount` — all `Money`-backed too;
`coupons.percentage_value` is deliberately a plain unsigned integer, not
a `Money` column, since a percentage isn't a currency amount.
