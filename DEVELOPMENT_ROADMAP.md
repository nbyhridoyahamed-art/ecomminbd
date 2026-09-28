# DEVELOPMENT ROADMAP

26 phases, per master spec section 176. Status reflects reality, not
aspiration — a phase is only ✅ once its code, tests, and docs are all
in place and the app still builds/runs.

| # | Phase | Status | This session? |
|---|---|---|---|
| 0 | Project Discovery | ✅ Done | Yes — `PROJECT_AUDIT.md` |
| 1 | Architecture | ✅ Done | Yes — this doc suite + folder structure |
| 2 | Design System | ✅ Done | Yes — tokens, theme, first primitives |
| 3 | Authentication | ✅ Done | Yes — Sanctum, login/logout/me/reset, roles/permissions seeded |
| 4 | Store Foundation | ✅ Done (localization data-management UI deferred — see note) | Yes — orgs/stores/users/roles/permissions/settings/currency + full admin UI (General/Users/Roles) |
| 5 | Catalog | ✅ Wave 1 + Wave 2a done (bundles/reviews/bulk import-export/media library still deferred — see note) | Yes — categories (hierarchy), brands, simple + variable products w/ pricing/SEO/images, attributes + a variant generator, full admin UI |
| 6 | Inventory | ✅ Wave 1 done, now variant-aware (transfer approval workflow deferred — see note) | Yes — stock levels per warehouse, movements ledger, adjustments, transfers; plus the Warehouses admin UI (a Phase 4 gap this closed) |
| 7 | Purchasing | ✅ Wave 1 done, now variant-aware (purchase returns/supplier ledger/PO approval workflow deferred — see note) | Yes — suppliers, purchase orders (draft→ordered→received state machine), receipts that drive real stock movements |
| 8 | Orders | ✅ Wave 1 done, now variant-aware (payments ledger/coupons/returns/order-edit UI deferred — see note) | Yes — customers + saved addresses, orders (pending→processing→shipped→delivered/cancelled state machine) that reserve and then fulfil real stock |
| 9 | Delivery | ✅ Wave 1 done (delivery zones/rates, multi-shipment orders deferred — see note) | Yes — couriers, shipments (pending pickup→picked up→in transit→delivered/failed/returned state machine, additive on top of Order.ship()/deliver()), COD settlements |
| 10 | Returns | ✅ Wave 1 done (exchanges/store-credit, cross-return refund reconciliation deferred — see note) | Yes — return requests (requested→approved→rejected\|received→refunded state machine) against a delivered order, real stock-reversal movements on receive, and the Phase 9 gap this closes (returned-to-seller shipments now restock too) |
| 11 | Admin Dashboard (full KPIs/charts) | ✅ Wave 1 done (custom date ranges, per-warehouse/per-courier breakdowns, full reporting suite deferred — see note) | Yes — sales trend (orders + revenue, last 14 days) and order-status-breakdown charts backed by real aggregate endpoints, a recent-orders widget, and every stat card now permission-gated |
| 12 | CMS | ⏳ Not started | No |
| 13 | Homepage Builder | ⏳ Not started | No |
| 14 | Blog | ⏳ Not started | No |
| 15 | SEO | ⏳ Not started | No |
| 16 | Storefront | ⏳ Not started | No |
| 17 | Customer Dashboard | ⏳ Not started | No |
| 18 | Reporting | ⏳ Not started | No |
| 19 | Integrations (payment/courier/email/SMS/WhatsApp adapters) | ⏳ Not started | No |
| 20 | Analytics | ⏳ Not started | No |
| 21 | Security Hardening | ⏳ Ongoing baseline only | Partial — Sanctum, policies, rate limiting, validation from day one |
| 22 | Performance | ⏳ Not started | No |
| 23 | Accessibility | 🟡 Baseline in design system | Partial |
| 24 | Responsive QA | 🟡 Baseline (login/dashboard tested at all breakpoints) | Partial |
| 25 | Final Testing | 🟡 Backend feature tests + frontend build/lint/typecheck for what exists | Partial |
| 26 | Production | ⏳ Not started | No |

## Why the Scope Was Cut Here

The master specification describes a multi-quarter, multi-team SaaS
product (189 numbered sections covering ERP, CMS, page builder, blog,
SEO suite, and a full storefront). Building all 26 phases in a single
pass would violate the spec's own explicit rules:

- Rule 176/188: "Do NOT build the entire application in one pass...
  build incrementally... keep the application runnable after every
  phase."
- Rule 178: never generate fake functionality, dead buttons, or
  duplicated business logic — which is unavoidable if screens are
  scaffolded ahead of the backend that should drive them.
- Rule 189: the very first action mandated is producing these audit/
  architecture docs and starting **Phase 1 — Foundation**, not
  everything at once.

So this project delivers Phases 0–7 (Wave 1) completely (foundation,
design system, auth, RBAC, multi-store data model, BD localization,
admin shell, full Settings UI, a working catalog, warehouse-level
inventory tracking, and supplier purchase orders) as real, tested,
runnable code — a solid base every later phase builds directly on top
of, with zero placeholder/fake screens.

**Phase 4 scope note:** the one deliberately deferred piece is a UI for
*editing* the BD divisions/districts/upazilas reference data — the
schema and seed data exist (`bd_divisions`/`bd_districts`/`bd_upazilas`),
and a read API exists (`/api/v1/locations/*`), but there's no admin
screen to add/edit that data yet. It doesn't block anything: nothing
downstream needs to edit it before the storefront's address forms need
to consume it. Building that management screen now, with nothing yet
driven by it, would be exactly the "fake functionality ahead of its
consumer" spec rule 178 forbids. (The other Phase 4 gap noted in an
earlier version of this doc — a Warehouses admin UI — was closed in
Phase 6, once Inventory made it a hard dependency rather than a nice-
to-have.)

**Phase 5 scope note:** Wave 1 ships everything a simple product needs
end-to-end (categories with unlimited-depth hierarchy, brands, simple
products with real pricing/SEO fields, multi-image upload with primary
selection) with full CRUD, RBAC, and tests. Deliberately deferred to a
Wave 2 (see `DATABASE_DESIGN.md` section 2 and `PAGE_INVENTORY.md`):
variable products/attributes/variant generation, bundles/combos,
customer reviews (needs Phase 8's orders for "verified purchase"),
CSV bulk import/export, and a reusable cross-entity media library
(today images upload directly against their owning record — see
`DATABASE_DESIGN.md` section 1b). Each is a substantial subsystem in
its own right and every one currently has no real consumer to justify
shipping it early — spec rule 178.

**Phase 5 Wave 2a scope note:** narrowed Wave 2 down to the one piece
several later phases actually block on — variable products, not all
five deferred items at once (spec rule 176: incremental, not a grab-bag
pass). Ships `product_attributes`/`product_attribute_values` (store-
scoped reference data, own CRUD + permissions, reused across products —
same shape as categories/brands) and `product_variants`/
`product_variant_attribute_values` (a variant belongs to one product,
carries its own SKU and nullable price/sale-price/cost-price overrides
that fall back to the parent product's own price when null). A
"Generate variants" action computes the cartesian product of the
selected attribute values and skips any combination that already exists
as a variant, so re-running it after adding one new value only creates
the new combinations. This shipped catalog data only at the time — no
order line item, stock level, or stock movement was variant-aware yet;
a variable product's variants existed for catalog management (distinct
SKUs/prices/barcodes) the same way Wave 1's simple products existed
before Phase 8's orders ever consumed them. Wiring Orders/Inventory/
Purchasing to be variant-aware was flagged as real, substantial,
cross-cutting work of its own and deliberately left for its own pass
rather than attempted alongside this one — touching every phase built so
far in one pass would have been exactly the kind of un-incremental
change rule 176 warns against. That pass has since been done — see the
**variant-aware Orders/Inventory/Purchasing retrofit** scope note below.
Bundles/combos (needs
Orders-integrated component stock decrement, not just a new `type`
value), customer reviews, CSV bulk import/export, and a reusable media
library remain deferred for the reasons Wave 1's note above already
gives — none has a real consumer yet.

**Phase 6 scope note:** Wave 1 ships on-hand stock tracking per
warehouse (`stock_levels`), a full audit ledger of every change
(`stock_movements`), manual adjustments (increase/decrease with a
reason), and multi-item warehouse-to-warehouse transfers — executed
atomically, with `lockForUpdate()` preventing a decrease/transfer from
ever taking quantity negative. It also builds the Warehouses admin UI
(list/create/edit), closing a Phase 4 gap: the backend model existed
since Phase 4, but there was no screen to add a second warehouse and no
demo data seeded either, which would have made Inventory unusable out
of the box. Deliberately deferred to a Wave 2 (see `DATABASE_DESIGN.md`
section 2): a pending/in-transit/received transfer approval workflow,
and a `stock_adjustments` header table for grouping a stocktake's many
adjustments. Neither has a real consumer yet — spec rule 178.
(Purchase-receipt-driven movements, order reservation/fulfillment/
cancellation movements, order-*returns* movements, and variant-level
stock — the Wave 2 items this note used to list — are no longer
deferred: Phase 7, Phase 8, Phase 10, and the variant-aware retrofit
built them respectively; see the retrofit's own scope note below for
that last one.)

**Phase 7 scope note:** Wave 1 ships suppliers (full CRUD) and purchase
orders with a real state machine: `draft` (items freely editable, a PUT
replaces them wholesale) → `ordered` (explicit "Place order" action,
items lock) → `partially_received`/`received` (set automatically as
receipts come in) or `cancelled` (only reachable from `draft`/`ordered`
— cancelling after any stock has been received is a Wave 2 problem, see
below). Recording a receipt is the first real producer of the
`purchase_receipt` stock-movement type Phase 6 reserved: it increases
`stock_levels` and writes to the ledger inside the same locked
transaction as the receipt itself, rejecting any attempt to over-receive
beyond what remains on an order line. Deliberately deferred to a Wave 2
(see `DATABASE_DESIGN.md` section 2): purchase returns (needs a real
trigger from actual usage before its workflow can be designed with
confidence), supplier payment terms/ledger and multi-currency POs
(accounting-heavy, no consumer yet), a PO approval/sign-off workflow (no
multi-user approval concept exists yet), and low-stock-driven reorder
suggestions (needs Phase 18/20 reporting infra).

**Phase 8 scope note:** Wave 1 ships customers (full CRUD) with saved
addresses (managed inline on the customer edit page, no separate
address pages), and orders with a real state machine: `pending` (stock
reserved atomically at creation via `stock_levels.quantity_reserved`,
items freely editable — a PUT releases the old reservation and
re-reserves the new items) → `processing` (explicit action) →
`shipped` (explicit action — converts the reservation into a real
`sale` stock movement and decrements on-hand quantity) → `delivered`
(explicit action), or `cancelled` (only reachable from
`pending`/`processing` — releases the reservation without touching
on-hand stock; cancelling after shipment is a Wave 2 problem, see
below). This is also the first real consumer of stock reservations,
closing the Inventory Wave 2 gap Phase 6 left open: low-stock detection
now compares against *available* (on-hand minus reserved) quantity, and
`StockAdjustmentController`/`StockTransferController` both reject a
change that would take on-hand stock below what's already reserved.
Deliberately deferred to a Wave 2 (see `DATABASE_DESIGN.md` section 2):
a real gateway-payments ledger for non-COD methods (Phase 9 closed the
COD half of this gap — see its scope note below), coupons/discount codes
(`discount_amount` is a plain manual entry in Wave 1), order
returns/exchanges (needs Phase 10), and a dedicated order-edit-while-
pending UI (the endpoint exists and is tested, but no page consumes it
yet — same as purchase-order editing).

**Phase 9 scope note:** Wave 1 ships couriers (full CRUD) and shipments
with a real state machine: `pending_pickup` (created by assigning a
courier + tracking number to an already-`shipped` order — additive on
top of `Order.ship()`/`deliver()`, not a replacement: an order can still
be marked delivered directly with no shipment at all, e.g. store pickup
or self-delivery) → `picked_up` → `in_transit` → `delivered` (captures
`cod_amount_collected`, defaulting to the order total for a `cod` order,
and — if the order isn't already delivered — transitions it too, setting
`payment_status` to `paid` for COD; this closes the Orders Wave 2 gap
Phase 8 left open for COD reconciliation specifically). `failed_delivery`
is reachable any time before delivery, and from there `returned_to_seller`
— neither touches order status or stock, since reversing a shipment's
stock effect is a Wave 2 returns problem. Once an order has a shipment,
`Order.deliver()` refuses to mark it delivered directly, so the two
can never disagree. COD settlements (`cod_settlements` +
`cod_settlement_shipments`) record a courier's remittance batch against
a set of delivered, unsettled COD shipments — an immutable financial
record, same as `stock_movements`/`order_status_history`: no
update/destroy endpoint. Also fixed along the way: `OrderResource`'s
shipping division/district/upazila names were silently always `null`
(read a non-existent `name` column instead of `name_en`), a latent
Phase 8 bug caught while wiring the shipment relation onto the same
resource. Deliberately deferred to a Wave 2 (see `DATABASE_DESIGN.md`
section 2): delivery zones/rates (no automatic shipping-rate-calculation
consumer yet — `orders.shipping_amount` is still a plain manual entry)
and multi-shipment orders (`shipments.order_id` is unique — re-dispatching
after a failed delivery has nowhere to go yet). The remaining Wave 2 item
this note used to list — automatic stock-reversal movements on a
`returned_to_seller` shipment — is no longer deferred: Phase 10 closed it
(see its scope note below).

**Phase 10 scope note:** Wave 1 ships return requests against a
`delivered` order with a real state machine: `requested` (per-item
quantity, guarded against exceeding what remains eligible — a competing
open return reserves its quantity, but a `rejected` one frees it back
up) → `approved`/`rejected` → `received` (the first real producer,
alongside the Phase 9 fix below, of the `return` stock-movement type
Phase 6 reserved since; a per-item `restock` flag can be overridden here,
since a returned item's condition is only knowable once it's physically
back — not at request time) → `refunded` (an amount defaulting to the
sum of the returned items' line totals, staff-overridable, same pattern
as `ShipmentController::delivered()`'s COD amount). A refund only flips
`orders.payment_status` to `refunded` once every order item's full
ordered quantity is covered by that order's `refunded` returns combined
— a deliberately conservative reconciliation that avoids guessing at
partial-refund semantics. Also closed along the way: the exact gap
Phase 9's own scope note flagged — `ShipmentController::returned()` now
restocks on-hand quantity and writes a real `return` movement when a
failed delivery is marked back to the seller, since `Order.ship()` had
already decremented it before any shipment existed. Deliberately
deferred to a Wave 2 (see `DATABASE_DESIGN.md` section 2): exchanges
(swap for a different product/variant — Phase 5 Wave 2a's variant
catalog data exists now, but no order line item is variant-aware yet,
so there's nothing to swap *to* within an order), store credit as a
refund method (no wallet/ledger concept exists), and reconciling
`payment_status` across *partial* refunds spread over multiple separate
return records (today only a full-coverage refund reconciles it — see
above).

**Phase 11 scope note:** Wave 1 ships two new store-scoped aggregate
endpoints (`DashboardController::salesTrend()`/`orderStatusBreakdown()`,
gated by a direct `orders.view` check, same pattern as
`StockLevelController::lowStockCount()` — a dashboard aggregate spans
multiple models, not one Eloquent policy) and wires them into real
Recharts visuals: a 14-day orders+revenue trend (revenue is the sum of
`order_items` line totals — quantity × `unit_price_amount` — deliberately
excluding shipping/discount, so it won't exactly match an individual
order's `total_amount`; that per-order figure belongs on the order
itself, this is a trend) and an order-status-breakdown bar chart, plus a
recent-orders widget (reuses the existing `GET /orders` list, sliced to
5 client-side — no new endpoint needed). Every existing stat card is now
gated behind the permission that backs its number, so a user without
`orders.view`/`shipments.view`/etc. no longer sees a misleadingly blank
0 for data they can't actually see (spec rule 178). Deliberately
deferred to a Wave 2 (see `DATABASE_DESIGN.md` section 2): a custom
date-range picker (Wave 1 is fixed at the trailing 14 days),
per-warehouse/per-courier breakdowns, revenue by payment method, a
low-stock-*products* widget with names (today's `/stock-levels` list
endpoint is single-warehouse only — a cross-warehouse product list
needs a new endpoint, and the existing store-wide `low-stock-count`
scalar already backs the "Low stock alerts" card honestly), CSV/PDF
export, period-over-period comparisons, and the full reporting suite —
all of that is Phase 18 Reporting's job, not a dashboard widget's.

**Variant-aware Orders/Inventory/Purchasing retrofit scope note:** closes
the gap Phase 5 Wave 2a's own scope note (above) flagged and this doc
used to point "Next Session Should Start With" at. Adds a nullable
`product_variant_id` alongside `product_id` on `order_items`,
`purchase_order_items`, `stock_transfer_items`, `stock_levels`, and
`stock_movements` (see `DATABASE_DESIGN.md` sections 1c/1i), and threads
it through every write path that touches one of those tables:
`OrderController` (`syncItems`/`reserveItems`/`releaseReservation`/
`ship`), `PurchaseOrderController::syncItems()`,
`PurchaseReceiptController::receiveStock()`,
`StockAdjustmentController::store()`, and
`StockTransferController::moveStock()` — plus two gaps beyond the
original plan, found while auditing every `StockLevel`/`StockMovement`
call site rather than just the ones initially listed:
`ReturnController`'s restock-on-receive and `ShipmentController`'s
restock-on-returned-to-seller were still product-only, so a returned
variant would have silently restocked the wrong (simple-product)
row. A shared `App\Rules\VariantBelongsToProduct` rule validates the
submitted variant actually belongs to the submitted product on every
line-item form that accepts one. `OrderResource`, `PurchaseOrderResource`,
`PurchaseReceiptResource`, `StockTransferResource`, `StockMovementResource`,
and `ReturnResource` all now expose the variant on each line item;
`ProductVariantResource` gained a `stock_summary` (total + per-warehouse
breakdown), and `ProductVariantController::destroy()` now refuses to
delete a variant with any `stock_levels` row (even a zeroed-out one),
pointing at deactivating it instead. Frontend: a shared `VariantPicker`
is reused in the Order/Purchase Order/Stock Transfer forms (with a
client-side guard requiring a variant selection for a variable product,
stricter than the backend's own nullable rule, so a real 422 round-trip
never happens for the common case), every page that lists those line
items now shows the variant, and the product's own Variants tab gained a
Stock column plus an "Adjust stock" entry point
(`VariantStockAdjustmentDialog`) — see the deliberate-scope-cut note in
`DATABASE_DESIGN.md` section 1c for why that's on the Variants tab and
not the global Stock Levels list. That list needed one real fix along
the way: it was still joining `stock_levels` to `products` 1:1, which a
variable product's now-multiple-rows-per-product would have turned into
duplicate rows per product; it now sums with `GROUP BY`/`SUM()` instead.
14 new backend tests (141 → 155), all green, plus the existing frontend
build/lint/typecheck.

## Next Session Should Start With

Phase 5 Wave 2b (bundles/combos, customer reviews, CSV bulk
import/export, a reusable media library) or Phase 18 Reporting are the
two reasonable pickups now that catalog variants are fully sellable,
stockable, and purchasable — neither blocks the other, pick whichever
the user prioritizes. Follow the phase order above; do not skip ahead to
CMS/SEO/Storefront (Phases 12–17) — nothing currently blocks them
specifically, but the master spec's own incremental-phases rule (176)
means they still wait their turn behind Phase 18 Reporting and any
remaining Wave 2 items on already-started phases.

## Execution Protocol for Every Future Phase (spec section 177)

1. Explain phase objective.
2. Inspect relevant existing files.
3. Review architecture (this doc suite).
4. Database changes (migrations).
5. Laravel backend (Actions/Services/Repositories/Models).
6. API (Controllers/Requests/Resources/routes).
7. Next.js frontend (pages/components wired to the real API).
8. UI states (loading/empty/error/success/disabled).
9. Seed/demo data.
10. Tests.
11. Run PHP tests, TypeScript check, ESLint, PHPStan (once installed), build.
12. Fix errors.
13. Responsive review.
14. Accessibility review.
15. Update this roadmap + relevant inventory docs.
16. Summarize what's actually done — never claim more.
