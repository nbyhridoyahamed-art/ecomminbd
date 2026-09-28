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
| Select | ✅ | Radix-based; store currency/locale/status, user status, role assignment, product category/brand/status, warehouse pickers, stock adjustment direction, supplier/warehouse pickers on purchase orders, customer/warehouse/payment-method/saved-address pickers on orders, cascading BD division/district/upazila pickers (customer addresses + order shipping), courier pickers on shipments/COD settlements, variant pickers on order/purchase-order/stock-transfer line items and the variant stock-adjustment dialog's warehouse picker |
| Textarea | ✅ | category/brand/product descriptions, SEO meta description, warehouse address, transfer note, supplier address, PO notes/receipt note, order notes, shipment delivered/failed/returned notes, COD settlement note, return request reason, return reject/receive notes |
| Checkbox | ✅ | Radix-based; role permission matrix, user role assignment, product track-stock/featured flags, low-stock-only filter, customer address default flag, COD settlement shipment picker, per-item restock decision on return receive |
| Radio | ⏳ | Not needed yet |
| Switch | ⏳ | Not needed yet — every boolean setting so far reads fine as a Checkbox or Select |
| Tooltip | ⏳ | Not needed yet |
| Modal/Dialog | ✅ | centered dialog (distinct from Sheet); delete confirmations for users/roles/categories/brands/products/warehouses/suppliers/customers/customer addresses, stock adjustment form, purchase-order cancel confirmation, customer address add/edit form, order cancel confirmation, shipment delivered/failed/returned-to-seller confirmations (COD capture on delivery), return reject/receive/refund confirmations (receive has a per-item restock checklist, refund has a suggested-amount-prefilled input) |
| Tabs | ✅ | Two flavors: a route-driven Link sub-nav (Settings, Catalog — now Products/Categories/Brands/Attributes, Inventory, Purchasing, Orders/Customers/Returns, Delivery) and a client-state tab switcher inside the product form (General/Pricing/Variants*/Media/SEO — *shown only when type is Variable) — plain buttons + conditional rendering, not the Radix Tabs primitive, since neither use case needed its accessibility semantics beyond what a nav/button already gives |
| Accordion | ⏳ | Phase 15 (SEO analysis groups) — not needed by anything shipped yet |
| Table / DataTable | ✅ | Server-paginated table w/ loading/empty states. Built as a small dependency-free component rather than on TanStack Table — the installed major version (v9) shipped a completely different, unfamiliar API; safer to write ~100 lines directly than guess at an API with no reliable reference. Now also backs Warehouses, Stock Levels, Movements, Transfers, Suppliers, Purchase Orders, Customers, Orders, Couriers, Shipments, COD Settlements, Returns, and Attributes. |
| Pagination | ✅ | ships with DataTable (prev/next, server-driven) |
| Breadcrumb | ✅ | topbar |
| Toast | ✅ | global toaster for mutations |
| Timeline | ✅ | order, shipment, and return status history — a plain `<ol>` of status badges + timestamp/actor, same "plain markup over a new primitive" call as Tabs; now has three consumers with the identical shape (order show page, shipment show page, return show page) but still duplicated inline rather than extracted, since each instance is small and none has diverged — promote to a real shared component the next time it actually needs to change in more than one place at once |
| Chart | ✅ | Recharts (v3, React 19-compatible); see the Charts section below — no generic `ui/chart.tsx` wrapper, each chart is its own focused component under `components/charts/` since the two built so far (trend vs. breakdown) have different enough shapes that a shared abstraction would be premature |
| Date Picker | ⏳ | Not needed yet — nothing shipped so far has a date field (products have no scheduled-publish date in Wave 1; orders use server-set timestamps, not a user-picked date) |
| Command Palette | ⏳ | Products is now a searchable resource, but the palette itself is still unbuilt — next natural pickup |
| File Upload | ✅ | Two components: `ImageUploadField` (single image — category/brand) and `ProductImageGallery` (multi-image with primary selection, delete, drag-free grid). No shared/reusable media library yet (Phase 5 Wave 2b) — each upload is stored directly against its owning record. |
| AttributeForm / AttributeValuesManager | ✅ | Phase 5 Wave 2a — `AttributeForm` (name/slug, mirrors `CategoryForm`'s shape minus the hierarchy) and `AttributeValuesManager` (inline add/edit/delete for one attribute's values; no drag-reorder — `sort_order` is just assignment order, reordering wasn't worth a drag-and-drop dependency for Wave 2a's scope). |
| VariantsManager | ✅ | Phase 5 Wave 2a — attribute-value checkboxes (pre-checked from a product's existing variants) + a "Generate variants" action calling the cartesian-product endpoint, plus a per-variant row (SKU/price override/status, inline Save when dirty, Delete). Lives under `components/catalog/`, reused by the product form's Variants tab. Since the variant-aware retrofit, each row also shows a Stock column (`stock_summary` total available / on-hand / reserved) and an "Adjust stock" action opening `VariantStockAdjustmentDialog` — this is the only place variant-level stock is visible, since the global Stock Levels list stays product-centric (see `DATABASE_DESIGN.md` section 1c). |
| VariantPicker / VariantStockAdjustmentDialog | ✅ | Added for the variant-aware retrofit. `VariantPicker` renders nothing for a simple product (or one with no generated variants yet) and otherwise a `Select` of that product's variants (attribute values joined with " / ", plus SKU) — reused identically by the Order, Purchase Order, and Stock Transfer forms' line-item rows. `VariantStockAdjustmentDialog` is `StockAdjustmentDialog`'s counterpart for a specific product+variant rather than a `StockLevel` row: it adds its own warehouse picker (the Variants tab has no ambient warehouse context, unlike the already-warehouse-scoped Stock Levels page) and otherwise reuses the same `useCreateStockAdjustment` mutation. Both live under `components/catalog/`. |
| Rich Editor (TipTap) | ⏳ | Phase 14 (blog) |
| Stat Card | ✅ | dashboard KPI cards; supports a `tone="danger"` accent, added for the Phase 6 low-stock-alerts card; since Phase 11 every card on the dashboard is filtered by the permission that backs its number before rendering, rather than showing 0 for data the viewer can't see |
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

| Component | Status | Notes |
|---|---|---|
| SalesTrendChart | ✅ | Recharts `ComposedChart` — an `Area` for revenue (left axis) + a dashed `Line` for order count (right axis) over the trailing N days, colored from CSS custom properties (`var(--color-primary)` etc.) so it repaints for dark mode automatically, same mechanism as every other themed component |
| OrderStatusChart | ✅ | Recharts `BarChart`, one bar per order status, each `Cell` colored to match the same status badge variant used everywhere else (pending/processing/shipped/delivered/cancelled) |

Both are the first Phase 11 consumers of Recharts. A generic reusable
`Chart` wrapper wasn't built — see the Primitives table above for why.

## Rule Followed

No component above marked ⏳ is referenced anywhere in the shipped
code this session. A component only appears in the tree once the
screen using it has real data behind it (spec rule 178: no fake
functionality, no dead buttons).
