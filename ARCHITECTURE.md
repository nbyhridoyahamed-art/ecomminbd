# ARCHITECTURE

## 1. High-Level System

```
                         ┌─────────────────────────┐
                         │        Customers         │
                         │  (Storefront, mobile)    │
                         └────────────┬─────────────┘
                                      │ HTTPS
                         ┌────────────▼─────────────┐
                         │   frontend/ (Next.js)     │
                         │  Storefront + Admin UI     │
                         │  App Router, RSC, TS       │
                         └────────────┬─────────────┘
                                      │ REST /api/v1 (JSON, Bearer/Sanctum)
                         ┌────────────▼─────────────┐
                         │   backend/ (Laravel API)  │
                         │  Controllers → Actions →   │
                         │  Services → Repositories → │
                         │  Models → Resources         │
                         └──┬───────┬───────┬────────┘
                            │       │       │
                     ┌──────▼─┐ ┌───▼───┐ ┌─▼─────────┐
                     │ MySQL  │ │ Redis │ │ Queue      │
                     │  8+    │ │ cache │ │ workers    │
                     └────────┘ └───────┘ └────────────┘
                                              │
                         ┌────────────────────▼────────────────────┐
                         │ External adapters (all interface-based)  │
                         │ Payments: bKash/Nagad/Rocket/Card/Manual  │
                         │ Couriers: Pathao/Steadfast/RedX/...       │
                         │ Notify: Email/SMS/WhatsApp                │
                         └────────────────────────────────────────┘
```

## 2. Monorepo Layout

```
/backend    Laravel 11 API-only application
/frontend   Next.js 15 application (admin + storefront + customer account)
/docs       (future) deployment.md, courier-integrations.md, etc.
*.md        Root-level planning docs (this suite)
```

The two apps communicate exclusively over HTTP (`/api/v1/*`). The
frontend never talks to the database directly. This keeps the backend
authoritative for every business rule (money, stock, permissions) and
lets a future mobile app or POS reuse the same API.

## 3. Backend Layered Architecture

Controllers stay thin. The flow for a write operation is:

```
Route → Middleware (auth, permission) → Controller
      → FormRequest (validation)
      → Action (single business operation, e.g. CreateOrderAction)
      → Service (orchestration across multiple actions/repositories)
      → Repository (query logic, Eloquent isolation)
      → Model / Events
      → Resource (API response shape)
```

Folder responsibilities (`backend/app/`):

| Folder | Responsibility |
|---|---|
| `Actions/` | One class = one business operation (`CreateStoreAction`, `AssignRoleAction`). Invokable, unit-testable, no HTTP concerns. |
| `DTOs/` | Immutable data carriers between layers (avoids passing raw arrays/Request objects into Actions). |
| `Enums/` | PHP 8.1+ backed enums for statuses, roles, permission groups, etc. — never magic strings. |
| `Events/` / `Listeners/` | Domain events (`OrderPlaced`, `StockAdjusted`) and their side effects (notifications, cache invalidation). |
| `Exceptions/` | Domain-specific exceptions with proper HTTP mapping. |
| `Http/Controllers`, `Http/Requests`, `Http/Resources`, `Http/Middleware` | Standard Laravel HTTP layer — controllers only orchestrate, they never contain business logic. |
| `Jobs/` | Queueable work (imports, exports, report generation, sitemap builds). |
| `Models/` | Eloquent models — relationships, casts, scopes only. No business logic beyond simple accessors. |
| `Notifications/` | Laravel notification classes (multi-channel: mail/db/SMS/WhatsApp adapters). |
| `Policies/` | Laravel authorization policies, one per resource, backed by Spatie permissions. |
| `Repositories/` | Query encapsulation for anything beyond trivial Eloquent calls; keeps Actions free of query-builder noise. |
| `Rules/` | Custom validation rules (BD phone number, BD postal code, slug uniqueness, etc.). |
| `Services/` | Cross-cutting orchestration (e.g. `OrderCalculationService`, `InventoryLedgerService`) that multiple Actions depend on. |
| `Support/` | Small framework-agnostic helpers (e.g. `ApiResponse`, `Money` value object). |

Two authenticated identities share the one `auth:sanctum` middleware
above rather than a second Sanctum guard: `App\Models\User` (staff) and,
since Phase 17, `App\Models\Customer` both use `HasApiTokens`, and
Sanctum resolves whichever one actually owns the bearer token via
`personal_access_tokens`' polymorphic `tokenable` relation — no
`config/auth.php` change needed. The access boundary between them is two
small `staff`/`customer` middleware aliases
(`EnsureStaffUser`/`EnsureCustomerUser`) stacked alongside `auth:sanctum`,
type-checking `$request->user()` and 401ing on a mismatch, rather than
left to an unverified assumption that a `User`-type-hinted Policy would
fail closed against a `Customer` instance. See `DATABASE_DESIGN.md`
section 1o and this doc's section 10 for the rest of the Phase 17 design.

## 4. Multi-Store / Multi-Tenant Foundation

```
organizations (1) ─── (N) stores ─── (N) warehouses
       │                    │
       │                    └── (N) users (via store_user pivot + role)
       └── (N) users (organization owner/staff)
```

- `organizations` is the top-level tenant boundary (prepares future SaaS
  multi-tenancy — spec section 5/187).
- `stores` belong to an organization. A user can belong to multiple
  stores through a pivot table carrying their role in that store.
- Store-scoped tables (products, orders, warehouses, settings, etc.)
  carry a `store_id` foreign key. Global/shared tables (BD divisions,
  permissions, currencies) do not.
- V1 seeds exactly one organization and one store, but no code assumes
  singularity — every query that should be store-scoped already filters
  by `store_id`.

## 5. RBAC

- Powered by `spatie/laravel-permission`.
- Permissions follow the `resource.action` convention mandated by the
  spec (`products.view`, `orders.cancel`, `seo.manage`, ...).
- The 17 default roles from spec section 6 are seeded with a starting
  permission set per role; Super Admin gets everything.
- Every protected controller action calls `$this->authorize()` against a
  Policy — **hiding a UI button is never sufficient authorization**
  (spec rule 183). Policies are the single source of truth; the frontend
  only reads `/api/v1/auth/me` permissions to decide what to *render*,
  never to decide what is *allowed*.

## 6. Adapter Pattern for External Integrations

Every third-party integration is defined as a PHP interface with a mock
implementation shipped first, and real providers added without touching
calling code (spec sections 30, 151, 152, 179):

```php
interface CourierInterface {
    public function createShipment(...): ShipmentDTO;
    public function cancelShipment(string $trackingId): bool;
    public function trackShipment(string $trackingId): TrackingDTO;
    public function calculateDeliveryCharge(...): Money;
    public function getStatus(string $trackingId): string;
    public function getLabel(string $trackingId): string; // URL or binary
}

interface PaymentGatewayInterface {
    public function createPayment(...): PaymentIntentDTO;
    public function verifyPayment(string $transactionId): PaymentStatusDTO;
    public function refundPayment(string $transactionId, Money $amount): RefundDTO;
    public function getTransaction(string $transactionId): TransactionDTO;
    public function handleWebhook(Request $request): void;
}
```

A `CourierManager` / `PaymentGatewayManager` resolves the concrete
adapter from store settings at runtime (Laravel Manager pattern), so
adding "Steadfast" later is a new adapter class + a config entry, never
a change to `OrderService` or `ShipmentService`. Neither interface is
built yet — Delivery (Phase 9) shipped real `couriers`/`shipments`
tracking without one (a courier is a manually-entered record; there is
no outbound API call to Pathao/Steadfast to adapt in the first place),
and Orders Wave 2's payment gateway ledger is still deferred (see
`DATABASE_DESIGN.md` section 2) — this section still documents the
target contract for whichever phase builds the real outbound call.

Phase 19 Wave 1 is this pattern's first real, shipped instance, on a
smaller integration: `App\Contracts\SmsGateway` (one method,
`send(string $to, string $message): void`) bound to
`App\Services\Sms\LogSmsGateway` in `AppServiceProvider::register()` —
Wave 1's only implementation, since no BD SMS provider credentials exist
in this environment (`PROJECT_AUDIT.md`), so it logs the message it would
have sent rather than pretending to deliver it. Every caller depends on
the interface; swapping in a real provider (SSL Wireless, Alpha SMS, ...)
later is one binding change, not a rewrite. A custom Laravel notification
channel, `App\Notifications\Channels\SmsChannel`, resolves `SmsGateway`
from the container and is referenced by its class name directly from a
notification's `via()` — see the Phase 19 Wave 1 scope note in
`DEVELOPMENT_ROADMAP.md` for what actually triggers it.

## 7. Financial & Inventory Integrity

- All money fields are stored as integer **minor units** (paisa) in the
  database and converted to/from decimal only at the API/UI boundary via
  a `Money` value object — never floats (spec rule 27/182).
- Every stock change writes an immutable `stock_movements` ledger row
  (`type`: `adjustment_increase`/`adjustment_decrease`/`transfer_in`/
  `transfer_out`/`purchase_receipt`/`sale`/`return`) recording a
  before/after quantity snapshot. `stock_levels.quantity` is a
  materialized cache of "current stock," not derived by summing the
  ledger on every read — but it is only ever written inside the same DB
  transaction as the movement row that explains the change, with
  `lockForUpdate()` held on it throughout, so the two can never drift
  (spec section 18–19). Landed in Phase 6; Phase 7 added
  `purchase_receipt`, Phase 8 added `sale`, and Phase 10 added `return`
  (with two real producers: a received customer return, and a
  `returned_to_seller` shipment — see below) as real non-manual
  producers. Inventory *reservation* (`stock_levels.quantity_reserved`,
  distinct from on-hand `quantity`) also landed in Phase 8 — see
  `DATABASE_DESIGN.md` section 1e. Phase 9's `shipments`/`shipment_status_history`,
  `cod_settlements`/`cod_settlement_shipments`, and Phase 10's
  `returns`/`return_items`/`return_status_history` are their own
  append-only ledgers of the same shape, layered on top rather than
  always touching `stock_movements` directly — a shipment reaching
  `delivered` doesn't write a new stock movement (the `sale` movement
  already happened at `Order.ship()`); it only updates the order's
  status/`payment_status` and records the COD amount collected. A
  shipment reaching `returned_to_seller`, and a return reaching
  `received`, are the exception: both *do* write a real `return`
  movement, since goods are physically coming back and the earlier
  `sale` decrement needs reversing.
- Order creation, payment capture, inventory reservation, purchase
  receiving, and returns/refunds all run inside DB transactions (spec
  rule 180/114). Purchase receiving (Phase 7), order reservation/shipment
  (Phase 8), shipment status transitions plus COD settlement (Phase 9),
  and the return lifecycle plus its refund/payment-status reconciliation
  (Phase 10) are built — see
  `PurchaseReceiptController`/`OrderController`/`ShipmentController`/`CodSettlementController`/`ReturnController`.

## 8. Caching & Queues

- Redis is the target cache/queue driver in production; settings,
  navigation, categories, and the published homepage are cached and
  invalidated on write (spec section 112–113).
- Heavy work (CSV import/export, image processing, report generation,
  sitemap builds, notification delivery) runs on queued jobs, never
  synchronously in a request (spec section 110).

## 9. Frontend Architecture

- Next.js App Router, TypeScript strict mode.
- **What's actually built, not the original plan:** every admin
  (`(admin)/`) and account (`(auth)/`, `account/`) page is a Client
  Component (`"use client"`), fetching via TanStack Query hooks against
  the JSON API — not "Server Components by default" as this section
  originally said, and this part of the gap is unchanged.
- **Phase 15 closed the storefront half of that gap.** Every storefront
  *leaf* page (`/products/[slug]`, `/category/[slug]`, `/brand/[slug]`,
  `/blog/[slug]`, `/blog/category/[slug]`, `/blog/tag/[slug]`,
  `/pages/[slug]`, and the homepage `/`) is now an `async` Server
  Component (`page.tsx`) with a real `generateMetadata()` — a genuine
  server-side fetch, this app's first, via a new server-only
  `src/lib/storefront-api.ts` client. That client exists as a separate
  module because the existing browser-oriented `src/lib/api.ts` imports
  `auth-token.ts` (a `"use client"` module reading `localStorage`), and
  React Server Components can't call a function exported from a Client
  Component module — only render it as a component; `storefront-api.ts`
  has no such import, since the storefront endpoints it calls are all
  public and unauthenticated. The existing interactive body of each page
  is unchanged, just renamed into a sibling `*-client.tsx` file that
  keeps doing its own client-side TanStack Query fetch exactly as
  before — "fetch twice, once per side," exactly as this section
  previously named it as the anticipated fix, rather than a full
  storefront data-flow rewrite. Every leaf page also emits real JSON-LD
  (`src/lib/json-ld.tsx`: Product/BreadcrumbList/Article/
  Organization+WebSite) and checks for a configured redirect (via
  `src/lib/storefront-seo.ts`'s `resolveRedirectOrNotFound()`) only when
  its own by-slug lookup 404s — never global middleware, so an ordinary
  request never pays for a redirects-table lookup it doesn't need.
  Listing/browse pages (`/products`, `/brands`, `/blog` index, `/cart`,
  `/checkout`, admin/account pages generally) remain plain Client
  Components; they were never the pages needing per-entity `<title>`/
  meta description in the first place.
- `sitemap.xml`/`robots.txt` are Next.js's own native
  `src/app/sitemap.ts`/`src/app/robots.ts` special files (`force-dynamic`
  on the sitemap, so it reflects the live catalog rather than a stale
  build-time snapshot), not a Laravel endpoint — robots.txt must disallow
  this same Next.js app's own admin/account/cart/checkout paths, which
  the Laravel API has no visibility into. Those admin/account paths
  don't share a common URL prefix (no literal `/admin`) — the `(admin)`
  and `(auth)` route groups add no path segment of their own, so each
  real top-level segment (`/dashboard`, `/catalog`, `/content`,
  `/delivery`, `/inventory`, `/orders`, `/purchasing`, `/reports`,
  `/settings`, `/account`, `/login`) is disallowed by name.
- **Phase 20 adds one small, deliberately separate client:**
  `src/lib/analytics.ts`'s `trackEvent()`, called from the storefront
  (a page-view tracker mounted once in the storefront layout, the cart
  store's own add/remove actions, and a handful of page-load effects) to
  fire behavioral events at `POST storefront/analytics/events`. It's
  fire-and-forget by design — `fetch(..., {keepalive: true})`, so a
  `page_view` fired right before navigating away still lands, wrapped so
  it can never throw or return a promise a caller needs to await.
  Deliberately not `navigator.sendBeacon`, the usual textbook choice for
  this: sendBeacon always sends with credentials included and its return
  value only means "queued," not "accepted" — confirmed against a real
  browser, a credentialed beacon request is silently rejected by this
  API's wildcard-origin CORS config *after* `sendBeacon()` already
  returned `true`, so every event would vanish with no fallback ever
  running; `keepalive: true` fetch gives the identical survives-
  navigation guarantee without that failure mode. It doesn't reuse `api.ts` or
  `storefront-api.ts`: neither fits — `api.ts` is request/response
  shaped for TanStack Query, and `storefront-api.ts` is server-only (see
  the Phase 15 bullet above); a tracking call is neither, so it gets its
  own minimal client rather than being forced into either existing one.
- Server state via TanStack Query (all API data); Zustand reserved for
  genuine client-only UI state (sidebar collapsed, the storefront cart —
  see `DATABASE_DESIGN.md` section 1n for why the cart itself has no
  server-side table).
- Actual layout is flat, not the originally planned
  `frontend/src/features/*` per-domain folders: route segments under
  `frontend/src/app/` (`(admin)/`, `(storefront)/`, ...), with
  `components/`, `hooks/`, `types/`, `lib/`, and `stores/` each holding
  every domain's files side by side rather than grouped per feature — see
  `COMPONENT_INVENTORY.md` / `PAGE_INVENTORY.md` for what exists today.

## 10. What This Session Implements vs. Defers

This document describes the **target architecture for the whole
platform**. Sessions so far implement the foundation layer
(organizations/stores/users/roles/permissions/warehouses/settings/BD
localization on the backend, design system + auth + dashboard shell on
the frontend), Phase 5 Wave 1 catalog (categories, brands, simple
products with pricing/SEO/images), Phase 6 Wave 1 inventory (stock
levels per warehouse, the movements ledger, manual adjustments, and
warehouse-to-warehouse transfers), Phase 7 Wave 1 purchasing
(suppliers, purchase orders with a draft/ordered/received state
machine, and receipts that drive real stock movements), and Phase 8
Wave 1 orders (customers with saved addresses, and orders with a
pending/processing/shipped/delivered/cancelled state machine that
reserve stock on creation and convert the reservation into a real stock
movement on shipment — the first real consumer of Inventory Wave 2's
reservation gap), and Phase 9 Wave 1 delivery (couriers, and shipments
with their own pending_pickup/picked_up/in_transit/delivered/
failed_delivery/returned_to_seller state machine layered additively on
top of `Order.ship()`/`deliver()`, plus COD settlement batches that
reconcile a courier's remittance against delivered COD shipments — the
first real consumer of the COD half of Orders Wave 2's payments gap),
and Phase 10 Wave 1 returns (return requests against a `delivered` order
with a requested/approved/rejected/received/refunded state machine —
the first real producer, alongside a fix to Phase 9's
`returned_to_seller` shipment action, of Inventory Wave 2's `return`
stock-movement gap; a refund reconciles `orders.payment_status` once
every order item's ordered quantity is covered by that order's refunded
returns combined), and Phase 11 Wave 1 admin dashboard (two aggregate
endpoints — a 14-day sales trend and an order-status breakdown, both
pure read-side `GROUP BY` queries with no new tables, see
`DATABASE_DESIGN.md` section 1h — wired into real Recharts visuals, plus
every dashboard stat card now gated behind the permission that backs its
number instead of showing a misleading 0), and Phase 5 Wave 2a variable
products (`product_attributes`/`product_attribute_values`, own CRUD +
permissions, same shape as categories/brands; `product_variants`/
`product_variant_attribute_values`, with a "generate the cartesian
product of selected attribute values" action that skips combinations
that already exist as a variant — see `DATABASE_DESIGN.md` section 1i.
Shipped catalog-only at the time: no order line item, stock level, or
stock movement was variant-aware yet, so a variant couldn't actually be
sold, stocked, or purchased against individually — that was deliberately
left as its own Catalog-adjacent pass rather than folded into this one,
per rule 176's incremental-phases mandate, and has since been built (the
variant-aware Orders/Inventory/Purchasing retrofit, described next).
Also built since: **the variant-aware Orders/Inventory/Purchasing
retrofit** — a nullable `product_variant_id` on `order_items`/
`purchase_order_items`/`stock_transfer_items`/`stock_levels`/
`stock_movements` (see `DATABASE_DESIGN.md` sections 1c/1i), threaded
through every controller that mutates one of those tables, plus a
`stock_summary` on each variant (total + per-warehouse) surfaced on the
product's own Variants tab — deliberately not on the global Stock Levels
list, which stays product-centric (one row per product, summing a
variable product's stock across all its variants) rather than gaining
per-variant rows. This is the real unblock Catalog Wave 2a's variants
needed before storefront product pages or Returns' exchange feature can
use them, neither of which is built yet (their own phases haven't
started). Also built since: **Catalog CSV bulk import/export** (Phase 5
Wave 2b) — no new tables, `GET /products/export`/`POST /products/import`
read and write the existing `products` columns directly through a shared
`App\Support\ProductCsv` header list (see `DATABASE_DESIGN.md` section
1j), scoped to simple products only (an update never touches an existing
product's `type` or variants). Also built since: **Phase 18 Wave 1
Reporting** — three read-only, permission-gated (`reports.view`)
endpoints on `ReportController`, no new tables, pure aggregation over
`orders`/`order_items`/`products`/`stock_levels` exactly like the
Phase 11 dashboard endpoints (see `DATABASE_DESIGN.md` section 1k): a
sales report (totals, day/week/month-folded by-period breakdown, and a
by-payment-method breakdown, filterable by date range and warehouse), a
product performance report (units sold + revenue ranked by revenue,
variant sales rolled up to the parent product), and a low-stock report
(cross-warehouse quantity/reserved/available vs. threshold, with product
names — the low-stock gap the Phase 11 note below used to list). All
three ship a CSV export and a new admin "Reports" section
(Sales/Product Performance/Low Stock tabs). Also built since: **Phase 18
Wave 2a** — a `by_courier` breakdown on the sales report, inner-joining
`orders` to `shipments`/`couriers` (same shape as the existing
by-payment-method breakdown) so only orders that actually reached a
courier are counted, shown as a second table beside it on the Sales
report page. Also built since: **Phase 18 Wave 2b** — a period-over-period
`comparison` on the sales report, computed over the immediately
preceding period of equal length and wired into `StatCard`'s existing
(previously unused) `trend` prop on the Revenue/Orders/Average Order
Value cards. Also built since: **Phase 18 Wave 2c** — a PDF export twin
alongside each report's existing CSV export, via the new
`barryvdh/laravel-dompdf` dependency rendering Blade views that call the
same private query helpers the JSON/CSV endpoints already use, so the
PDF can't drift from what's on screen; richer than the CSV on purpose
(KPI totals, trend, payment-method/courier breakdowns included) since a
PDF is a presentable snapshot of the page, not a spreadsheet export.
This closes out Reporting Wave 2. Also built since: **Phase 5 Wave 2c** —
bundles/combos, the last deferred Catalog Wave 2 item, closing that wave
out entirely. A bundle is a `products` row with `type='bundle'`, not a
separate table (mirrors the `product_variants` precedent above); a new
`bundle_items` table defines its components, and a new
`order_item_components` table snapshots each order line's resolved
components at order-creation time so a later edit to a bundle's
composition can't retroactively change what an already-placed order
reserves/ships/returns (see `DATABASE_DESIGN.md` section 1l). A bundle
never holds real stock of its own — `App\Support\BundleExpander` derives
its sellable quantity from its components' stock instead, and the same
class expands a bundle line item into its component rows for every
stock operation. Deliberately excluded: nested bundles, Purchasing/
adjustments/transfers (a bundle is never bought or moved directly, only
its components — `App\Rules\ProductIsNotBundle`), and the Low Stock
report/Stock Levels list. Also built since: **Phase 7 Wave 2a** —
purchase returns, mirroring Phase 10's customer-facing Returns almost
exactly but with the goods flow reversed: `requested` → `approved` →
`shipped_back` (the stock-decrementing step, a new `purchase_return`
movement type mirroring `purchase_receipt`) → `credited` (a supplier
credit note, not a cash refund — nothing exists yet to apply it against),
or `rejected`. New `purchase_returns`/`purchase_return_items`/
`purchase_return_status_history` tables (see `DATABASE_DESIGN.md` section
1m); eligibility is capped by what's actually been received
(`quantity_received`), not what was ordered. Also built since:
**Phase 16 Wave 1** — the app's first public, unauthenticated API surface
(`api/v1/storefront/*`, no `auth:sanctum`) and the storefront UI that
consumes it, at `frontend/src/app/(storefront)/` (see
`PAGE_INVENTORY.md`). No new tables beyond an `orders.source`
(`'admin'`/`'storefront'`) column — every browsing endpoint is a new
`App\Http\Resources\Storefront\*`-shaped read over Catalog's existing
tables, deliberately never reusing the admin resources (they expose
`cost_price` and other operator-internal fields). The one write path,
guest checkout, is where a public endpoint needed a real trust-model
change the admin side never needed: it has no `unit_price` field at all,
always computing the charged price server-side, and it matches a guest
to an existing `Customer` by phone rather than minting a new identity
concept. `OrderController`'s item-sync/stock-reservation logic moved into
a shared `App\Support\OrderPlacement` so admin and storefront checkout —
now two genuinely independent callers — can't drift apart (see
`DATABASE_DESIGN.md` section 1n for the full contract). Deliberately
scoped down: guest-only (no accounts — Phase 17), one hardcoded store
(no domain-based multi-tenant routing yet — Phase 4's `stores.domain`/
`slug` exist but nothing seeds a second store to route between), COD
only (no payment gateway — Phase 19), a client-side-only
(`zustand`/`localStorage`) cart with no server persistence, and
no real per-page SEO metadata (see section 9's note on why that's a
separate, deliberate pass, not a quick add-on here). The homepage itself
is no longer hardcoded — see Phase 13 below.
Also built since: **Phase 17 Wave 1** — real customer accounts (register/
login/logout/me under a new `api/v1/account/*` prefix) that close
Storefront Wave 1's guest-only gap, via the two-identity Sanctum design
section 3 above describes, plus an `/account/*` shell (order history with
a status-history timeline, saved addresses, profile) at
`frontend/src/app/account/` (see `PAGE_INVENTORY.md`). The one
schema change is `customers.password` (nullable — see `DATABASE_DESIGN.md`
section 1o); registering claims an existing guest-checkout `Customer` row
by `(store_id, phone)` instead of creating a duplicate, which is also why
`Storefront\CheckoutRequest.customer_phone` gained the same `BdPhone`
normalization staff `User.phone` already had — checkout-time and
registration-time phone formatting have to agree for the claim lookup to
match. A second, fully separate frontend token/API-client pair
(`nby_customer_auth_token`/`accountApi`) keeps a customer session on a
browser from clobbering an admin session on the same browser, or vice
versa. Deliberately scoped down, each for lack of a real consumer/design
pass yet: no wishlist, no customer-initiated return requests (still the
Phase 10 staff-only flow), and checkout's own address collection is
unchanged — a saved-address picker at checkout is a real, separate
feature tracked as its own Wave 2 item, not bundled in just because it's
related.
Also built since: **Phase 19 Wave 1** — the Adapter Pattern (section 6
above) shipped for real for the first time, on notifications rather than
payment/courier: a new `notifications` table (Laravel's own, via
`php artisan notifications:table`) backs both a customer-facing
mail+SMS channel and a staff-facing database channel that finally gives
the admin topbar's bell (`COMPONENT_INVENTORY.md`) real data instead of
its placeholder empty state. Four notification classes, each fired
synchronously (no queue worker runs anywhere in this app yet, so
`ShouldQueue` would just be unused ceremony) from the exact controller
methods that already change an `Order`'s or `OrderReturn`'s status:
`OrderPlacedNotification` (customer, on `OrderController::store` and
`Storefront\CheckoutController::store`), `NewOrderPlacedNotification`
(staff, database-only, storefront orders only — an admin-created order
was just entered by a staff member themselves, so notifying them about it
would be pure noise, unlike a storefront order they wouldn't otherwise
know about), `OrderStatusChangedNotification` (customer, on
`process`/`ship`/`deliver`/`cancel`), and `ReturnStatusChangedNotification`
(customer, on approve/reject/receive/refund). Mail uses this environment's
already-configured `log` mailer (real Laravel mail pipeline, mock
transport); a customer with no email on file simply gets `sms` only,
decided per-notification in `via()`. Deliberately deferred, each for lack
of real provider credentials in this environment (`PROJECT_AUDIT.md`):
a real BD SMS provider, the courier/payment gateway adapters section 6
still documents as target-only, a WhatsApp channel, and queued
(non-synchronous) delivery once a real queue worker actually runs.
Also built since: **Phase 12 Wave 1** — simple CMS content pages, the
first (small) piece of the CMS/builder box the paragraph below used to
list as entirely unbuilt. A `Page` model/`pages` table (see
`DATABASE_DESIGN.md` section 1q) backs admin CRUD at a new
`frontend/src/app/(admin)/content/` route group and two public storefront
endpoints. Two design choices worth flagging, both later confirmed
correct once Phase 13 and Phase 14 actually shipped: (1) `PagePolicy`
maps all five abilities to one `pages.manage` permission — not this
session's own invention, but a
pre-existing seeder wire-up (`RoleAndPermissionSeeder`, since Phase 3)
finally activated, the same "some resources get one umbrella permission,
not Category's 4-way split" pattern `settings.manage` already established;
(2) page content is plain text, not the rich-text/Markdown a real CMS
usually implies, because `COMPONENT_INVENTORY.md` had already earmarked
TipTap for Phase 14's blog post editor specifically — adding a second,
competing rich-text dependency ahead of that phase would pre-empt a
decision that phase hasn't made yet. Deliberately deferred: page version
history, `navigation_menus`/`navigation_items` (today's page links are a
flat, unordered footer column), hierarchical/nested pages, and scheduled
publish dates — none of which a first "About Us" page needs, per
`DEVELOPMENT_ROADMAP.md`'s Phase 12 Wave 1 scope note.
Also built since: **Phase 13** — a full drag-and-drop Homepage Builder
(not a lean wave — the user explicitly asked for the complete spec),
covering all ~30 block types the spec's block-registry pattern
(section 60) calls for. Two files are the registry's entire integration
surface — `content-panel-registry.tsx` (block type → Content-tab editor)
and `homepage-block-renderer.tsx` (block type → storefront renderer) —
deliberately kept as the only two files every one of the ~30 type's
renderer/panel pair plugs into, so ~25 of them could be built by parallel
background agents against four hand-built exemplars (hero, category_grid,
featured_products, rich_text) without merge conflicts, then wired in one
pass. `App\Support\ResolvesHomepageBlocks` is the matching backend
half: one trait, shared verbatim by the public `Storefront\
HomepageController` (active blocks only) and a new admin `preview`
endpoint (every block, draft included), so the builder canvas's "live
preview" claim is literally true — same renderer components, same
resolved data shape — rather than an approximation. Two real bugs only
surfaced by actually clicking through the builder in a browser against a
running backend, not by tsc/eslint/PHPUnit: (1) `flash_sale`'s
`sale_price` was passed through as a raw minor-unit integer on read and
never converted at all on write, instead of through the `Money` value
object every other price field in the app already uses at the API
boundary (`ProductController::preparePayload()`); (2) eleven block
types' own `defaultSettings()` didn't satisfy their own
`settingsRules()` (Laravel's `required` rejects both `null` and empty
string/array), so clicking them in the block picker 422'd and silently
added nothing — fixed by making decorative/optional fields nullable
(matching how their renderers already treat "not configured yet") and
giving genuinely-core-text fields a real starter value instead, plus a
regression test that creates all 30 types from their own defaults via
the real endpoint. Builder access is a granular three-permission split
(`builder.view`/`builder.edit`/`builder.publish`) rather than Phase 12's
single `pages.manage` — discovered pre-wired and dormant in the RBAC
seeder (Marketing Manager: view+edit only; Content Manager: all three),
honored rather than replaced. Local undo/redo (an in-session draft
history, debounce-autosaved) and server-side revision history (a
snapshot taken before every settings/publish/unpublish/restore mutation,
restorable from a new UI sheet) are deliberately two separate mechanisms
rather than one trying to do both jobs — see
`DEVELOPMENT_ROADMAP.md`'s Phase 13 scope note. Deliberately minimal
placeholder models back three block types pending their real phase:
`Testimonial` (backs both Testimonials and Reviews — not Catalog Wave
2's still-unbuilt verified-purchase review system), `BlogPost`
(title/slug/excerpt/image/published_at only — not Phase 14's real
blog CMS), and `NewsletterSubscriber` (email capture only).
Also built since: **Phase 14** — the real Blog CMS, absorbing Phase 13's
placeholder `BlogPost` model exactly as flagged above (an ALTER
migration, not drop-and-recreate, so the 3 existing demo rows survive).
`blog.manage` is a single umbrella permission, the same pattern
`pages.manage`/`settings.manage` already established — another
pre-existing seeder wire-up (Marketing Manager/SEO Manager/Content
Manager, since Phase 3) finally activated, not invented for this phase.
A new `BlogPost::scopePublished()` local scope — this app's first use of
an Eloquent local scope — is the single place `status = 'published' AND
published_at <= now()` lives, shared by the storefront's index/detail/
category/tag reads and the homepage builder's own Blog Posts block
resolver, so real scheduled publishing needs zero extra cron
infrastructure (unlike Phase 13's dedicated
`homepage-blocks:publish-scheduled` Artisan command) and the visibility
rule can't drift across five call sites. Version history reuses Phase
13's `homepage_block_revisions` pattern exactly (a snapshot taken before
every save and every restore) at a new `blog_post_versions` table — a
Save-button form doesn't need Phase 13's separate local-undo/redo layer
alongside it, since there's no live-autosave canvas to undo within.
`RichTextEditor` (`COMPONENT_INVENTORY.md`) is reused as-is for the post
body, exactly as earmarked when Phase 12 chose plain text instead. See
`DEVELOPMENT_ROADMAP.md`'s Phase 14 scope note for the full design —
including why this phase was designed from `DATABASE_DESIGN.md`'s own
forward-looking notes rather than a master-spec section, since a
dedicated research pass established none was ever committed to this
repository for it.
Also built since: **Phase 15** — full SEO tooling. A single polymorphic
`seo_metadata` table (section 4's `entity_type`/`entity_id` convention,
matching `activity_logs`) supersedes the three ad-hoc SEO field sets
Phases 5/12/14 each grew independently (`products.seo_title`/
`seo_description`/`focus_keyword`, `pages.meta_title`/`meta_description`,
`blog_posts.meta_title`/`meta_description`) rather than running alongside
them — one migration backfills every existing value into `seo_metadata`
rows, then drops all five legacy columns. Every SEO-bearing model
(Product, Category, Brand, Page, BlogPost, BlogCategory, BlogTag, Store)
gets a `seoMetadata()` morphOne relation and accepts/returns its SEO data
as a nested `seo` object on its own existing endpoint via a new shared
`App\Http\Controllers\Concerns\SyncsSeoMetadata` trait — not a dedicated
seo-metadata REST resource, mirroring how `BlogPost` already syncs
`tag_ids` as part of one save. `Redirect` and `SeoTemplate` are genuinely
independent resources with their own standalone CRUD, reusing the
`seo.manage` permission the RBAC seeder had pre-wired to SEO Manager/
Content Manager since Phase 3 (the same dormant-permission pattern
`pages.manage` and `blog.manage` each followed). Section 9's Client-
Component gap this section used to describe is now closed for the
storefront's leaf pages specifically — see the updated section 9 note.
Catalog Wave 2's remaining
items (reviews — no longer blocked on anything, just not yet picked, now
that Phase 17 gives the real customer identity it was waiting on — see
`DATABASE_DESIGN.md` section 2 — and a reusable media library), Purchasing Wave 2's remaining
items (supplier ledger, PO approval workflow, reorder suggestions —
no longer blocked on reporting infra, just not yet picked), Orders Wave 2 (a
non-COD gateway-payments ledger, coupons), Delivery Wave 2 (delivery
zones/rates, multi-shipment orders — the return-driven stock reversal gap
this used to list is closed, see above), Returns Wave 2 (exchanges, store
credit, cross-return partial-refund reconciliation), Dashboard Wave 2 (a
custom date-range picker and per-warehouse/per-courier breakdowns *on the
dashboard widget itself* — Reports above now covers date-range/
per-warehouse/payment-method/CSV/low-stock-with-names as its own admin
section, so only per-courier breakdowns and folding any of that back into
the dashboard remain open there), and Reporting's remaining
materialized/scheduled aggregate table (see `DATABASE_DESIGN.md`
section 2 — Reporting Wave 2 itself is fully shipped) are designed here
but built in later phases per `DEVELOPMENT_ROADMAP.md`.
