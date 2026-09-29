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
| 12 | CMS | ✅ Wave 1 done (page versions/history, navigation menus, hierarchical pages, scheduled publishing deferred — see note) | Yes — simple content pages (About/Terms/Privacy-style) with plain-text content, admin CRUD under a new "Content" nav section, and public storefront rendering at `/pages/[slug]` plus a footer links column |
| 13 | Homepage Builder | ✅ Full spec done, not a lean wave (see note) | Yes — a real drag-and-drop visual builder: all ~30 block types, a dnd-kit live-preview canvas, full per-block style/responsive/animation overrides, local autosave + undo/redo, server-side revision history with restore, and a reusable saved-sections library |
| 14 | Blog | ✅ Full spec done, not a lean wave (see note) | Yes — a real Blog CMS: rich TipTap posts with categories/tags/SEO/scheduled publishing, server-side version history with restore, admin CRUD under Content, and public `/blog` index/detail/category/tag pages plus an RSS feed |
| 15 | SEO | ✅ Full spec done, not a lean wave (see note) | Yes — polymorphic SEO metadata (nested `seo` object) on every content entity, a real per-block-independent site-wide SEO panel in the Homepage Builder, redirects + SEO templates admin CRUD, real per-page `<head>` metadata + JSON-LD (Server Component conversion), and native `sitemap.xml`/`robots.txt` |
| 16 | Storefront | ✅ Wave 1 done, homepage now block-driven since Phase 13 (multi-store domain routing, non-COD payment still deferred — see note) | Yes — public unauthenticated catalog browsing (products/categories/brands) and guest COD checkout against the single active store |
| 17 | Customer Dashboard | ✅ Wave 1 done (wishlist, customer-initiated returns, checkout saved-address integration deferred — see note) | Yes — customer register/login/logout against a new `customers.password` column, guest-checkout orders auto-linked by phone on registration, and an `/account/*` shell (order history + status timeline, saved addresses, profile) |
| 18 | Reporting | ✅ Wave 1 + Wave 2 (per-courier breakdown, period-over-period comparison, PDF export) done (materialized/scheduled aggregate tables deferred — see note) | Yes — sales report (totals/by-period/by-payment-method/by-courier, day/week/month granularity, date-range + warehouse filters, vs.-previous-period trend on each KPI card), product performance (variant sales rolled up to parent product), and a cross-warehouse low-stock report, each with CSV and PDF export; activates the `reports.view` permission the RBAC seeder has carried since Phase 3 |
| 19 | Integrations (payment/courier/email/SMS/WhatsApp adapters) | ✅ Wave 1 done (real payment/courier/WhatsApp providers, real SMS provider, queued delivery deferred — see note) | Yes — the Adapter Pattern's first real instance: a `SmsGateway` contract + log-mock implementation, order/return lifecycle notifications (mail + SMS to the customer, a database notification to staff), and the admin topbar's notification bell finally wired to real data |
| 20 | Analytics | ✅ Full spec done, not a lean wave (see note) | Yes — first-party storefront behavioral tracking (page/product/category views, searches, cart/checkout funnel, purchases) feeding a new admin Analytics dashboard (traffic trend, top viewed products, search terms incl. zero-result flagging, a 4-stage conversion funnel, new-vs-returning customers), each report with CSV export and the Overview also with PDF |
| 21 | Security Hardening | ✅ Wave 1 done (2FA, account lockout, breach-checked passwords deferred — see note) | Yes — a real global `throttle:api` (60/min per user-or-IP, on top of the existing tighter per-route throttles), an explicit reviewed `config/cors.php` (previously an undocumented framework fallback), Sanctum tokens now expire (30 days, were permanent), a catch-all exception renderer that stops an unexpected 500 leaking a stack trace when `APP_DEBUG` is off, and standard security response headers (`X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`) on every response |
| 22 | Performance | ✅ Wave 1 done (queued jobs, materialized aggregates still deferred — see note) | Yes — measured first (N+1 queries and missing indexes both checked and confirmed clean, not assumed), then closed the one real, verified gap: the storefront category tree and resolved homepage — the two highest-traffic public reads — are now cached and invalidated on every write that could change them |
| 23 | Accessibility | ✅ Wave 1 done (dark-mode accent/solid-fill token split, manual keyboard/screen-reader passes deferred — see note) | Yes — a real axe-core audit across 16 pages found and fixed 9 WCAG violation categories: 79 unlabeled Select combobox triggers across 44 files, systemic color-contrast failures in the shared status-color tokens and their badge tints, a heading-order skip, an empty table header with no screen-reader fallback, and several unlabeled landmarks/date inputs — 0 violations left, both color schemes |
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
homepage layout, not block-driven (Phase 13's Homepage Builder didn't
exist yet to feed it — building a block registry with one caller would
have been exactly the "fake functionality ahead of its consumer" spec
rule 178 forbids; Phase 13 later built it and replaced this page's
hardcoded JSX outright — see that phase's own scope note); the single
active store only, resolved by
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

**Phase 17 Wave 1 scope note:** gives customers real accounts —
register/login/logout against a new `customers.password` column
(nullable; null means a guest-checkout-only record) — and an
authenticated `/account/*` shell, without a second Sanctum guard.
`Customer` now extends `Authenticatable` and uses `HasApiTokens` exactly
like `User` does; no `config/auth.php` change was needed, since Sanctum's
`auth:sanctum` middleware resolves whichever model actually owns the
bearer token through `personal_access_tokens`' polymorphic `tokenable`
relation, regardless of guard config. The real security boundary —
keeping a customer token off admin routes and a staff token off account
routes — is two new small, explicit middleware aliases, `staff` and
`customer` (`EnsureStaffUser`/`EnsureCustomerUser`), that type-check
`$request->user() instanceof User`/`instanceof Customer` and 401
otherwise; deliberately not left to an unverified assumption that a
`User`-type-hinted Policy would fail closed against a `Customer`
instance, since that would be an implicit, untested boundary for a
brand-new cross-model auth surface. Both are stacked alongside
`auth:sanctum` on the existing admin route block and the new
`api/v1/account/*` one.

The headline mechanic is claiming a guest order history: `POST
account/auth/register` looks up an existing `Customer` by `(store_id,
phone)` before creating anything — a match with `password === null` (an
unclaimed guest-checkout record, e.g. from Storefront Wave 1's
`firstOrCreate`) gets claimed (password set, name/email updated) rather
than duplicated into a second, disconnected row; a match with a password
already set is rejected (422, "sign in instead"); no match creates a
fresh row. Because guest checkout already resolves a `Customer` by the
same `(store_id, phone)` pair, every past guest order attaches
automatically the moment the same phone number registers — verified
end-to-end by a dedicated test that places a real no-Authorization-header
storefront checkout after registering with the same number and confirms
both that `Customer::count()` stays at 1 and that the order shows up in
`GET account/orders`. That match only works if checkout-time and
registration-time phone formatting agree, which surfaced a real
pre-existing gap: `App\Rules\BdPhone`/`BdPhoneNumber::normalize()` were
already used for staff `User.phone` but nowhere on `Customer.phone` — not
the admin `CustomerRequest`, not `CustomerAddressRequest`, not
Storefront's own `CheckoutRequest`. Fixed narrowly: `BdPhone` validation
plus `prepareForValidation()` normalization was added to Storefront
`CheckoutRequest.customer_phone` (the identity field) and the new
`Account\RegisterRequest`/`LoginRequest`; `shipping_phone` (a delivery
contact, not an identity key) and the admin customer forms were
deliberately left as they were, matching admin's own already-loose
`OrderRequest.shipping_phone`.

`api/v1/account/*` (customer-gated beyond register/login) exposes: orders
(`index`/`show`, always scoped to `Auth::id()`, never a client-supplied
customer id — someone else's order 404s, never 403, so a UUID can't be
used to probe which orders exist), addresses (full CRUD, the same
default-address-transaction logic as the admin `CustomerAddressController`
but scoped to `Auth::user()`), and profile (name/email only — phone is
the login identifier and stays immutable here, since changing it would
need a re-verification flow this Wave doesn't build). `CustomerResource`/
`CustomerAddressResource` are reused as-is for a customer viewing their
own data; a new `Account\OrderResource` mirrors Storefront's public
receipt shape but adds a customer-safe `status_history` (from/to status
and timestamp only, no staff note or `created_by`) since real order
tracking is the point of being signed in. `CustomerResource` also gained
`has_account` (`password !== null`), surfaced as a Claimed/Guest badge on
the admin Customers list and detail page.

Frontend keeps a customer session fully separate from an admin session on
the same browser: a second token key (`eleventory_customer_auth_token`, its own
`localStorage` event) and a second minimal API client (`accountApi`)
rather than extending the existing `auth-token.ts`/`api.ts` in place.
Routing splits `/account/*` into an outer, ungated `layout.tsx` (so
`/account/login` and `/account/register` stay reachable signed out,
reusing the storefront's own header/footer/cart chrome per
`UI_UX_ARCHITECTURE.md`) and a nested `(dashboard)` route group with its
own gated layout (redirects to `/account/login`, tab nav) wrapping the
overview/orders/addresses/profile pages; the addresses page reuses
`CustomerAddressForm` directly rather than building a second, near-
identical form. That reuse surfaced one real bug, caught only by the
Playwright walkthrough against a production build (not by tests, lint, or
typecheck — none of which exercise cross-token runtime behavior): the
form's division/district/upazila pickers called the admin `useDivisions`/
`useDistricts`/`useUpazilas` (`/locations/*`), which sits behind the
`staff` middleware — so a signed-in customer, with no staff token, got a
silent 401 and a permanently empty dropdown. Fixed by pointing the same
form at `useStorefrontDivisions`/`useStorefrontDistricts`/
`useStorefrontUpazilas` instead, the already-public `/storefront/
locations/*` endpoints Storefront Wave 1's own checkout already relies
on — safe for the admin call site too, since that reference data was
already "nationwide, not store-scoped or sensitive" and the endpoint
never checks for a token either way (see `COMPONENT_INVENTORY.md`'s
Customer Account note). The storefront header gained one small
addition, an account icon linking to `/account` (lands on login when
signed out, the dashboard when signed in) — deliberately just
navigational; checkout's own address collection was left unchanged (it
still always collects a fresh address, even for a signed-in customer),
since wiring a saved-address picker into checkout is a real, separate,
non-trivial feature (detecting sign-in state via a second token,
branching checkout's form) that deserves its own pass rather than being
bundled in because it's related.

Deliberately deferred to a Wave 2, each for lack of a real consumer or
design pass yet: wishlist (no backing schema or consumer anywhere in the
app today); customer-initiated return requests from `/account/orders`
(return creation is still the staff-only flow Phase 10 built); and the
checkout saved-address integration described above. 17 new backend tests
(247 → 264), all green, Pint-clean, plus the existing frontend
build/lint/typecheck and a real Playwright walkthrough against a
production build.

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

**Phase 19 Wave 1 scope note:** ships the Adapter Pattern
(`ARCHITECTURE.md` section 6) for real for the first time — on
notifications, the smallest of the phase's four integration categories,
not payment/courier/WhatsApp. `App\Contracts\SmsGateway` (one method) is
bound to `App\Services\Sms\LogSmsGateway` in
`AppServiceProvider::register()`; no BD SMS provider credentials exist in
this environment (`PROJECT_AUDIT.md` flagged this back in Phase 0), so it
logs the message it would have sent rather than pretending to deliver
it — every caller depends on the interface, so swapping in a real
provider later is a binding change, not a rewrite. A custom
`App\Notifications\Channels\SmsChannel` resolves that gateway from the
container; email rides Laravel's own `mail` channel, already pointed at
this environment's `log` mailer, so it's a real code path with a mock
transport, same as the SMS side.

Four notification classes cover every real event this app already fires
without telling anyone: `OrderPlacedNotification` (customer, mail + SMS,
fired from both `OrderController::store` and
`Storefront\CheckoutController::store` — there's no single shared
order-creation choke point to hook instead, since `OrderPlacement` only
covers item-sync/reservation, not the `Order::create()` call itself, so
this is one line added at each of the two existing call sites, not a
refactor); `NewOrderPlacedNotification` (staff, database-only — powers
the admin topbar bell, `COMPONENT_INVENTORY.md` — fired only for
`source === 'storefront'` orders, since an admin-created order was just
entered by a staff member themselves and notifying them about their own
action would be pure noise, unlike a storefront order they'd otherwise
only see by refreshing the Orders list); `OrderStatusChangedNotification`
(customer, mail + SMS, fired from `process`/`ship`/`deliver`/`cancel`,
reading `$order->status` fresh rather than taking a parameter, since by
the time it fires the transaction that changed it has already committed);
and `ReturnStatusChangedNotification` (customer, mail + SMS, fired from
the shared `transition()` private method that already backs
approve/reject, plus separately from `receive`/`refund`, which update
status inline rather than through `transition()`). Every one of these
fires synchronously, no `ShouldQueue` — this app has never dispatched a
single queued job despite `QUEUE_CONNECTION=database` being configured
since early in the project, so adding queue-worker ceremony for Wave 1's
first notification consumer would be infra nobody runs, not a real
capability. `Customer` gained the `Notifiable` trait (`User` already had
it); `via()` on every customer-facing notification checks for a non-null
email before including `mail`, since `Customer.email` is nullable and
`phone` is not — a guest with no email on file still gets an SMS.

Staff-facing notifications are scoped to `current_store_id` matching the
order's store, filtered to users who `can('orders.view')` — the same
"no new RBAC permission, just an existing one, queried directly" choice
Inventory/Purchasing's direct `$user->can()` checks already established,
not a new `notifications.*` permission, since no such permission would
mean anything beyond "can you see orders" anyway. `GET /api/v1/notifications`,
`POST .../{id}/read`, and `POST .../read-all` are always scoped to
`$request->user()` — there is no way to pass another user's id and read
their inbox, the same reasoning `auth/me` needs no permission gate beyond
being authenticated. Deliberately deferred to a Wave 2, each for lack of
real provider credentials in this environment: a real BD SMS provider, the
`CourierInterface`/`PaymentGatewayInterface` contracts section 6 still
documents as target-only (Delivery's `couriers` are manually-entered
records with no outbound API to adapt yet; Orders Wave 2's payment ledger
is still unbuilt — see `DATABASE_DESIGN.md` section 2), a WhatsApp
channel, and queued (rather than synchronous) delivery once a real queue
worker actually runs. 15 new backend tests (264 → 279), all green,
Pint-clean, plus the existing frontend build/lint/typecheck and a real
Playwright walkthrough against a production build.

**Phase 12 Wave 1 scope note:** ships simple CMS content pages — the
About Us/Terms & Conditions/Privacy Policy style of static page, not a
page builder (Phase 13) or blog (Phase 14). A new `pages` table
(`store_id`, `title`, `slug`, `content` text-nullable, `meta_title`,
`meta_description`, `status` default `draft`, `created_by`, soft deletes,
`unique(store_id, slug)`) backs it, mirroring `Category`'s per-store-slug
convention exactly (`Rule::unique(...)->where('store_id', $storeId)`).
`PagePolicy` maps all five ability methods to one `pages.manage`
permission rather than Category's 4-way split — not a new convention
invented for this phase, but discovery of one already committed: the RBAC
seeder had `pages.manage` pre-wired to the SEO Manager and Content
Manager roles since Phase 3, dormant until now. Content is deliberately
plain text (a `Textarea`), not rich text or Markdown — `COMPONENT_INVENTORY.md`
already reserved TipTap for Phase 14's blog post editor, and introducing a
second editor dependency ahead of that phase would be scope creep; the
storefront renders it with `whitespace-pre-line`, the same convention
`Product.description` already uses. Admin UI lives under a new "Content"
nav section (`/content/pages`, gated on `pages.manage`) — deliberately
without the shared tab-nav layout Purchasing/Reports use, since Blog
(Phase 14) hasn't shipped yet to be a second tab; that infrastructure gets
added the moment its phase does, same principle as every other
single-resource nav section so far. Public side: `GET /storefront/pages`
(published-only, backs a new "Information" footer column) and
`GET /storefront/pages/{slug}` (published-only, 404s on draft or
wrong-store) back a new `/pages/[slug]` storefront route, styled like the
product detail page's description block. Deliberately cut to Wave 2: page
versioning/history, navigation menus (`navigation_menus`/`navigation_items`
tables spec section 12 describes), hierarchical/nested pages, and
scheduled publish/unpublish dates — none of which a first "About Us" page
needs. 11 new backend tests (279 → 290), all green, Pint-clean, plus the
existing frontend build/lint/typecheck and a real Playwright walkthrough
against a production build.

**Phase 13 scope note:** ships the full drag-and-drop Homepage Builder
per the complete master spec (sections 58-63) — explicitly requested in
full rather than a lean wave, so this phase has no "Wave 2" of its own
left over. All ~30 block types (Hero/Hero Slider/Announcement Bar,
Featured/Latest/Best-Selling Products, Category/Brand/Product Carousels,
Category Grid, Flash Sale, Countdown, Promo/Two-/Three-Column Banners,
Video, Image+Text, Rich Text, Testimonials, Reviews, FAQ, Newsletter,
Gallery, Trust Badges, Statistics, CTA, Blog Posts, Custom HTML/CSS,
Spacer) have a real admin Content-tab editor and a real storefront
renderer, built against one block-registry pattern (`ARCHITECTURE.md`
section on Phase 13 has the full technical breakdown — registry files,
the shared resolver trait, the two bugs real browser testing caught and
fixed, the placeholder models). The builder itself: a real dnd-kit
sortable canvas rendering every block (draft included) through the exact
component the live storefront uses; per-block Content/Design/Layout
(spacing+responsive+visibility combined)/Animation/Advanced/SEO panels;
a style-override engine turning stored design JSON into real scoped CSS
per breakpoint; local autosave + undo/redo; server-side revision history
with a restore action; and a reusable saved-sections library (save any
block, insert it anywhere later). The storefront homepage
(`app/(storefront)/page.tsx`) is now fully block-driven — no hardcoded
sections left to maintain, it just renders whatever's published. SEO tab
is an honest placeholder (real per-block SEO metadata/analysis is
Phase 15's explicit job) rather than fabricated scoring. 39 new backend
tests (290 → 329), all green, Pint-clean, plus frontend build/lint/
typecheck and a real Playwright walkthrough against the running dev
stack — which is what actually caught both bugs `ARCHITECTURE.md`
describes; neither tsc, eslint, nor the backend test suite alone would
have.

**Phase 14 scope note:** ships the full Blog CMS — explicitly requested
in full rather than a lean wave, and against this project's own
previously-documented target schema rather than a master-spec section:
a dedicated research pass this session conclusively established the
189-section master spec was never committed to this repository (it only
ever existed as chat-pasted content across the session), so this phase
was designed from `DATABASE_DESIGN.md`'s own forward-looking notes,
established conventions, and full-featured-blog-CMS judgment, per the
user's explicit "use your best judgment" instruction. Real posts (rich
TipTap body reusing Phase 13's `RichTextEditor` as-is, exactly as
`COMPONENT_INVENTORY.md` already documented it would; a manual excerpt
falling back to an auto-truncated plain-text lead-in; featured image;
SEO fields; draft/published status; real scheduled publishing via
`published_at <= now()` with zero extra cron infrastructure, unlike
Phase 13's dedicated Artisan command), flat categories (deliberately
non-hierarchical, unlike products' `Category` — the near-universal blog
convention), and many-to-many tags. Server-side version history mirrors
Phase 13's `homepage_block_revisions` pattern exactly: one snapshot per
save, and restoring a version is itself a change that gets its own
snapshot first. Absorbs Phase 13's placeholder `BlogPost` model exactly
as that phase's own code comments anticipated: an ALTER migration (not
drop-and-recreate) preserves the 3 existing demo rows, backfilling
`is_active = true` to `status = published` before dropping the column
entirely rather than running both in parallel. `blog.manage` is a single
umbrella permission — not new, but another dormant discovery: the RBAC
seeder had it pre-wired to Marketing Manager/SEO Manager/Content Manager
since Phase 3, and `BlogPostPolicy`'s own Phase 13 comment explicitly
anticipated this exact handoff. Admin UI adds Blog as a third tab under
Content (Posts/Categories/Tags, gated on `blog.manage`) — the first
Content sub-resource to get its own nested tab-nav; Categories/Tags use
dedicated create/edit routes matching `Page`'s established pattern, not
an inline dialog. Storefront adds `/blog` (paginated, searchable),
`/blog/[slug]` (detail + related posts by category), `/blog/category/[slug]`
and `/blog/tag/[slug]` (paginated archives), and a hand-built RSS 2.0
feed at `GET /storefront/blog/rss` — plus Blog links in the header and
footer nav. Reading time and the excerpt fallback are computed at the
Resource layer at read time, never stored, so edits to `body` keep them
fresh automatically; a new `BlogPost::scopePublished()` local scope —
this app's first — keeps that one "is this visible" condition from
drifting across the storefront's four read paths and the homepage
builder's own Blog Posts block. Deliberately cut: a comments/moderation
subsystem — not part of this project's own documented target schema, a
genuinely large separate feature (its own table, spam states, a public
submission UI, notification hooks) that would roughly double this
phase's size, and real spec-rule-178 risk (fake functionality) if built
without genuine safeguards. 29 new backend tests (329 → 358), all green,
Pint-clean, plus frontend build/lint/typecheck and a real Playwright
walkthrough against the running dev stack — post creation with
category/tag/body/status, a version-history snapshot-and-restore round
trip, and all four storefront routes plus the RSS feed.

**Phase 15 scope note:** ships full SEO tooling — explicitly requested in
full rather than a lean wave, and (like Phase 14) against this project's
own previously-documented target schema rather than a master-spec section,
since a dedicated research pass this session re-confirmed the master spec
was never committed to this repository. The central design move: a single
polymorphic `seo_metadata` table (`entity_type`/`entity_id`, matching
`activity_logs`' existing convention, not one nullable-FK-per-entity-type)
supersedes three earlier ad-hoc SEO field sets entirely rather than running
alongside them — `products.seo_title`/`seo_description`/`focus_keyword`
(Phase 5), `pages.meta_title`/`meta_description` (Phase 12), and
`blog_posts.meta_title`/`meta_description` (Phase 14) are all backfilled
into `seo_metadata` rows and dropped in one migration, the same
"supersede, don't parallel" discipline Phase 14 used for
`is_active`→`status`. Every SEO-bearing entity (Product, Category, Brand,
Page, BlogPost, BlogCategory, BlogTag, and the Store itself for site-wide
SEO) gets a `seoMetadata()` morphOne relation and accepts/returns its SEO
data as a nested `seo` object on its own existing endpoint — mirroring how
`BlogPost` already accepts `tag_ids` and syncs a pivot as part of one save
— via a new shared `SyncsSeoMetadata` controller trait, not a dedicated
seo-metadata REST resource. `redirects` and `seo_templates`, by contrast,
are genuinely independent resources and get their own standalone admin CRUD
under a new "SEO" tab in Content (`/content/seo/redirects`,
`/content/seo/templates`), reusing the `seo.manage` permission the RBAC
seeder had pre-wired to SEO Manager/Content Manager since Phase 3 — another
dormant-permission discovery, same pattern as `pages.manage` and
`blog.manage` before it. A real, deterministic, rule-based SEO checklist
(title/description length targets, focus-keyword presence) replaces Phase
13's honest SEO-tab placeholder — never a fabricated AI-style score, per
spec rule 178. The Homepage Builder's `SeoPanel` now edits one site-wide
record (via `Store.seoMetadata()`, a new `store-seo` endpoint gated
directly on `seo.manage` rather than the heavier `stores.manage`
`StorePolicy` requires) regardless of which block is selected on the
canvas — matching its own placeholder copy ("Page-level SEO metadata...
arrives with Phase 15"), not per-block data. The biggest technical risk —
`ARCHITECTURE.md`'s own Phase 13 note flagged it, and Phase 16's scope note
tracked it here explicitly — was that every single storefront page is a
Client Component, so nothing anywhere emitted a real, dynamic `<title>` or
meta description; fixed via the "fetch twice, once per side" approach both
notes anticipated: every storefront leaf page (`/products/[slug]`,
`/category/[slug]`, `/brand/[slug]`, `/blog/[slug]`, `/blog/category/[slug]`,
`/blog/tag/[slug]`, `/pages/[slug]`, and the homepage) is now a Server
Component wrapper with a real `generateMetadata()` (a new server-only
`storefrontApi` client — the existing browser-oriented `api` client
can't be called from server code, it imports a `"use client"` module) and
JSON-LD (Product/BreadcrumbList/Article/Organization+WebSite), rendering
the existing interactive Client Component as its child, completely
unchanged. Redirect resolution is inline per-leaf-page (checked only when
the entity-by-slug lookup itself 404s, via a new public
`GET storefront/redirects/lookup`), not global middleware, so a normal
request never pays for a redirects-table lookup it doesn't need; Next's
App Router only ever emits 307/308 for a programmatic redirect (never an
exact 301/302), so a stored 301/308 maps to `permanentRedirect()` and
302/307 to `redirect()` — the closest available primitive, not a dropped
feature. `sitemap.xml`/`robots.txt` are Next.js's own native
`app/sitemap.ts`/`app/robots.ts` special files, not a Laravel endpoint,
since robots.txt must disallow this same app's own admin/account/cart/
checkout paths — routes the Laravel API has no visibility into; both are
verified working end-to-end against a production build (real XML/txt
output, real per-product/category/blog-post URLs). New `Accordion` UI
primitive (Radix-based, `COMPONENT_INVENTORY.md` had already reserved it
for this phase) groups the SEO checklist and OG/Twitter/Advanced fields in
the new shared `SeoFields` form section wired into all seven admin forms.
25 new backend tests (358 → 383), all green, Pint-clean, plus frontend
typecheck/lint/build clean and real verification (curl + a headless
browser) against both the dev server and a production build — confirming
real `<title>`/meta description/canonical/OG/Twitter tags and JSON-LD
render before any client JavaScript runs, and that a nonexistent slug with
no matching redirect returns a genuine HTTP 404.

**Phase 20 scope note:** ships real first-party behavioral analytics, not
Reporting (Phase 18) under a new name — Reporting aggregates transactional
tables that already exist (orders/order_items); Analytics tracks
behavior nothing else records (a visit, a product view, a search, a cart
add, a checkout that never finished). A new `analytics_events` table
(`entity_type`/`entity_id` reusing the same polymorphic convention
`activity_logs`/`seo_metadata` already use, but only ever populated
server-side from a validated `product_id`/`category_id` — see below) is
written by one new public, unauthenticated, throttled endpoint,
`POST storefront/analytics/events`, that the storefront's own pages call
fire-and-forget (`fetch(..., {keepalive:true})` — not `navigator.
sendBeacon`, the usual choice for this: confirmed against a real browser,
a beacon request's forced-credentialed mode gets silently rejected by
this API's wildcard-origin CORS *after* `sendBeacon()` already reports
success, so every event would vanish with no fallback ever running) for
eight event types: `page_view` (one call in the
storefront's shared layout covers every page), `product_view`,
`category_view`, `search` (with its result count — a zero-result search is
flagged in the report, the single most actionable row in it), `add_to_cart`/
`remove_from_cart` (hooked directly into the cart Zustand store, covering
every add-to-cart call site in one place), `checkout_start`, and `purchase`.
A client-fired analytics ping is inherently spoofable — no tool's isn't —
but nothing here lets that inflate *revenue*: a `purchase` event stores
only an `order_uuid`, and the controller looks up that order's real
`total_amount` server-side rather than trusting anything the client
claims, so a spoofed event can inflate a conversion *count* at worst, never
a reported currency figure. Five read-side admin reports (`analytics/
overview` with a traffic trend + conversion rate, `analytics/products`
ranking view count with a view-to-cart rate per product, `analytics/
searches` grouped by query with zero-result flagging, `analytics/funnel`
— unique sessions reaching each of 4 stages with stage-over-stage
conversion, no export since it's a 4-row summary not a report — and
`analytics/customers`, the one exception that reads straight from
`orders`/`customers` rather than an event, since "new vs returning" is
order-shaped data Reporting's own conventions already cover) are pure
runtime aggregation, same "compute it fresh, no materialized table"
reasoning as Reporting and DashboardController. CSV export on every
report except the funnel; a richer PDF snapshot (KPIs + trend) on
Overview only, mirroring exactly how Sales was the one Reporting report
that earned the richest PDF. Deliberately cut, not deferred to a numbered
Wave — none has a real consumer yet and each would need infrastructure
this environment doesn't have: a `customer_id` column on `analytics_events`
(no storefront route runs optional Sanctum auth today to populate it — a
future real need, not invented ahead of one), real-time/live visitor
counts (no WebSocket infra), third-party pixel integrations (no ad
platform credentials, same reasoning Phase 19's real SMS/payment
providers are still deferred), and IP-based geolocation (no geo-IP
service). 16 new backend tests (383 → 399), all green, Pint-clean, plus
frontend typecheck/lint/build clean.

**Phase 21 scope note:** picked the five items with a concrete, already-
identified shape over a vague "harden everything" pass — spec rule 178's
reasoning applies here too: a fix needs a real, verified gap behind it, not
an imagined one. `API_DESIGN.md` section 8 had flagged the headline item
since Phase 3: no global `throttle:api` was ever wired up, only per-route
throttles on login/register/checkout. Fixed by defining the named `api`
`RateLimiter` (`AppServiceProvider::boot()`, 60/min per authenticated user
or IP) and attaching it via Laravel's own `$middleware->throttleApi()` —
disabled under the test suite (`app()->runningUnitTests()`) since 400+
tests share one in-process array cache and IP and would otherwise throttle
each other; a dedicated test re-registers the limiter with a tiny value to
prove it's real. Verified live against a running server, not just the
test's bypassed path: 59 real requests to a storefront route returned 200,
the 60th on returned 429. Four more gaps came from actually reading the
current config rather than assuming: no `config/cors.php` ever existed, so
the wildcard-origin CORS behavior confirmed during Phase 20 was an
undocumented Laravel framework fallback, not a decision anyone had
reviewed — now an explicit, version-controlled file with the exact same
values (verified byte-for-byte via a live curl before and after), since a
wildcard origin is still correct for an API where every client
authenticates with a bearer token, never a cookie. Sanctum tokens never
expired (`expiration: null`); now 30 days, needing no frontend change since
both `auth-token.ts` and `customer-auth-token.ts` already clear their token
and bounce to `/login` on any 401. An unexpected exception (nothing to do
with the four already-handled types) fell through to Laravel's default
renderer, which includes the exception message, file path, and stack trace
in the JSON body whenever `APP_DEBUG` is on — a real production risk if
that flag is ever left on by accident; a fifth `render()` callback now
catches anything unhandled and, only when `config('app.debug')` is false,
returns the same generic envelope every other error already uses (debug-on
behavior, including in every existing test, is untouched). Standard
response headers (`X-Content-Type-Options: nosniff`, `X-Frame-Options:
DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Content-
Security-Policy: default-src 'none'` — safe globally since nothing this
API returns is HTML a browser should render) now apply to every response
via a new `SecurityHeaders` middleware. `composer audit` and `npm audit`
both ran clean (verified, not skipped) — no dependency vulnerability to
fix on either side. Deliberately cut: 2FA and persistent account lockout
beyond the existing `throttle:6,1` on login are substantial features with
no existing consumer/request behind them, not a config fix, so building
them now ahead of any real need would be exactly the kind of ahead-of-its-
consumer work rule 178 forbids; breach-checked passwords
(`Password::uncompromised()`) were considered and dropped for this pass —
it calls a third-party API (k-anonymity range query to the Pwned Passwords
service) from inside the registration/reset flow, and making a core auth
path depend on an external service's availability is a real behavioral
change, not a config toggle, that deserves its own deliberate look rather
than riding in on a hardening pass. 4 new backend tests (400 → 404), all
green, Pint-clean.

**Phase 22 scope note:** measured before touching anything, per this
phase's own next-steps note from the previous session — same reasoning as
every other phase's scope note: a fix needs a verified gap behind it, not
an assumption. Two hypotheses turned out to be non-issues: N+1 queries
(a differential query-count test — 3 rows vs. 15 rows, same endpoint —
showed a flat query count on both the admin orders list and the storefront
products list, once a Spatie permission-cache warm-up call removed a
confound from the first measurement) and missing indexes (the `orders`
and `products` migrations already carry composite `[store_id, status]`
indexes and more; spot-checking the two highest-traffic tables found
nothing to fix). The one real, concrete gap: `ARCHITECTURE.md` section 8
claimed settings/navigation/categories/the published homepage were already
cached and invalidated on write, and that heavy work already ran queued —
neither was true (`grep` for `Cache::remember`/`ShouldQueue`/`dispatch(`
across `app/` returned nothing at all). Queuing stays unactionable for the
same reason Phase 19's real SMS/payment providers do — `QUEUE_CONNECTION=
database` is configured, but nothing processes the `jobs` table without a
running worker, which doesn't exist in this environment, so converting
real work to `ShouldQueue` now would silently stop it running rather than
defer it. Caching had no such blocker, so that's what shipped: the
storefront's category tree and resolved homepage (the two highest-traffic
public reads — the latter re-running up to ~30 block types' worth of
queries on literally the site's front page, on every single view) are now
cached, keyed per store on the model itself
(`Category`/`HomepageBlock::storefrontCacheKey()`) so the admin
controller that invalidates and the storefront controller that reads
can never drift apart. Invalidation is a `CategoryObserver`/
`HomepageBlockObserver` pair (`saved`/`deleted`) for the two models —
verified first that every mutating action for both goes through a model
instance (`create()`/`update()`/`delete()`, which fires the events an
observer needs) rather than a query-builder bulk update, with one
exception: `HomepageBlockController::reorder()` updates each block by
`HomepageBlock::where('id', $id)->update(...)`, which never fires model
events, so that one action invalidates explicitly instead — the kind of
thing that's only safe to build after actually reading how each write
path works, not by assuming an observer catches everything. Deliberately
cut: `Setting`/navigation caching (no controller reads `Setting` on a hot
path yet, and navigation menus don't exist as a shipped feature — Phase
12 Wave 2 — so nothing there to cache ahead of a need); real queued jobs
(see above); materialized/scheduled aggregate tables for Reporting/
Analytics (still infra to build once real data volume demands it, same
reasoning those two phases' own scope notes already gave, not something
this pass changes).

A real bug shipped in the first pass and was only caught by live
verification against a running `php artisan serve`, not the automated
suite: both caches initially stored the raw Eloquent Collection/API
Resource output (`CategoryResource::collection($categories)` and
`resolveBlockData()`'s `ProductResource::collection(...)` etc.) rather
than a plain array. That round-trips fine through `artisan tinker`, but
the moment a real request read it back, PHP's `unserialize()` produced a
`__PHP_Incomplete_Class` and every hit past the first crashed with a 500
— Resources and Eloquent Collections carry framework internals (relation-
loader closures, a request reference) that plain `serialize()` can't
safely reconstruct. The test suite's `array` cache driver (`phpunit.xml`)
never actually serializes anything — a cached value just sits in memory
as the same live PHP object — so all 5 tests above passed while the bug
shipped. Fixed by caching `json_decode(json_encode(...), true)` of the
resource output instead — the plain, already-JSON-ready array every
Resource ultimately produces anyway — in both controllers. Two more tests
force `config(['cache.default' => 'file'])`, the simplest store that
actually calls `serialize()`/`unserialize()`, specifically so this class
of regression can't silently reappear; both were confirmed to fail
against the reverted (broken) code before being confirmed green against
the fix, the same red-green discipline the fix itself deserved. 7 new
backend tests total (404 → 411), all green, Pint-clean.

**Phase 23 scope note:** measured first, same discipline as Phase 22 —
an axe-core (Playwright) audit across 8 representative pages found 9
distinct WCAG violation categories, all real, none guessed. Fixed:

- **`button-name` (critical):** every Radix `Select` renders a
  `role="combobox"` button whose accessible name must describe its
  *purpose*, not its selected value — the value text `SelectValue`
  renders visibly doesn't count toward that computation the way a plain
  button's text would (confirmed via an isolated minimal-HTML
  reproduction before trusting it as real, not a tool false-positive).
  204 raw `SelectTrigger` grep hits across 44 files turned out to be 79
  real elements needing a fix (the rest were the JSX closing tags and
  import lines the same string also matches). Delegated to 5 parallel
  background agents, each owning a disjoint file batch in its own
  isolated git worktree, with an explicit two-pattern decision tree in
  every prompt: wire `id`/`htmlFor` to an adjacent `<Label>` where one
  exists (most form fields), else add a purpose-describing `aria-label`
  directly (standalone filter toolbars, and per-row selects in a
  repeating list, e.g. `` `Product for item ${index + 1}` `` — row-aware
  since nothing else distinguishes those rows for a screen reader). All
  5 agents worked in the same repository but touched zero overlapping
  files; their uncommitted worktree diffs were reapplied onto the main
  tree with `git apply --3way` (not a branch merge — the agents edited
  working-tree state, never committed) and merged cleanly, including the
  4 files where an agent's fix and this session's own date-filter-label
  fix landed on different lines of the same file. A post-merge script
  independently verified all 79 `SelectTrigger`s carry exactly one of
  `id=`/`aria-label=` (never both, never neither) and every `id` has a
  matching `htmlFor` in the same file — not just trusting each agent's
  self-report.
- **`color-contrast` (serious), the largest follow-on:** re-running the
  full audit after the button-name fix surfaced this was far more
  systemic than the first 8-page sample showed. `--color-success/
  warning/danger/info` all failed 4.5:1 as plain text on white (warning
  as low as 2.15:1) *and* inside their own `bg-{color}/10` badge tint
  (as low as 1.99:1) — a shared `Badge` component used everywhere, so
  fixing it once at the token level (darken each, same hue/saturation,
  found by an HSL-lightness search rather than picked by eye, each
  verified to clear both contexts with real margin) fixed every status
  pill and StatCard trend indicator/chart-tooltip color at once.
  `--color-text-muted`'s Phase-2-era-then-earlier-this-phase fix
  (`#94A3B8` → `#677690`) passed against white but was still short
  against the app's actual `#F8FAFC` background, which is what it
  usually sits directly on — darkened once more to `#5F6D88`.
  `AlertDescription` moved from `text-text-secondary` to
  `text-text-primary` (its own tinted alert background pulled effective
  contrast below 4.5:1; `AlertTitle` already used the safe color).
  `bg-primary/10` text/icon tints (`Avatar` initials, `Badge`,
  `StatCard`, plus 3 more call sites a follow-up grep for the same class
  string found in the homepage builder's tab/sidebar highlights and the
  storefront PDP's variant picker) moved to `/8`, leaving the primary
  brand blue itself untouched. One reported finding
  (white-on-`bg-primary` button, 4.36:1) was chased down and disproved:
  Playwright's mouse cursor was left hovering the button from an earlier
  page's login click, transiently triggering `hover:bg-primary/90` —
  confirmed by reading the button's live `getComputedStyle` background
  with the mouse moved away (`rgb(37, 99, 235)`, the un-hovered token
  value, contrast 5.17:1) before concluding it wasn't a real bug; the
  audit script now moves the mouse away between every page for exactly
  this reason. A dark-mode spot-check (not part of the original 8-page
  sample, but checked anyway once color tokens were being touched)
  caught a real regression before it shipped: naively giving `--color-
  primary`/`--color-danger` their own darker-for-light-mode values would
  have made them *worse* as text against dark surfaces, and a follow-up
  lightened dark-mode-only value fixed that but broke the *other* role
  those two colors play (white text on a solid button background,
  which was already fine and needs the opposite property) — proven
  computationally in both directions before reverting to leave both
  tokens unset in dark mode (inheriting light mode's value, exactly the
  pre-existing, not-measured-by-this-phase state) rather than shipping a
  partial fix that traded one failure for another. `--color-success/
  warning/info` keep an explicit dark-mode override restoring their
  original (pre-darkening) values, which already clear 4.5:1 there.
- **`heading-order`:** `CardTitle` was an `<h3>` under a bare `<h1>`
  page title with no `<h2>` between them (used on `/dashboard` and
  elsewhere) — now `<h2>`. The Homepage Builder's Hero block heading was
  a styled `<p>`, not a heading element at all — now `<h1>` (a page
  should have exactly one).
- **`empty-table-header`:** `DataTable` columns with no visible header
  text (an actions column, typically) rendered a blank `<th>`; now falls
  back to a humanized `sr-only` label built from the column id.
- **Landmarks and labels:** the login page's outer wrapper became a
  `<main>`. 12 tab-bar `<nav>`s (11 admin section-layout files plus the
  product form's own internal section tabs, found only because the
  post-SelectTrigger-fix re-audit specifically flagged `/catalog/
  products/new` for `landmark-unique` — two indistinguishable unlabeled
  `<nav>`s on one page) each got a specific `aria-label`. 7 analytics/
  report pages had two adjacent, identically unlabeled `<input
  type="date">`s; each pair now has `"From date"`/`"To date"`.

Verification: full axe-core re-audit across 16 pages (the original 8
plus 8 more chosen to cover every fix batch, correcting an earlier
mislabeling in the audit script itself — `/products` is the *public
storefront* listing, not the admin catalog, which is at `/catalog/
products`) — 0 violations, both color schemes. Frontend typecheck/lint/
build clean throughout; no backend changes this phase. Deliberately not
done: the dark-mode primary/danger accent-vs-solid-fill token split
noted above (a small architecture change, correctly scoped out of a
token-value pass); a full keyboard-only manual pass (Radix gives correct
keyboard/focus/ARIA semantics for every primitive already in use, which
is what a11y audits actually check for programmatically, but a human
tabbing through each flow hasn't happened); and screen-reader-specific
testing beyond what axe-core's accessible-name/ARIA-semantics rules
check.

## Next Session Should Start With

Phase 21 (Security Hardening), Phase 22 (Performance), and Phase 23
(Accessibility) Wave 1s are all now done — see their scope notes above
for what shipped and what's still deliberately cut. **Phase 24
(Responsive QA)** is the next not-yet-started numbered phase in the
master table (rule 176's own order). The table's current "🟡 Baseline
(login/dashboard tested at all breakpoints)" entry predates this
session's Phase 22/23 measure-first discipline, so — same lesson learned
twice now — don't assume that baseline generalizes to the ~90 other
pages; check a representative sample at each of the 4 breakpoints
(`DESIGN_SYSTEM.md` section 5: mobile `<640px`, tablet `640–1024px`,
desktop `1024–1440px`, large `1440px+`) with real browser viewport
resizing (Playwright, already the established tool this session for
Phase 17/19/20's e2e verification and Phase 23's whole audit), not just
the two pages already covered. The Homepage Builder's canvas, the
largest data tables (Products, Orders), and the biggest forms (the
product form, the order form's line-item rows) are the highest-risk
candidates for anything genuinely breakpoint-specific, versus pages that
just reuse the same list/form/card primitives already verified.

Phase 17 (Customer Dashboard) Wave 1, Phase 19 (Integrations) Wave 1,
Phase 12 (CMS) Wave 1, the full Phase 13 (Homepage Builder), Phase 14
(Blog), Phase 15 (SEO), and now the full Phase 20 (Analytics) are all
done — real customer accounts with guest orders auto-claimed by phone, an
`/account/*` shell, real order/return lifecycle notifications (mail/SMS to
the customer, a database notification driving the admin topbar bell),
simple content pages manageable in the admin and rendered on the
storefront, a complete drag-and-drop homepage builder with ~30 block types
feeding a fully block-driven storefront homepage, a real Blog CMS
(categories, tags, version history, scheduled publishing, RSS) with its
own storefront section, full SEO tooling (polymorphic per-entity SEO
metadata, redirects, SEO templates, real server-rendered `<head>` tags +
JSON-LD on every storefront page, and native sitemap/robots), and now
real first-party behavioral analytics (storefront event tracking feeding
a traffic/products/searches/funnel/customers admin dashboard); see each
phase's own scope note for what they deliberately still cut. Phase 13
(and Phase 14 right behind it) shipped out of the order rule 176 would
otherwise have picked — both explicitly requested in full ahead of
everything else — so the already-flagged older dependency they jumped is
still open: Phase 17 finally unblocked Catalog Wave 2's one remaining
item, customer reviews (a review needs a real customer identity plus a
verified order to attach to, and Phase 17 gives both — `/account/orders`
already shows a signed-in customer their own delivered orders), and that
has been the clearest rule-176 pickup since before Phase 13 was
requested; it still is. Phase 20 was likewise requested by number ahead
of that queue.

Reasonable alternative to Phase 22, whichever the user prefers: Phase 19
Wave 2 itself (a real BD SMS provider, the
courier/payment gateway adapters section 6 of `ARCHITECTURE.md`
documents as target-only, a WhatsApp channel, queued delivery — each
still blocked on real provider credentials or a running queue worker,
neither of which exist in this environment); Phase 17 Wave 2 (wishlist,
customer-initiated returns, checkout saved-address integration — see that
scope note); Phase 12 Wave 2 (page versioning, navigation menus,
hierarchical pages, scheduled publishing — see that scope note);
Storefront Wave 2 (multi-store domain routing, non-COD payment — each
still blocked on a second store to route between or Phase 19 Wave 2's
payment adapters; real per-page SEO metadata itself shipped with Phase
15); or any already-started phase's own remaining Wave 2 (Orders,
Delivery, Returns, Dashboard, or Purchasing's supplier ledger/PO approval
workflow/reorder suggestions). Phase 18 Reporting stays fully shipped
through Wave 2; only materialized/scheduled aggregate tables remain
there, still infra to build once real data volume demands it, not a
pick-able feature today. A reusable media library still has no real
consumer (today's direct-upload-per-record images work fine). Phase 20's
own deferred items (a `customer_id` column on `analytics_events`,
real-time visitor counts, third-party pixel integrations, IP geolocation)
are each blocked on real infra this environment doesn't have, same as
Phase 19's remaining pieces — not pick-able today either.

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
