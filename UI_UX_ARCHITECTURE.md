# UI/UX ARCHITECTURE

## 1. Three Experiences, One Design Language

1. **Admin (ERP)** — dense, data-first, keyboard-friendly. Sidebar (260px,
   collapsible to 72px) + topbar + main content (spec section 40–42).
2. **Storefront** — customer-facing, marketing-friendly, mobile-first,
   built from the homepage builder's block registry (spec section 85–90).
3. **Customer Account** — a lighter authenticated shell under
   `/account/*`, reusing storefront chrome, not the admin sidebar.

All three share the same design tokens (`DESIGN_SYSTEM.md`) so dark
mode, typography, and color are consistent, but layout primitives differ
per experience.

## 2. Admin Shell (implemented this session)

```
┌─────────────────────────────────────────────────────────┐
│ Topbar: breadcrumb · global search · quick create · ⌘K  │
│         notifications · language · theme · profile      │
├───────────┬───────────────────────────────────────────────┤
│  Sidebar  │                                               │
│  (260px)  │              Main content                    │
│  logo     │              (resizes automatically)          │
│  store    │                                               │
│  selector │                                               │
│  nav tree │                                               │
│  ...      │                                               │
│  help/    │                                               │
│  settings │                                               │
└───────────┴───────────────────────────────────────────────┘
```

- Sidebar collapses to a 72px icon rail; state persists per-user
  (server-side preference once `user_preferences` exists; `localStorage`
  fallback for the immediate session).
- Nav tree in this session only renders sections that are actually
  functional (Dashboard). Every other section from spec section 41 is
  documented in `PAGE_INVENTORY.md` and added to the nav the moment its
  phase ships — no dead links (spec rule 178).
- Responsive: `<768px` the sidebar becomes a Sheet/drawer (spec section
  171); tables degrade to stacked cards below `tablet`.

## 3. Global Search / Command Palette (future phase)

`⌘K` / `/` opens a command palette (spec section 44). `products` is now
a searchable resource (Phase 5), but the palette itself is still
unbuilt — a natural next pickup. Still deferred for orders/customers
until those phases ship; building it against zero real data would be
exactly the "fake functionality" spec rule 178 forbids.

## 4. Storefront Shell (future phase)

Sticky header, mega menu, cart drawer, mobile bottom nav — built once
the catalog/cart phases exist. Documented in `PAGE_INVENTORY.md` for
sequencing.

## 5. Forms

React Hook Form + Zod on every form; Zod schema mirrors (but never
replaces) backend `FormRequest` validation — frontend validation is UX
sugar, backend is authoritative (spec rule 135/182).

## 6. Tables

A single reusable `DataTable` component (`components/ui/data-table.tsx`)
handles server-driven pagination, loading skeletons, and empty states,
reused by every list screen (Users, Roles, Categories, Brands, Products,
Warehouses, Stock Levels, Movements, Transfers, Suppliers, Purchase
Orders, Customers, Orders, Couriers, Shipments, COD Settlements, Returns,
Attributes today; later phases' resources as they ship). It's a small dependency-free
implementation rather than
built on TanStack Table — the version available at build time (v9) ships
a materially different API from what's documented/commonly known, so
writing the ~100 lines directly was safer than guessing at an
unfamiliar surface. Column sorting, visibility toggles, and row
selection/bulk actions are not implemented yet; they're added when a
screen actually needs them (spec rule 136 on pagination is satisfied
today via server-side `page`/`per_page`).

## 6b. Data Visualization

Recharts (v3, React 19-compatible) backs the two charts the dashboard
ships in Phase 11 (`SalesTrendChart`, `OrderStatusChart` — see
`COMPONENT_INVENTORY.md`). Every stroke/fill is a CSS custom property
(`var(--color-primary)`, `var(--color-success)`, …) rather than a baked-in
hex value, so a chart repaints correctly under the dark-mode class toggle
the same way every other themed component does (spec section 45/46) —
no chart-specific theme logic. Axes, grid lines, and tooltips use the
muted/border/surface tokens so a chart reads as part of the same design
language as its surrounding Card, not a foreign widget dropped in.

## 7. UI States Discipline

Every screen this session ships (login, dashboard, settings) implements
all of: loading (skeleton), empty, error, success, disabled, and — where
relevant — permission-denied. This is a hard rule for every future
phase too (spec rule 181): no "happy path only" screens.

## 8. Localization & RTL-readiness

English/Bangla via `next-intl`-style JSON translation keys (no
hard-coded UI strings in components — spec section 12). Bangla line-
height handled at the token level (`DESIGN_SYSTEM.md` §3), not per
component.

## 9. What's Implemented So Far

Updated through Phase 15 (SEO) — this section had been frozen since the
Phase 3/4 admin-shell-and-Settings snapshot above and had drifted well
behind reality; see `PAGE_INVENTORY.md`/`COMPONENT_INVENTORY.md` for the
authoritative per-screen/per-component detail, this is just the summary.

Admin shell (sidebar + topbar, collapse/expand, dark mode toggle,
responsive drawer, permission-gated nav that now covers every shipped
section: Dashboard, Catalog, Inventory, Purchasing, Orders, Delivery,
Content incl. SEO, Reports, Settings), full Settings, and a complete
data-management admin covering Catalog (incl. variants/bundles/CSV
import-export), Inventory, Purchasing (incl. returns), Orders, Delivery
(incl. COD settlement), Reporting (sales/product-performance/low-stock,
each with CSV+PDF export), a full drag-and-drop Homepage Builder, a Blog
CMS, and an SEO suite (per-entity metadata, redirects, templates). The
Storefront (public catalog browsing + guest COD checkout) and a
Customer Account shell (`/account/*`) are both live, sharing this same
design language rather than the admin sidebar shell (section 1 above).

A **verbatim copy of the full master UI/UX spec** (colors, dark-mode
palette, typography scale, breakpoints, component/animation/
accessibility rules) was re-supplied mid-Phase-20 as a sanity check
against `DESIGN_SYSTEM.md`/this doc: every token, hex value, and
section-number citation already in `tokens.css`/`theme.css` matches it
exactly, confirming the design layer hasn't drifted from spec across 19
phases of feature work. No token or component changes were needed as a
result — Phase 20's new screens are built on the same
already-conformant primitives, not a parallel or upgraded system.

Command palette (`⌘K`/`/`, spec section 15/44) is the one piece of the
original admin-shell sketch still genuinely unbuilt. It's no longer
blocked on missing data — products/orders/customers/pages/blog/
categories/brands all exist with real records now — but building a
cross-entity global-search index is a substantial, self-contained
feature in its own right (not a Phase 20 Analytics concern), so it stays
a flagged, ready-to-pick item rather than something bolted on here.
