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
| 5 | Catalog | ✅ Wave 1 + Wave 2a + Wave 2b (CSV import/export) + Wave 2c (bundles/combos) done — Wave 2 fully closed (reviews/media library still deferred — see note) | Yes — categories (hierarchy), brands, simple + variable + bundle products w/ pricing/SEO/images, attributes + a variant generator, bundle components with derived availability, CSV bulk import/export, full admin UI |
| 6 | Inventory | ✅ Wave 1 done, now variant-aware (transfer approval workflow deferred — see note) | Yes — stock levels per warehouse, movements ledger, adjustments, transfers; plus the Warehouses admin UI (a Phase 4 gap this closed) |
| 7 | Purchasing | ✅ Wave 1 done, now variant-aware, plus Wave 2a (purchase returns) (supplier ledger/PO approval workflow/reorder suggestions still deferred — see note) | Yes — suppliers, purchase orders (draft→ordered→received state machine), receipts that drive real stock movements, purchase returns (requested→approved→shipped_back→credited) |
| 8 | Orders | ✅ Wave 1 done, now variant-aware (payments ledger/coupons/returns/order-edit UI deferred — see note) | Yes — customers + saved addresses, orders (pending→processing→shipped→delivered/cancelled state machine) that reserve and then fulfil real stock |
| 9 | Delivery | ✅ Wave 1 done (delivery zones/rates, multi-shipment orders deferred — see note) | Yes — couriers, shipments (pending pickup→picked up→in transit→delivered/failed/returned state machine, additive on top of Order.ship()/deliver()), COD settlements |
| 10 | Returns | ✅ Wave 1 done (exchanges/store-credit, cross-return refund reconciliation deferred — see note) | Yes — return requests (requested→approved→rejected\|received→refunded state machine) against a delivered order, real stock-reversal movements on receive, and the Phase 9 gap this closes (returned-to-seller shipments now restock too) |
| 11 | Admin Dashboard (full KPIs/charts) | ✅ Wave 1 done (custom date ranges, per-warehouse/per-courier breakdowns, full reporting suite deferred — see note) | Yes — sales trend (orders + revenue, last 14 days) and order-status-breakdown charts backed by real aggregate endpoints, a recent-orders widget, and every stat card now permission-gated |
| 12 | CMS | ⏳ Not started | No |
| 13 | Homepage Builder | ⏳ Not started | No |
| 14 | Blog | ⏳ Not started | No |
| 15 | SEO | ⏳ Not started | No |
| 16 | Storefront | ✅ Wave 1 done (customer accounts, multi-store domain routing, non-COD payment, homepage builder integration deferred — see note) | Yes — public unauthenticated catalog browsing (products/categories/brands) and guest COD checkout against the single active store |
| 17 | Customer Dashboard | ⏳ Not started | No |
| 18 | Reporting | ✅ Wave 1 + Wave 2 (per-courier breakdown, period-over-period comparison, PDF export) done (materialized/scheduled aggregate tables deferred — see note) | Yes — sales report (totals/by-period/by-payment-method/by-courier, day/week/month granularity, date-range + warehouse filters, vs.-previous-period trend on each KPI card), product performance (variant sales rolled up to parent product), and a cross-warehouse low-stock report, each with CSV and PDF export; activates the `reports.view` permission the RBAC seeder has carried since Phase 3 |
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
value), customer reviews, and a reusable media library remain deferred
for the reasons Wave 1's note above already gives — none has a real
consumer yet. CSV bulk import/export is no longer deferred — see the
**Phase 5 Wave 2b** scope note directly below.

**Phase 5 Wave 2b scope note:** picked CSV bulk import/export as the one
piece of the remaining Wave 2 list with a real, immediate, self-contained
consumer (a store operator bulk-loading or editing a catalog from a
spreadsheet) and no dependency on anything else — unlike the other three:
bundles need Orders-integrated stock decrement (substantial, cross-
cutting work similar in shape to the variant-aware retrofit), and
customer reviews specifically can't be *real* yet regardless of how much
backend work goes into them — nothing lets a customer actually write one
before a storefront and an account portal both exist (Phase 16/17;
Phase 16 Wave 1 has since shipped, but its checkout is guest-only, so
Phase 17's real customer identity is still the missing half), so building
the table now would be exactly the "fake functionality ahead of its
consumer" spec rule 178 forbids. `GET /products/export` streams every
product matching the same filters as the list (not just the current
page) as a CSV; `POST /products/import` reads one back, upserting by
`(store_id, sku)` — a new SKU creates a simple product (mirroring
`ProductController::store()`'s own defaults), an existing one updates
only its own base fields, never its `type` or variants, so importing a
tweaked export of a variable product can't silently flatten it. A shared
`App\Support\ProductCsv` column list keeps both directions honest, so a
straight export → edit → re-import round-trips. Missing Category/Brand
names are auto-created (matched case-insensitively first, to avoid
duplicates from casing alone) — a deliberate choice, since naming a
category to import against is real intent, not a typo to silently drop.
A row that fails validation (e.g. a blank Name) is skipped and reported
by row number rather than aborting the whole file, so one bad row in a
large catalog file doesn't cost every good one. Deliberately scoped down
to *simple* products only: a flat CSV row has nowhere to represent a
variant's own SKU/price/attribute-values without a lot more complexity
than a first pass warrants, so import never creates a variable product —
variants stay managed from the product's own Variants tab, matching how
this project has consistently drawn that line all session.

**Phase 5 Wave 2c scope note:** ships the last deferred Wave 2 item —
bundles/combos — closing out Catalog Wave 2 entirely (reviews and the
media library remain deferred for the reasons the notes above already
give). A bundle is a `Product` row with `type='bundle'`, not a separate
`bundles` table as an older, pre-variant-system note in
`DATABASE_DESIGN.md` section 2 used to assume — the `product_variants`
precedent (a `variable` product doesn't get its own table either) is
the more consistent pattern to follow, so that old note is superseded
by this one. A new `bundle_items` table defines a bundle's components
(product + optional variant + quantity); the core design decision is
that a bundle never holds real stock of its own — its "available to
sell" quantity (`App\Support\BundleExpander::availability()`) is
derived by taking, per warehouse, the minimum across every component of
`floor(component_available / component_quantity_needed)`, treating a
component with no stock at a warehouse as zero there rather than
"unconstrained." Selling a bundle reserves/decrements/restocks its
*components'* stock, never the bundle's own. Rather than re-deriving a
bundle's composition live from `bundle_items` at every reserve/ship/
cancel/return step — which would let an edit to a bundle's components
made between order-creation and shipment silently reserve one set of
components and decrement a different set — a new `order_item_components`
table snapshots the resolved (product, variant, quantity) rows once, at
order-creation time (`App\Support\BundleExpander::expand()`), and every
downstream stock operation (Order reserve/ship/cancel, Return receive,
Shipment returned-to-seller) reads that snapshot rather than the
bundle's live definition. This also unifies bundle and non-bundle order
items into one code path with zero branching on product type at the
point of use — `OrderItem::resolvedComponents()` returns the snapshot
when present, falling back to a live `BundleExpander::expand()` call
only for the handful of pre-existing tests that construct an
`OrderItem` directly and bypass the real order-creation flow the
snapshot exists to protect (a fallback that's safe precisely because an
item with no snapshot never went through the race condition the
snapshot prevents). Partial-return proration divides each component's
snapshotted quantity by the order item's own quantity to get an exact
per-unit rate, since the snapshot is always built as an exact multiple.
Deliberately scoped down, the same way every other Wave 2 item was: no
nested bundles (a bundle cannot contain another bundle); Purchasing/
stock-adjustments/stock-transfers never touch a bundle directly (only
its components — enforced by a shared `App\Rules\ProductIsNotBundle`
rule; Orders is the one deliberate exception, since ordering a bundle is
the whole point); the Low Stock report and the Stock Levels list exclude
bundles entirely (a bundle has `track_stock` forced `false` server-side
regardless of what's submitted, which is what keeps it off the Low
Stock report; the Stock Levels list additionally filters
`type != 'bundle'` since a left join would otherwise show it as a
misleading "0 on hand" row); CSV import/export stays scoped to simple
products only, unchanged; and there's no physical "kitting/assembly"
stock action, since a bundle's stock is purely virtual/computed, never
a real inventory movement of its own.

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

**Phase 7 Wave 2a scope note:** ships the first Purchasing Wave 2 item —
purchase returns — mirroring Phase 10's customer-facing Returns almost
exactly (`requested` → `approved` → `shipped_back` → `credited`, or
`rejected` from `requested`/`approved`), the strong precedent that
resolves the "needs a real trigger before its workflow can be designed
with confidence" reason Wave 1's note above gave for deferring it. New
`purchase_returns`/`purchase_return_items`/`purchase_return_status_history`
tables. A return can only be requested against a purchase order with
something actually received (`partially_received`/`received`), and its
per-line quantity is capped by `quantity_received` minus whatever's
already covered by a non-rejected return on that line — not
`quantity_ordered`, since goods still in transit can't physically be sent
back. `POST .../{id}/ship-back` is where stock actually decrements (a new
`purchase_return` stock-movement type, the mirror image of
`purchase_receipt`), guarded the same way `OrderController::ship()`
guards against a negative result, in case stock moved elsewhere between
the return being approved and physically packed. `POST .../{id}/credit`
records a supplier credit note — not a cash refund, and not applied
against anything, since no accounts-payable ledger exists yet (that's the
still-deferred **supplier ledger** item below); it's a bookkeeping record
of how much credit the return is worth, defaulting to the covered items'
original unit cost, overridable the same way `ReturnController::refund()`'s
suggested amount is. Still deferred, for the same reasons Wave 1's note
already gave: supplier payment terms/ledger and multi-currency POs
(accounting-heavy, no consumer yet — a credit note existing is not the
same as a ledger to apply it against), a PO approval/sign-off workflow
(still no multi-user approval concept anywhere in the app), and
low-stock-driven reorder suggestions (Phase 18/20 reporting infra is now
built, so this one is no longer *blocked* — just not yet picked).

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
(swap for a different product/variant — order line items are
variant-aware now, since the variant-aware retrofit below, so there's
something to swap *to* within an order, but the exchange workflow
itself is a separate, unbuilt feature), store credit as a
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
all of that was Phase 18 Reporting's job, not a dashboard widget's. Phase 18
Wave 1 (see note below) has since shipped the date-range/per-warehouse/
payment-method/CSV pieces on dedicated Reports pages, leaving per-courier
breakdowns, PDF export, and period-over-period comparisons still open.

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

**Phase 16 Wave 1 scope note:** ships the app's first public,
unauthenticated API surface — `api/v1/storefront/*`, entirely outside
`auth:sanctum` — and the storefront UI that consumes it, at
`frontend/src/app/(storefront)/`. Deliberately needed almost no new
schema: `GET store`/`categories(/{slug})`/`brands(/{slug})`/
`products(/{slug})`/`locations/*` are new read endpoints over tables
every earlier phase already built (Store/Category/Brand/Product/
ProductVariant/BundleItem/BdDivision-District-Upazila), returned through
new `App\Http\Resources\Storefront\*` classes rather than the admin
resources — the admin ones expose `cost_price`, per-warehouse stock
breakdowns, and other operator-internal fields a public visitor must
never see. `POST checkout` is the one real write path, and the one
place a public, unauthenticated endpoint needed a genuinely different
trust model than every admin endpoint before it: it never accepts a
client-submitted price (there is no `unit_price` field anywhere in the
request — `App\Http\Requests\Storefront\CheckoutRequest` only takes
`product_id`/`product_variant_id`/`quantity`), always resolves the
current price server-side the same field-by-field-fallback way
`VariantResource` decides what a shopper sees on the PDP, and
auto-selects the first active warehouse that can fully cover the
resolved cart (no split-fulfilment across warehouses — Wave 1 doesn't
invent a capability the admin flow doesn't have either). A guest is
matched to an existing `Customer` by phone (store-scoped
`firstOrCreate`) rather than getting a new identity table — and never
renamed on a repeat order, so typing someone else's real phone number
can't rewrite their record. `GET orders/{uuid}` is a receipt lookup by
UUID only, never the sequential `id`, returned through a storefront-only
`OrderResource` that leaves out warehouse/staff-attribution/status-history
detail the admin one exposes. The order-placement logic itself
(`syncItems`/`reserveItems`) was extracted from `OrderController` into a
new `App\Support\OrderPlacement`, since there are now genuinely two real
callers needing identical bundle-snapshot/reservation behavior — the
same "extract once a second consumer exists" rule the `BundleExpander`
extraction followed earlier. A new `orders.source` column
(`'admin'`/`'storefront'`, default `'admin'`) lets staff tell them apart;
the admin Orders list and detail page both surface it as a badge.
Deliberately scoped down, and each cut has a real reason, not just
"ran out of time": guest-only checkout, no customer accounts or login
(Phase 17 — `customers` still has no password column); a hardcoded real
homepage layout, not block-driven (Phase 13's Homepage Builder doesn't
exist yet to feed it — building a block registry with one caller would
be exactly the "fake functionality ahead of its consumer" spec rule 178
forbids); the single active store only, resolved by
`StorefrontController::currentStore()` — `stores.domain`/`slug` already
exist for real multi-tenant routing (see `DATABASE_DESIGN.md` section
1a) but nothing currently seeds a second store to route between; COD
only, no payment gateway (Phase 19 Integrations); a client-side
(`zustand` + `persist`, `localStorage`-backed) cart, no server-side
`carts`/`cart_items` table — checkout always re-validates/re-prices from
the live catalog regardless of what the cart's stale snapshot shows, so
nothing trusts the client cart for anything that matters; no reviews,
coupons, or wishlist (still no real writer/consumer for any of them —
reviews specifically still needs Phase 17's customer identity, guest
checkout alone doesn't give a review a "verified purchase" to attach
to); and no real per-page SEO metadata — `products.seo_title`/
`seo_description` (Phase 5) already round-trip through the storefront
API, but every storefront page here is a Client Component, and Next.js's
`generateMetadata()` needs a Server Component to set a real `<title>`/
meta description per product. Wiring that in means introducing this
app's first server-side data fetch (everything today is client-component
+ TanStack Query) alongside the existing client fetch for the
interactive page body — a real, separate design decision (fetch twice,
once per side, or restructure the data flow) that deserves its own
deliberate pass rather than a rushed add-on here; tracked against
Phase 15 (SEO), not silently dropped. Two small, well-justified
additions beyond `PAGE_INVENTORY.md`'s
original route sketch: a `/products` all-products browse/search page
(the sketch only had `/` and `/products/[slug]`, no index — a real
storefront needs one) and `/order-confirmation/[uuid]` (the sketch's
`/checkout` entry didn't spell out where checkout lands after it
succeeds); see the `PAGE_INVENTORY.md` note for the rest. 27 new backend
tests (220 → 247), all green, Pint-clean, plus a real Playwright
walkthrough against a production build (browse → PDP → variant
selection → cart → guest checkout with the live BD division/district/
upazila cascade → confirmation → verified in the admin Orders list and
detail page with the correct `source` badge, warehouse, and total).

**Phase 18 Wave 1 scope note:** ships three read-only, permission-gated
report endpoints on `ReportController`, activating the `reports.view`
permission the RBAC seeder has carried since Phase 3 but no controller
had checked until now. Sales report: totals (revenue/orders/average
order value) plus a by-period breakdown — day/week/month granularity,
where week/month buckets are folded in PHP over already-fetched
day-level `DATE()` rows rather than in SQL, since MySQL and SQLite (used
by the test suite) don't share a portable week/month truncation function
— and a by-payment-method breakdown, filterable by date range (capped at
366 days) and warehouse. Product performance: units sold + revenue per
product, ranked by revenue, rolling a variable product's variant sales up
to its parent product (same convention as the Stock Levels list and the
Low Stock report below, for one consistent merchandising view across all
three). Low stock report: cross-warehouse quantity/reserved/available
summed per product against its threshold (mirrors the earlier
variant-retrofit fix to `StockLevelController`'s own cross-warehouse
`GROUP BY`/`HAVING`). All three ship a CSV export via
`streamDownload()`, and a new "Reports" nav section (Sales/Product
Performance/Low Stock tabs) fronts them. Deliberately deferred to a
Wave 2 (see `DATABASE_DESIGN.md` section 2): per-courier breakdowns, PDF
export, period-over-period comparisons, and any materialized/scheduled
aggregate table — like `DashboardController`, every report here computes
fresh on each request, fine at current data volume. 10 new backend tests
(164 → 174), all green, plus the existing frontend build/lint/typecheck.

**Phase 18 Wave 2a scope note:** picked per-courier breakdown as the
first Wave 2 item — lowest risk of the three, and reuses a proven
pattern rather than introducing anything new. Adds a `by_courier` array
to `GET /reports/sales`'s response (`courierQuery()`, same shape as the
existing `paymentMethodQuery()`) by inner-joining `orders` to
`shipments`/`couriers`. That inner join is deliberate: an order still
awaiting dispatch has no shipment row yet, so it correctly drops out of
`by_courier` while still counting in the report's own `totals` — the two
are expected to disagree once orders are in flight, not a bug. Shown as
a second table beside "By payment method" on the Sales report page (both
now wrapped in a `Card` for a heading, since a second unlabeled table
would've been confusing); not added to the CSV export, matching the
existing choice not to put `by_payment_method` there either — the CSV is
for period-level data, on-screen tables are for the per-dimension
breakdowns. 1 new backend test (174 → 175), all green, plus the existing
frontend build/lint/typecheck.

**Phase 18 Wave 2b scope note:** adds a period-over-period trend to each
of the sales report's three KPI cards (revenue, orders, average order
value) — "vs previous period," where "previous period" is the same
number of days immediately before the requested range (a 30-day
selection compares against the 30 days right before it), not a fixed
"last calendar month." `ReportController::previousPeriodRange()`
computes that window and `periodTotals()` (extracted from `salesReport()`'s
previously-inline totals math, now shared by both the current and
comparison period) sums it the same way as the primary totals; same
store/warehouse filters, so the comparison stays apples-to-apples. One
subtlety worth flagging for the next date-math change in this file:
the first cut of `previousPeriodRange()` diffed `date_from` (a
`startOfDay`) directly against `date_to` (an `endOfDay`, so a
23:59:59.999999 instant) to get the range's day count, and Carbon's
`diffInDays` rounds that near-whole-day fraction *up* — silently adding
an extra day and shifting the comparison window's start a full day
early. A test asserting the exact comparison boundary caught it; the fix
diffs against `date_to`'s own `startOfDay()` instead, an exact whole-day
difference with no rounding involved. Response-shape addition only: a
new `comparison: {date_from, date_to, totals}` alongside the existing
`totals`/`by_period`/`by_payment_method`/`by_courier`; not added to the
by-period chart or the CSV export (same "on-screen only" reasoning as
`by_payment_method`/`by_courier`). Frontend wires this into `StatCard`'s
existing `trend` prop, which had been defined since early in the
project but had no real consumer until now — "New" is shown instead of
a nonsensical percentage when the previous period had zero orders. 1 new
backend test (175 → 176), all green, plus the existing frontend
build/lint/typecheck.

**Phase 18 Wave 2c scope note:** adds a PDF twin alongside the existing
CSV export on all three reports, closing out Reporting Wave 2. New
dependency: `barryvdh/laravel-dompdf` (pure-PHP, no headless-browser or
system binary needed, so it works the same in this sandbox as it would
on a normal server). Deliberately richer than the CSV twin, not just a
different file format of the same rows: a PDF is a presentable,
shareable snapshot of the whole page, so the sales PDF includes the KPI
totals, the vs.-previous-period trend, and the payment-method/courier
breakdowns the CSV leaves out for spreadsheet-friendliness; product
performance and low stock render their one table plus a header (store
name, date range or "as of" timestamp, generated-at). All three reuse
the exact same private query helpers (`dailySalesRows()`,
`periodTotals()`, `paymentMethodQuery()`, `courierQuery()`,
`productPerformanceQuery()`, `lowStockQuery()`) the JSON/CSV endpoints
already use, so the PDF can't drift from what's on screen. One deliberate
cut: amounts render as `{code} {amount}` (e.g. "BDT 1,234.00") rather
than the ৳ glyph — dompdf's default fonts have no Bengali coverage, and
bundling a Bengali-support font for one symbol wasn't worth it for a
first cut. Frontend swaps each report's single Export button for a
`DropdownMenu` (Export CSV / Export PDF), activating that primitive's
first use outside the topbar. 3 new backend tests (176 → 179), all
green, plus the existing frontend build/lint/typecheck.

## Next Session Should Start With

Phase 16 (Storefront) Wave 1 is done — the app's first public storefront,
guest COD checkout included; see the Phase 16 Wave 1 scope note for what
it deliberately still cuts. That changes the calculus this section used
to give: Phase 17 (Customer Dashboard) is now the clearest, most directly
motivated next pickup, not just next-in-line by phase number — Storefront
Wave 1's single biggest cut (guest-only checkout, no accounts) points
straight at it, `customers` already exists to extend with real
authentication, and `UI_UX_ARCHITECTURE.md` already sketches
`/account/*` reusing storefront chrome. Picking it now also finally
unblocks customer reviews (Catalog Wave 2's last deferred item — a
review needs a real customer identity plus a verified order to attach
to, and only Phase 17 gives both). Phase 18 Reporting Wave 2 is done —
all three items (per-courier breakdown, period-over-period comparison,
PDF export) are shipped; only materialized/scheduled aggregate tables
remain there, and per rule 178 that's infra to build once real data
volume demands it, not a pick-able feature today. Catalog Wave 2 is
otherwise fully closed — bundles/combos (Wave 2c) shipped. Purchasing
Wave 2a (purchase returns) is also done; supplier ledger/multi-currency
POs and a PO approval workflow remain deferred there for lack of a real
consumer (see the Phase 7 Wave 2a scope note), and low-stock-driven
reorder suggestions is no longer blocked (Phase 18's reporting infra
exists now) but hasn't been picked yet. Any already-started phase's own
remaining Wave 2 (Orders, Delivery, Returns, Dashboard, or Purchasing's
own remaining items) is still a reasonable alternative pickup, whichever
the user prefers — but Phase 17 is the one with a real, waiting consumer
now, not a hypothetical one. A reusable media library still has no real
consumer (today's direct-upload-per-record images work fine). Storefront
Wave 2 (multi-store domain routing, non-COD payment methods, real
per-page SEO metadata) stays deferred for the reasons its own scope note
gives — each needs either a second store to route between, Phase 19's
payment adapters, or its own deliberate server-fetch design pass, none
of which exist yet. Do not skip ahead to CMS/Homepage Builder/Blog/SEO
(Phases 12–15) on the theory that a real storefront now exists to feed
them — that's true, and unlike before this makes them legitimately
reachable rather than pure speculation, but the master spec's own
incremental-phases rule (176) still means they wait behind Phase 17,
which has the more direct, already-flagged dependency.

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
