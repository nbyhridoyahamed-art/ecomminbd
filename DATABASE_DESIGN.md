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

## 1c. Inventory Schema (Phase 6 Wave 1; `quantity_reserved` added Phase 8 Wave 1)

```
stock_levels
  id, product_id (FK→products, cascade), warehouse_id (FK→warehouses, cascade),
  quantity (int, default 0),
  quantity_reserved (unsigned int, default 0 — reserved by pending/processing
    orders; "available to sell" = quantity - quantity_reserved; see section 1e),
  timestamps
  unique(product_id, warehouse_id)

stock_movements
  id, uuid, store_id (FK→stores, cascade), product_id (FK→products, cascade),
  warehouse_id (FK→warehouses, cascade),
  type (varchar: adjustment_increase/adjustment_decrease/transfer_in/transfer_out/purchase_receipt/sale),
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

## 2. Target Schema for Future Phases (design intent, not yet migrated)

These are documented now so later phases don't have to re-derive the
shape, and so the foundation tables above (store_id placement, soft
deletes, currency as a table not a hardcoded symbol) are already
compatible with them.

- **Catalog Wave 2:** `product_variants`, `product_attributes`,
  `product_attribute_values` (variable products — `products.type` already
  reserves the column value, schema not yet built), `reviews` (Phase 8's
  `customers`/`orders` now exist to back "verified purchase", but the
  reviews table itself isn't built), a reusable/browsable
  `media` library with folders and cross-entity reuse (today, product/
  category/brand images upload directly against their own record — see
  section 1b). `products`, `categories`, `brands`, `product_images` are
  built — see section 1b.
- **Inventory Wave 2:** `product_variant_id` on `stock_levels`/
  `stock_movements` (needs Phase 5 Wave 2 variants), a pending/in-transit/
  received transfer approval workflow, and a `stock_adjustments` header
  table for grouping a stocktake's many per-product adjustments under one
  reference (today each adjustment is its own `stock_movements` row —
  see section 1c). `stock_levels` (incl. `quantity_reserved`),
  `stock_movements`, `stock_transfers`, `stock_transfer_items` are built
  — see section 1c. Movements driven by purchase receipts, order
  reservation/shipment, and returns are also built — see sections
  1d/1e/1g.
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
  — no variant system yet, needs Phase 5 Wave 2), store credit as a
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
