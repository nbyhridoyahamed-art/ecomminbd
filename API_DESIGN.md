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

Every other `/api/v1/*` route requires `auth:sanctum` middleware; the
storefront's public catalog endpoints (future phases) are the only
intentional exception and are listed explicitly in their own phase doc,
never left ungated by omission.

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

`payments`, `pages`, `blog`, `media`, `seo`, `reports`, `settings` —
each gets its own controller/request/resource set when its phase lands;
none are stubbed early to avoid dead routes (spec rule 178: no fake
functionality). `orders`, `customers`, `couriers`, `shipments`,
`cod-settlements`, and `returns` are implemented — see section 9.

## 7. Webhooks (future phases)

`order.created`, `order.updated`, `order.cancelled`, `payment.received`,
`shipment.created`, `shipment.updated`, `product.updated`,
`inventory.updated`, `customer.created` — dispatched via Laravel events
→ queued `WebhookDispatchJob`, signed with an HMAC secret per
subscriber. Not implemented until an event-producing phase exists.

## 8. Rate Limiting

`throttle:api` (60 req/min per token) globally; auth endpoints
(`login`, `forgot-password`) get a tighter `throttle:6,1` to blunt
credential-stuffing/brute force per spec section 107.

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
different store, returns a 422.

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

Section 7 (webhooks) remains documented intent for future phases.
