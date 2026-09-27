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

`orders`, `customers`, `payments`, `couriers`, `pages`, `blog`, `media`,
`seo`, `reports`, `settings` — each gets its own controller/request/resource
set when its phase lands; none are stubbed early to avoid dead routes
(spec rule 178: no fake functionality).

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

Section 6 (orders/etc.) and section 7 (webhooks) remain documented
intent for future phases.
