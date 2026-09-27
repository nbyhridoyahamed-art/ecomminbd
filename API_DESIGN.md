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

`products`, `categories`, `brands`, `orders`, `customers`, `inventory`,
`warehouses`, `payments`, `couriers`, `pages`, `blog`, `media`, `seo`,
`reports`, `settings` — each gets its own controller/request/resource
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

## 9. What This Session Implements

Sections 2–5 are implemented now (envelope, Sanctum auth endpoints,
`auth:sanctum` + `can:` middleware wiring, the `stores`/`warehouses`
CRUD as the first concrete example of the resource convention). Section
6 (catalog/orders/etc.) and section 7 (webhooks) are documented intent
for future phases.
