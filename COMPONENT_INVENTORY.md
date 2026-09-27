# COMPONENT INVENTORY

Legend: ✅ built this session · ⏳ planned (built when its consuming
phase lands — see `DEVELOPMENT_ROADMAP.md`).

## Primitives (`frontend/src/components/ui/`)

| Component | Status | Notes |
|---|---|---|
| Button | ✅ | variants: primary/secondary/outline/ghost/destructive/link; sizes sm/md/lg; loading state |
| Input | ✅ | text/email/password/number; error state |
| Label | ✅ | |
| Card | ✅ | header/content/footer slots |
| Badge | ✅ | status color variants (success/warning/danger/info/neutral) |
| Avatar | ✅ | image + initials fallback |
| Separator | ✅ | |
| Skeleton | ✅ | used for dashboard/table loading states |
| Alert | ✅ | inline error/success/info banners |
| Dropdown Menu | ✅ | used in topbar (profile, theme) |
| Sheet (drawer) | ✅ | mobile sidebar |
| Select | ✅ | Radix-based; store currency/locale/status, user status, role assignment, product category/brand/status, warehouse pickers, stock adjustment direction, supplier/warehouse pickers on purchase orders |
| Textarea | ✅ | category/brand/product descriptions, SEO meta description, warehouse address, transfer note, supplier address, PO notes/receipt note |
| Checkbox | ✅ | Radix-based; role permission matrix, user role assignment, product track-stock/featured flags, low-stock-only filter |
| Radio | ⏳ | Not needed yet |
| Switch | ⏳ | Not needed yet — every boolean setting so far reads fine as a Checkbox or Select |
| Tooltip | ⏳ | Not needed yet |
| Modal/Dialog | ✅ | centered dialog (distinct from Sheet); delete confirmations for users/roles/categories/brands/products/warehouses/suppliers, stock adjustment form, purchase-order cancel confirmation |
| Tabs | ✅ | Two flavors: a route-driven Link sub-nav (Settings, Catalog) and a client-state tab switcher inside the product form (General/Pricing/Media/SEO) — plain buttons + conditional rendering, not the Radix Tabs primitive, since neither use case needed its accessibility semantics beyond what a nav/button already gives |
| Accordion | ⏳ | Phase 15 (SEO analysis groups) — not needed by anything shipped yet |
| Table / DataTable | ✅ | Server-paginated table w/ loading/empty states. Built as a small dependency-free component rather than on TanStack Table — the installed major version (v9) shipped a completely different, unfamiliar API; safer to write ~100 lines directly than guess at an API with no reliable reference. Now also backs Warehouses, Stock Levels, Movements, Transfers, Suppliers, and Purchase Orders. |
| Pagination | ✅ | ships with DataTable (prev/next, server-driven) |
| Breadcrumb | ✅ | topbar |
| Toast | ✅ | global toaster for mutations |
| Timeline | ⏳ | Phase 8 (order timeline) |
| Chart | ⏳ | Phase 11 (dashboard KPI charts, Recharts) |
| Date Picker | ⏳ | Not needed yet — nothing shipped so far has a date field (products have no scheduled-publish date in Wave 1); Phase 8 (orders) is the next likely consumer |
| Command Palette | ⏳ | Products is now a searchable resource, but the palette itself is still unbuilt — next natural pickup |
| File Upload | ✅ | Two components: `ImageUploadField` (single image — category/brand) and `ProductImageGallery` (multi-image with primary selection, delete, drag-free grid). No shared/reusable media library yet (Phase 5 Wave 2) — each upload is stored directly against its owning record. |
| Rich Editor (TipTap) | ⏳ | Phase 14 (blog) |
| Stat Card | ✅ | dashboard KPI cards; supports a `tone="danger"` accent, added for the Phase 6 low-stock-alerts card |
| Empty State | ✅ | generic empty-state component (icon/title/description/CTA) |

## Layout Components (`frontend/src/components/layout/`)

| Component | Status | Notes |
|---|---|---|
| AdminSidebar | ✅ | collapsible, responsive drawer below 768px |
| AdminTopbar | ✅ | breadcrumb, theme toggle, profile menu, notifications placeholder (disabled — no notifications backend yet, so the bell is present but intentionally shows an empty state rather than fake data) |
| AdminShell | ✅ | composes sidebar+topbar+content, persists sidebar collapsed state |
| StorefrontHeader/Footer | ⏳ | Phase 16 |
| BuilderCanvas / BuilderPanel | ⏳ | Phase 13 |

## Charts (`frontend/src/components/charts/`)

All ⏳ — Phase 11 (Dashboard) is the first consumer.

## Rule Followed

No component above marked ⏳ is referenced anywhere in the shipped
code this session. A component only appears in the tree once the
screen using it has real data behind it (spec rule 178: no fake
functionality, no dead buttons).
