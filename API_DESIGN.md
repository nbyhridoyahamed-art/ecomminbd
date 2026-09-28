# API DESIGN

## 1. Versioning & Base URL

All endpoints are under `/api/v1/`. Breaking changes get a new version
prefix (`/api/v2/`); we never break `/api/v1/` contracts once the
frontend depends on them.

## 2. Response Envelope

Every response — success or failure — uses the same shape (spec section 108):

```json
{
  "success": true,
  "message": "Store fetched successfully.",
  "data": { "...": "..." },
  "meta": { "...": "pagination, counts, etc." }
}
```

Errors:

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "data": null,
  "errors": { "email": ["The email field is required."] }
}
```

Implemented via `App\Support\ApiResponse` (static helpers `success()`,
`error()`) and a custom `Handler`/exception renderer so every exception
(validation, auth, authorization, not-found, 500) is normalized to this
shape — controllers never hand-build error JSON.

## 3. Authentication

- Laravel Sanctum, token-based (SPA cookie mode is available later for
  same-domain deployments; V1 ships bearer tokens for simplicity across
  admin + storefront + future mobile clients).
- `POST /api/v1/auth/register` — customer/staff registration (staff
  creation is also available via `POST /api/v1/users` under permission).
- `POST /api/v1/auth/login` — returns `{ user, token, permissions }`.
- `POST /api/v1/auth/logout` — revokes the current token.
- `GET /api/v1/auth/me` — current user + roles + permissions + current
  store context.
- `POST /api/v1/auth/forgot-password` — emails a reset link/token.
- `POST /api/v1/auth/reset-password` — consumes the token, sets new password.

Every other `/api/v1/*` route requires `auth:sanctum` middleware except
the `api/v1/storefront/*` prefix (Phase 16 Wave 1 — see section 9) and
two routes inside `api/v1/account/*` (Phase 17 Wave 1 — also section 9),
`POST account/auth/register` and `POST account/auth/login` themselves;
every other route under that prefix is `auth:sanctum` plus a `customer`
middleware, never `staff` — see `ARCHITECTURE.md` section 3 for how one
`auth:sanctum` middleware authenticates both staff and customer bearer
tokens. Every exception is listed here explicitly and never left ungated
by omission.

## 4. Authorization

`can:<permission>` middleware or `$this->authorize()` in the controller
maps 1:1 to a Spatie permission (`products.view`, `orders.cancel`, ...).
403 responses use the standard envelope with `success:false` and a
generic "You do not have permission to perform this action." message —
never leaking which permission was missing beyond what the user already
has visibility into.

## 5. Resource Endpoint Conventions

Standard REST shape per resource, e.g. for `stores`:

```
GET    /api/v1/stores            list (paginated, filterable, sortable)
POST   /api/v1/stores            create
GET    /api/v1/stores/{id}       show
PUT    /api/v1/stores/{id}       update
DELETE /api/v1/stores/{id}       soft-delete
```

List endpoints accept:
- `page`, `per_page` (default 20, max 100 — spec rule 136: never load
  thousands of rows unbounded)
- `sort` (e.g. `-created_at`)
- `filter[<field>]` query params
- `search` for free-text where applicable

`meta` on list responses always includes
`{ current_page, per_page, total, last_page }`.

## 6. Resources Planned for Later Phases

`payments`, `blog`, `media`, `seo`, `settings` —
each gets its own controller/request/resource set when its phase lands;
none are stubbed early to avoid dead routes (spec rule 178: no fake
functionality). `orders`, `customers`, `couriers`, `shipments`,
`cod-settlements`, `returns`, and `pages` are implemented — see section 9.

## 7. Webhooks (future phases)

`order.created`, `order.updated`, `order.cancelled`, `payment.received`,
`shipment.created`, `shipment.updated`, `product.updated`,
`inventory.updated`, `customer.created` — dispatched via Laravel events
→ queued `WebhookDispatchJob`, signed with an HMAC secret per
subscriber. Not implemented until an event-producing phase exists.

## 8. Rate Limiting

No global `throttle:api` is actually wired up (verified empirically:
70 rapid unauthenticated requests to a storefront GET all returned 200
— this line previously claimed one existed and was wrong; Laravel 11+'s
`bootstrap/app.php` middleware stack needs it added explicitly, and
nothing here does). What's real is per-route: `auth/register`,
`auth/login`, `auth/forgot-password`, and `auth/reset-password` each get
`throttle:6,1` to blunt credential-stuffing/brute force per spec section
107 — `account/auth/register` and `account/auth/login` (Phase 17 Wave 1)
get the same `throttle:6,1` for the same reason — and `POST
storefront/checkout` (Phase 16 Wave 1) gets `throttle:15,1`
— slightly more permissive since a real shopper legitimately retrying a
declined checkout isn't an attack the way six failed logins is, but
still bounded, since unlike every other route on the public storefront
prefix this one writes a real order and reserves real stock. Every other
route — including the rest of the public storefront prefix — has no
throttle beyond whatever the deployment's reverse proxy/WAF applies.
Adding a real global limit is tracked, not forgotten: Phase 21 (Security
Hardening) is where a deliberate choice of limits per route class
belongs, not a value invented in passing here.

## 9. What's Implemented So Far

Sections 2–5 (envelope, Sanctum auth endpoints, `auth:sanctum` +
`can:` middleware wiring) plus full CRUD for `stores`, `warehouses`,
`users`, `roles` (+ `permissions` listing), `categories`, `brands`,
`products` (+ image sub-resource endpoints), and read-only listings for
`currencies` and BD `locations`. A generic `POST /uploads` endpoint
handles category/brand image uploads.

Catalog — variable products (Phase 5 Wave 2a): full CRUD for
`product-attributes` (`attributes.view/create/update/delete`, standard
Eloquent policy) plus nested `POST/PUT/DELETE
/product-attributes/{id}/values(/{value})` for managing an attribute's
values (same nested-sub-resource pattern as
`customers/{id}/addresses`). `POST /products/{id}/variants/generate`
takes `attribute_value_ids` and creates the cartesian product of those
values as variants, skipping any combination that already exists as a
variant; `PUT/DELETE /products/{id}/variants/{variantId}` edit or
remove one. All three variant actions check `products.update` directly
(the same `$user->can()` pattern as `ProductImageController`, since a
variant is a product sub-resource, not its own policy). Generating
variants for a `simple` product, or for attribute values from a
different store, returns a 422. `DELETE /products/{id}/variants/{variantId}`
now also returns a 422 if the variant has any `stock_levels` row at all
(even a zeroed-out one) — see the variant-aware retrofit note below.

Catalog CSV import/export (Phase 5 Wave 2b): `GET /products/export`
takes the same filters as `GET /products` (`store_id`, `search`,
`category_id`, `brand_id`, `status`) but streams every matching row —
not just the current page — as a `text/csv` download (`Content-
Disposition: attachment`), gated by `viewAny` on `Product` like the list
itself. `POST /products/import` is a `multipart/form-data` upload
(`store_id`, `file`; `mimes:csv,txt`, 5 MB max) gated by a direct
`products.create` check; it upserts by `(store_id, sku)` and always
returns `200` with a summary — `{created, updated, skipped, errors:
[{row, message}]}` — rather than a single failing status, since a big
file is expected to partially succeed (a `422` is reserved for the file
itself being unreadable or missing its required SKU/Name/Price columns
entirely). `App\Support\ProductCsv::HEADERS` is the shared column list
both endpoints read — see `DATABASE_DESIGN.md` section 1j for the exact
semantics (case-insensitive Category/Brand auto-create, `type`/variants
never touched by an update, and why `status`/`track_stock`/`featured`
are the one set of columns where a blank cell means "leave unchanged"
rather than "clear it").

Variant-aware Orders/Inventory/Purchasing retrofit: every endpoint below
that accepts a line item (`items[].product_id`) now also accepts an
optional sibling `items[].product_variant_id` (a flat
`product_variant_id` for `POST /stock-adjustments`, which only ever
handles one product at a time) — `POST/PUT /orders`, `POST/PUT
/purchase-orders`, `POST /stock-adjustments`, and `POST
/stock-transfers`. A submitted variant is validated (`App\Rules\
VariantBelongsToProduct`) to actually belong to the submitted product,
returning a 422 on the relevant `items.N.product_variant_id` field (or
plain `product_variant_id` for stock-adjustments) if not; omitting it (or
sending `null`) still means "the simple product itself," so every
existing integration keeps working unchanged. Every resource that
returns a line item — `OrderResource`, `PurchaseOrderResource`
(including its nested `receipts`), `PurchaseReceiptResource`,
`StockTransferResource`, `StockMovementResource`, and `ReturnResource` —
now includes the variant it resolved to (`product_variant: {id, sku,
attribute_values}` for the four line-item resources that carry a full
snapshot, or a flattened `product_variant_sku` for the two that already
kept their item shape lighter). `GET /products` and `GET
/products/{id}`'s `variants[]` now also carry a `stock_summary`
(`total_quantity`/`total_reserved`/`total_available` plus a
`by_warehouse[]` breakdown), since the global `GET /stock-levels` list
deliberately did not gain per-variant rows (see `DATABASE_DESIGN.md`
section 1c) — this is the one place variant-level stock is visible over
the API. `GET /stock-levels` itself is unchanged at the wire level (same
one-row-per-product shape) but for a variable product that row is now
the *sum* across all its variants' stock at that warehouse, not a single
arbitrary variant's row.

Inventory (Phase 6 Wave 1): `GET /stock-levels` (per-warehouse on-hand
quantity per product, `low_stock` filter) + `GET .../low-stock-count`
(scalar count backing the dashboard KPI), `GET /stock-movements` (the
append-only ledger, filterable by product/warehouse/type),
`POST /stock-adjustments` (manual increase/decrease with a reason), and
`GET/POST /stock-transfers` + `GET .../{id}` (multi-item warehouse-to-
warehouse transfer, executed atomically). All gated by the existing
`inventory.view` / `inventory.adjust` / `inventory.transfer` permissions
via direct `$user->can()` checks (like `UploadController`, since these
endpoints span multiple models rather than mapping to one Eloquent
policy).

Purchasing (Phase 7 Wave 1): full CRUD for `suppliers`
(`suppliers.view/create/update/delete`, standard Eloquent policy).
`GET/POST/PUT/DELETE /purchase-orders` + `GET .../{id}` (PUT/DELETE only
while `status = draft` — items are replaced wholesale, same one-shot
pattern as `stock-transfers`), `POST .../{id}/place` (draft → ordered,
locks items), `POST .../{id}/cancel` (draft/ordered → cancelled), and
`POST .../{id}/receipts` (records a `purchase_receipts` row, rejects
over-receiving beyond what remains on each line, and — inside the same
transaction — writes the `purchase_receipt` `stock_movements` type and
updates `stock_levels`). `GET /purchase-orders` also accepts `open=1` to
return only `draft`/`ordered`/`partially_received` orders, backing the
dashboard's "Open purchase orders" KPI. `purchase_orders.*` uses a
standard policy (`view`/`create`/`update`/`cancel`); `purchase_orders.receive`
is checked directly in `PurchaseReceiptController`, the same
direct-`$user->can()` pattern as inventory, since receiving is a
distinct action from editing a PO's terms.

Purchasing returns (Phase 7 Wave 2a): `POST
/purchase-orders/{id}/returns` requests a return against a purchase
order with something actually received (`partially_received`/`received`
only — a 422 otherwise); a line's eligible quantity is capped by
`quantity_received` minus whatever a non-rejected return already covers
on it, not `quantity_ordered` (see `DATABASE_DESIGN.md` section 1m).
`GET /purchase-returns` + `GET .../{id}`, and the status-transition
actions `POST .../{id}/approve`, `.../reject` (reachable from
`requested` or `approved`), `.../ship-back` (approved → shipped_back —
decrements `stock_levels` at the PO's own warehouse and writes a new
`purchase_return`-type `stock_movements` row, inside a locked
transaction; a 422 if on-hand stock has since dropped below the return's
quantity), and `.../credit` (shipped_back → credited — accepts an
optional `credit_amount`, defaulting to the sum of the covered items'
original unit cost; a supplier credit note, not a cash refund — nothing
exists yet to apply it against, since no accounts-payable ledger is
built). `purchase_returns.*` uses a standard policy (`view`/`create`/
`update` — the four status actions all check `update`). `GET
/purchase-orders` and `GET /purchase-orders/{id}` now also return a
`returns[]` summary (`id`, `return_number`, `status`, `credit_amount`),
the same lightweight-summary pattern `OrderResource` uses for its own
`returns[]`.

Orders (Phase 8 Wave 1): full CRUD for `customers`
(`customers.view/create/update/delete`, standard Eloquent policy,
soft-deleted) plus nested `POST/PUT/DELETE /customers/{id}/addresses(/{address})`
(no top-level address resource — addresses only exist as a customer's
children; adding/editing a default address unsets the previous one in
the same transaction, deleting the default promotes the next one).
`GET/POST/PUT /orders` + `GET .../{id}` (PUT only while `status =
pending` — items are replaced wholesale and re-reserved, same one-shot
pattern as `purchase-orders`; no `DELETE` — `cancel` is the only removal
path), `POST .../{id}/process` (pending → processing), `POST .../{id}/ship`
(pending/processing → shipped — converts the stock reservation into a
real `sale` `stock_movements` row and decrements on-hand `stock_levels.quantity`,
inside the same locked transaction as section on inventory),
`POST .../{id}/deliver` (shipped → delivered), and `POST .../{id}/cancel`
(pending/processing → cancelled — releases the reservation without
touching on-hand quantity). `GET /orders` also accepts `open=1` to
return only `pending`/`processing` orders, backing the dashboard's
"Pending orders" KPI. `orders.*` uses a standard policy (`view`/
`create`/`update`/`cancel`); creating/updating an order can fail with a
422 (`InsufficientStockException`, reused from Phase 6) when the
requested quantity exceeds what's currently available at the chosen
warehouse. `GET /stock-levels` now also returns `quantity_reserved`/
`quantity_available`, and its `low_stock` filter/`low-stock-count`
compare against *available* quantity, not raw on-hand — a Phase 8
change to Phase 6 code, covered by a regression test.

Delivery (Phase 9 Wave 1): full CRUD for `couriers`
(`couriers.view/create/update/delete`, standard Eloquent policy,
soft-deleted). `POST /orders/{id}/shipments` (assigns a courier +
tracking number to an already-`shipped` order; rejects a second
shipment for the same order), `GET /shipments` + `GET .../{id}`, and the
status-transition actions `POST .../{id}/picked-up`, `.../in-transit`,
`.../delivered` (captures `cod_amount_collected`, defaulting to the
order's total for a `cod` order when none is given, and — if the order
isn't already `delivered` — transitions it too, setting `payment_status`
to `paid` for COD; this is the only path to deliver an order that has a
shipment, since `POST /orders/{id}/deliver` now rejects one that does),
`.../failed`, and `.../returned` (failed → returned-to-seller; doesn't
touch order status, but — since Phase 10 — does restock the order's
items back into `stock_levels` and writes a `return`-type
`stock_movements` row for each, reversing the decrement `Order.ship()`
made before this shipment ever existed). `shipments.*` uses a standard
policy (`view`/`create`/`update` — the five status actions all check
`update`).
`GET/POST /cod-settlements` + `GET .../{id}` records a courier
settlement batch covering a set of delivered, unsettled `cod` shipments
— `amount_expected` is computed server-side from those shipments'
`cod_amount_collected`, the covered shipments are locked
(`lockForUpdate()`) and re-validated as still-unsettled inside the same
transaction (so two settlement requests racing on the same shipment
can't both succeed), and are marked `cod_settled` atomically with the
settlement's creation. `cod_settlements.*` uses `view`/`create` only —
no update/destroy endpoint, matching the immutable-ledger pattern of
`stock_movements`/`order_status_history`.

Returns (Phase 10 Wave 1): `POST /orders/{id}/returns` (requests a
return against a `delivered` order; rejects a quantity exceeding what
remains eligible per order item — see `DATABASE_DESIGN.md` section 1g),
`GET /returns` + `GET .../{id}`, and the status-transition actions
`POST .../{id}/approve`, `.../reject` (reachable from `requested` or
`approved`), `.../receive` (approved → received — accepts an optional
per-item `items[].restock` override; for each item whose effective
restock flag is true, adds its quantity back to `stock_levels` and
writes a `return`-type `stock_movements` row, inside a locked
transaction), and `.../refund` (received → refunded — accepts an
optional `refund_amount`, defaulting to the sum of the return's items'
line totals; flips `orders.payment_status` to `refunded` only once every
order item's ordered quantity is fully covered by the order's `refunded`
returns combined). `returns.*` uses a standard policy (`view`/`create`/
`update` — the four status actions all check `update`).

Admin Dashboard (Phase 11 Wave 1): `GET /dashboard/sales-trend`
(`store_id` + optional `days`, default 14/max 90 — per-day
`{date, orders_count, revenue_amount}`, zero-filled for days with no
orders, cancelled orders excluded, `revenue_amount` summed from
`order_items` line totals not `orders.total_amount`) and
`GET /dashboard/order-status-breakdown` (`store_id` — counts per
`orders.status`, zero-filled for every known status). Both check
`orders.view` directly (`$request->user()->can(...)`), the same
direct-permission pattern as `StockLevelController::lowStockCount()`,
since a dashboard aggregate spans multiple models rather than one
Eloquent policy. No new resource — see `DATABASE_DESIGN.md` section 2.

Reporting (Phase 18 Wave 1 + Wave 2): `GET /reports/sales` (`store_id`,
`date_from`, `date_to` — capped at a 366-day span, optional
`warehouse_id`, optional `granularity` of `day`/`week`/`month`, default
`day`) returns `{totals: {revenue_amount, orders_count,
average_order_value}, by_period: [...], by_payment_method: [...],
by_courier: [...], comparison: {date_from, date_to, totals: {...}}}` —
`by_courier` (Wave 2a) inner-joins `shipments`/`couriers`, so it only
covers orders that actually reached a courier and is expected to
undercount `totals.orders_count` for any range with orders still
awaiting dispatch; `comparison` (Wave 2b) is the same `totals` shape
computed over the immediately preceding period of equal length (not a
fixed "last calendar month"), same `store_id`/`warehouse_id` filters —
plus a `GET /reports/sales/export` CSV twin (period rows only; neither
`by_payment_method`, `by_courier`, nor `comparison` are in the CSV) and
a `GET /reports/sales/export-pdf` twin (Wave 2c — deliberately richer
than the CSV: KPI totals, trend, and both breakdowns included, since a
PDF is a presentable snapshot of the whole page). `GET
/reports/products-performance` (same date/warehouse filters, paginated)
ranks products by revenue, rolling a variable product's variant sales up
to the parent product — plus `GET /reports/products-performance/export`
(CSV, unpaginated, every matching row) and `.../export-pdf` twins. `GET
/reports/low-stock` (`store_id` only, paginated) sums quantity/reserved
across every warehouse per product and returns only rows at or below
their `low_stock_threshold` — plus `GET /reports/low-stock/export` (CSV)
and `.../export-pdf` twins. All nine actions check `reports.view`
directly (same direct-permission pattern as the Dashboard endpoints
above), activating a permission the RBAC seeder has carried since
Phase 3. No new resource — see `DATABASE_DESIGN.md` section 1k.

Bundles/Combos (Phase 5 Wave 2c): `POST /products/{product}/components`
adds a component (`product_id`, optional `product_variant_id`,
`quantity`) to a bundle — rejects with a 422 if `{product}` isn't itself
a `type=bundle` product, or with a validation error on `product_id` if
the component is the bundle itself, is itself a bundle (no nested
bundles), or is already a component of this bundle; `product_variant_id`
reuses `App\Rules\VariantBelongsToProduct` unchanged. `PUT/DELETE
/products/{product}/components/{component}` update a component's
quantity or remove it, returning 404 if `{component}` doesn't belong to
`{product}`. All three check `products.update` directly, the same
sub-resource pattern as `ProductVariantController`/`ProductImageController`.
`GET /products` and `GET /products/{id}` now also return `components[]`
(`{id, product_id, product_name, product_sku, product_variant_id,
product_variant_sku, quantity}`) for every product, and — only when
`type=bundle` — `bundle_availability: {total_available, by_warehouse:
[{warehouse_id, warehouse_name, available}]}`, the bundle's derived
sellable quantity (see `DATABASE_DESIGN.md` section 1l). `POST/PUT
/products` now accept `type=bundle` but force `track_stock` to `false`
and `low_stock_threshold` to `null` server-side whenever the effective
type is bundle, regardless of what's submitted, so a bundle can never
incorrectly show up as "low stock." `POST/PUT /orders` line items can
now target a bundle product directly — its components are resolved and
snapshotted into `order_item_components` at creation time, and
`OrderResource` returns each bundle line item's resolved
`components: [{product_id, product_name, sku, product_variant_sku,
quantity}]` (`null` for a non-bundle item) for packing visibility;
shipping, cancelling, and returning a bundle order reserves/decrements/
restocks its components, never the bundle itself. `POST
/stock-adjustments`, `POST /stock-transfers`, and `POST/PUT
/purchase-orders` all now reject a bundle `product_id` with a 422 via
the shared `App\Rules\ProductIsNotBundle` rule — a bundle is never
bought, adjusted, or moved directly, only ordered. `GET /stock-levels`
and `GET /reports/low-stock` both exclude bundles (the low-stock query's
`track_stock=true` filter already excludes any bundle, since that's
forced `false` server-side; `GET /stock-levels` additionally filters
`type != 'bundle'` explicitly, since its left join would otherwise show
a bundle as a misleading "0 on hand" row); `GET
/reports/products-performance` is unaffected and shows a bundle as its
own ranked row, same as any other product.

Storefront (Phase 16 Wave 1): a new `api/v1/storefront/*` prefix, the
first routes in this app registered outside `auth:sanctum` entirely —
the exception section 3 said would exist and be listed explicitly, now
that it does. `GET store` returns the current store's public identity
(`name`, `slug`, `currency_code`) for header/page-title branding — never
the full admin `StoreResource`, which exposes `organization_id` and
operator settings. `GET categories` (top-level, active, with active
`children`) + `GET categories/{slug}` (that category + its own active
products, paginated), and `GET brands` + `GET brands/{slug}` mirror each
other exactly. `GET products` (`search`, `category`, `brand`, `featured`,
`sort` of `price_asc`/`price_desc`/`newest`, paginated, active-only) and
`GET products/{slug}` (full PDP: variants with per-variant `in_stock`,
bundle `components[]` + `bundle_availability`, no `cost_price` anywhere)
both compute `in_stock` in bulk — one grouped query per page for
non-bundle products, falling back to `BundleExpander::availability()`
only for the bundle rows — so a listing page never pays an N+1 query for
it (see `DATABASE_DESIGN.md` section 1n). `GET locations/divisions`
`/districts` `/upazilas` are the same `LocationController` the admin app
uses under `auth:sanctum` below, just also reachable here without a
token — nationwide BD reference data has no per-store scoping and
nothing sensitive to gate.

`POST checkout` is the one write path, and the one endpoint in this
entire API with a different trust model from every other write endpoint:
its request accepts `customer_name`/`customer_phone`/`customer_email`,
`shipping_recipient_name`/`shipping_phone`/`shipping_address_line`/
`shipping_bd_division_id`/`shipping_bd_district_id`/
`shipping_bd_upazila_id`, optional `notes`, and
`items[].{product_id, product_variant_id, quantity}` — **no `unit_price`
field at any level**, unlike `POST/PUT /orders` below where staff may
legitimately override a line's price. The server always resolves the
current effective price itself (sale price if set, else regular price,
variant overrides falling back to the parent product field-by-field —
the same resolution `VariantResource` uses to decide what a shopper sees
on the PDP), rejects any `product_id`/`product_variant_id` that isn't
active and in the current store with a 422, and rejects a duplicate
product/variant line the same way `POST/PUT /orders` does. Warehouse
selection is automatic (no `warehouse_id` in the request at all): the
first active warehouse whose stock, expanded through
`BundleExpander::expand()`, can fully cover every resolved line; a 422
("...currently out of stock") if none can. The customer is matched to an
existing `Customer` by `(store_id, phone)` via `firstOrCreate` — no new
identity concept, and never renamed on a repeat match. On success it
returns a public receipt via a storefront-only `OrderResource` (`uuid`,
`order_number`, `status`, `payment_method` — always `cod` — `items[]`,
shipping snapshot, `subtotal_amount`/`shipping_amount`/`discount_amount`/
`total_amount`) that deliberately omits the sequential `id`, `warehouse`,
`created_by`, and `status_history` the admin `OrderResource` includes.
`GET orders/{uuid}` re-fetches that same receipt — looked up by `uuid`
only; the sequential `id` is never a valid lookup key here, even though
it's the primary key everywhere else in this API. `POST checkout` is
throttled `throttle:15,1`, the same abuse-guard reasoning as `auth/login`
in section 8, since unlike every other endpoint on this prefix it writes
a real order and reserves real stock.

Customer Account (Phase 17 Wave 1): a new `api/v1/account/*` prefix,
customer-facing rather than public (Storefront above) or staff (every
other prefix). `POST account/auth/register` (`name`, `phone`, optional
`email`, `password`+`password_confirmation`) either creates a fresh
`Customer` or, if `(store_id, phone)` already matches an unclaimed
guest-checkout row (`password === null`), claims it — sets the password,
updates name/email — instead of creating a duplicate; a match with a
password already set 422s ("...sign in instead"). `POST account/auth/login`
(`phone`, `password`) rejects an unclaimed phone the same way a wrong
password does, a deliberately generic "incorrect credentials" response
rather than confirming whether an account exists. Both return `{customer,
token}` via the existing admin `CustomerResource` (safe here — a customer
reading their own record isn't the leak scenario Storefront's *public*
resources guard against) plus a new bearer token; `POST .../logout` and
`GET .../me` behave exactly like their staff `auth/*` equivalents, just
`customer`-gated instead of `staff`-gated. `GET account/orders` and `GET
.../orders/{uuid}` are always scoped to `Auth::id()` — there is no
`customer_id` parameter to override, and someone else's order 404s rather
than 403ing, so a valid UUID can't be used to confirm another customer's
order exists. `account/addresses` is full CRUD over the caller's own
`customer_addresses`, identical semantics to the admin
`customers/{id}/addresses` (including the same single-default-address
transaction) but with no `{customer}` route parameter to substitute
someone else's id into; a mismatch on update/delete 404s the same way.
`PUT account/profile` only accepts `name`/`email` — `phone` is the login
identifier and isn't editable here, since changing it would need a
re-verification flow this Wave doesn't build. `CustomerResource` also
gained `has_account` (`password !== null`) for the admin Customers
list/detail to show a Claimed/Guest badge. See `DATABASE_DESIGN.md`
section 1o and `ARCHITECTURE.md` section 3 for the schema and the
two-identity Sanctum design behind all of the above.

Notifications (Phase 19 Wave 1): no new public endpoints — order/return
lifecycle actions that already existed (`OrderController::store/process/
ship/deliver/cancel`, `ReturnController`'s approve/reject/receive/refund)
now also fire a Notification as their last step, customer-facing ones via
`mail` + a custom `sms` channel (see `ARCHITECTURE.md` section 6 for the
`SmsGateway` contract behind it), a storefront-order-only one to staff via
Laravel's `database` channel. The one new surface is a staff member's own
notification inbox: `GET notifications` (paginated, newest first, `meta.
unread_count` alongside the usual pagination fields), `POST
notifications/read-all`, and `POST notifications/{id}/read` — all three
always resolve against `$request->user()->notifications()`, never a
client-supplied user id, so there's no permission to gate beyond being an
authenticated staff user (`auth:sanctum` + `staff`, same as every other
route in this block) — the same reasoning `auth/me` already established.
A notification not belonging to the caller simply isn't found (`{id}/read`
404s), never leaked or reassigned. `data` on a listed notification is raw
structured fields (`order_id`, `order_number`, `customer_name`, a `type`
discriminator), not a pre-formatted string — the frontend renders it, same
split every other resource in this API follows.

Pages (Phase 12 Wave 1): admin `GET/POST/PUT/DELETE pages` follows the
exact Category/Brand shape — flat list (search + `store_id` filter, no
pagination), `PageRequest` validates `slug` unique per `store_id` (same
`Rule::unique(...)->where(...)->ignore($id)` idiom), gated on a single
`pages.manage` permission via `PagePolicy` rather than a 4-way split, since
`pages.manage` already existed in the RBAC seeder before this phase (see
`DATABASE_DESIGN.md` section 1q). Two public, unauthenticated, store-scoped
endpoints mirror Storefront's existing category/brand/product read
endpoints: `GET storefront/pages` (published-only, ordered by title, backs
the footer's page-links column) and `GET storefront/pages/{slug}`
(published-only; a draft slug or one from another store both 404, never
403 — the same "don't confirm existence" reasoning Storefront's other
`{slug}` lookups already use). The public `Storefront\PageResource` returns
only `title`/`slug`/`content`/`meta_title`/`meta_description` — no `id`,
`status`, or `created_by`, the same admin-vs-public field split every other
Storefront resource in this API already draws.

Section 7 (webhooks) remains documented intent for future phases.
