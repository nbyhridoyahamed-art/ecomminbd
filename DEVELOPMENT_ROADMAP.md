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
| 5 | Catalog | ✅ Wave 1 done (variants/attributes/bundles/reviews/bulk import-export/media library deferred — see note) | Yes — categories (hierarchy), brands, simple products w/ pricing/SEO/images, full admin UI |
| 6 | Inventory | ✅ Wave 1 done (order reservations/variant-level stock deferred — see note) | Yes — stock levels per warehouse, movements ledger, adjustments, transfers; plus the Warehouses admin UI (a Phase 4 gap this closed) |
| 7 | Purchasing | ✅ Wave 1 done (purchase returns/supplier ledger/PO approval workflow deferred — see note) | Yes — suppliers, purchase orders (draft→ordered→received state machine), receipts that drive real stock movements |
| 8 | Orders | ✅ Wave 1 done (payments ledger/coupons/returns/order-edit UI deferred — see note) | Yes — customers + saved addresses, orders (pending→processing→shipped→delivered/cancelled state machine) that reserve and then fulfil real stock |
| 9 | Delivery | ⏳ Not started | No |
| 10 | Returns | ⏳ Not started | No |
| 11 | Admin Dashboard (full KPIs/charts) | 🟡 Shell only | Yes (shell) |
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
section 2): order *returns* movements (needs Phase 10), variant-level
stock (needs Phase 5 Wave 2), a pending/in-transit/received transfer
approval workflow, and a `stock_adjustments` header table for grouping a
stocktake's many adjustments. None of these has a real consumer yet —
spec rule 178. (Purchase-receipt-driven movements and order
reservation/fulfillment/cancellation movements, the two Wave 2 items
this note used to list, are no longer deferred — Phase 7 and Phase 8
built them respectively.)

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
a real payments/COD-reconciliation ledger, coupons/discount codes
(`discount_amount` is a plain manual entry in Wave 1), order
returns/exchanges (needs Phase 10), and a dedicated order-edit-while-
pending UI (the endpoint exists and is tested, but no page consumes it
yet — same as purchase-order editing).

## Next Session Should Start With

Phase 9: Delivery (couriers, shipments, delivery zones, COD
settlement) — the natural next unblock, since Phase 8 orders now exist
for shipments to reference and COD settlement to reconcile against.
Phase 5 Wave 2 (variants/attributes, bundles, bulk import/export, media
library) is the other reasonable starting point — see its scope note
above. Follow the phase order above; do not skip ahead to CMS/SEO/
Storefront before Delivery exists, since those phases both link to and
depend on catalog + inventory + order + delivery data.

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
