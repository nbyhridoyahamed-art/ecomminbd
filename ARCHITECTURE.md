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
a change to `OrderService` or `ShipmentService`. These interfaces and
their `Mock*` implementations ship in the Delivery/Payment phases
(Phase 8–9), not this session — this section documents the contract so
later phases implement against an agreed shape.

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
- Server Components by default; Client Components only for interactive
  forms, charts, drag-and-drop, rich editors, and real-time widgets
  (spec section 3).
- Server state via TanStack Query (all API data); Zustand reserved for
  genuine client-only UI state (sidebar collapsed, builder canvas state).
- `frontend/src/features/*` mirrors backend domain boundaries
  (`auth`, `dashboard`, and later `products`, `orders`, `inventory`, ...
  as each phase is built) — see `COMPONENT_INVENTORY.md` /
  `PAGE_INVENTORY.md` for what exists today versus what's planned.

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
returns combined).
CMS/builder, blog, SEO, storefront, customer account, reporting, the
adapter implementations described in section 6, Catalog Wave 2
(variants/attributes, bundles, bulk import/export, a reusable media
library), Inventory Wave 2 (variant-level stock — the order-*return*
movement gap this used to list is closed, see above), Purchasing Wave 2
(purchase returns, supplier ledger, PO approval workflow), Orders Wave 2
(a non-COD gateway-payments ledger, coupons), Delivery Wave 2 (delivery
zones/rates, multi-shipment orders — the return-driven stock reversal
gap this used to list is closed, see above), and Returns Wave 2
(exchanges, store credit, cross-return partial-refund reconciliation —
see `DATABASE_DESIGN.md` section 2) are designed here but built in later
phases per `DEVELOPMENT_ROADMAP.md`.
