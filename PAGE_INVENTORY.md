# PAGE / ROUTE INVENTORY

Legend: ✅ built & functional this session · ⏳ planned, route does not
exist yet (per spec rule 178, we do not create routes/pages ahead of
their backing functionality — no dead pages).

## Admin (`frontend/src/app/(admin)/`)

| Route | Status | Phase |
|---|---|---|
| `/login` | ✅ | 3 (Auth) |
| `/dashboard` | ✅ | 3/11 (real KPI stat cards, each permission-gated so a card never shows a misleading 0; sales-trend + order-status-breakdown Recharts visuals; recent-orders widget) |
| `/catalog/products` | ✅ | 5 (search, filter by category/brand/status, pagination); 5 Wave 2b added Export (respects the current filters) and Import (CSV upload dialog with a per-row results summary) |
| `/catalog/products/new`, `/catalog/products/[id]` | ✅ | 5/5-Wave2a/5-Wave2c (tabbed form: General/Pricing/Variants*/Components†/Media/SEO — *Variants tab only shown once type is set to Variable, †Components tab only shown once type is set to Bundle, showing a product+variant+quantity picker, the bundle's derived availability by warehouse, and per-row edit/remove; multi-image upload with primary selection) |
| `/catalog/categories` | ✅ | 5 (hierarchical list, unlimited nesting, cycle-safe) |
| `/catalog/categories/new`, `/catalog/categories/[id]` | ✅ | 5 |
| `/catalog/brands` | ✅ | 5 (list, search, pagination) |
| `/catalog/brands/new`, `/catalog/brands/[id]` | ✅ | 5 |
| `/catalog/attributes` | ✅ | 5 Wave 2a (list showing each attribute's values inline) |
| `/catalog/attributes/new`, `/catalog/attributes/[id]` | ✅ | 5 Wave 2a (edit page also manages the attribute's values inline: add/edit/delete, blocked while a value is still used by a variant) |
| `/catalog/products/[id]` (Variants tab: Stock column + "Adjust stock" action) | ✅ | variant-aware retrofit — per-variant stock summary and adjustment entry point, since the global `/inventory/stock-levels` list stays product-centric (see `DATABASE_DESIGN.md` section 1c) |
| `/orders/orders/new`, `/purchasing/purchase-orders/new`, `/inventory/transfers/new` (variant picker on line items) | ✅ | variant-aware retrofit — a variable product's row grows a second `Select` for its variant (required client-side before submit, though the API itself still accepts a bare product); `order_items`/`purchase_order_items`/`stock_transfer_items`/`stock_levels`/`stock_movements` are now all variant-aware, see `DATABASE_DESIGN.md` section 1i |
| `/catalog/products/[id]` (Components tab, bundle/combo type) | ✅ | 5 Wave 2c — component picker (product + optional variant + quantity), derived per-warehouse availability, editable quantity, remove; `/orders/orders/[id]` shows a bundle line item's resolved component breakdown inline for packing; `/purchasing/purchase-orders/new`, `/inventory/transfers/new` exclude bundles from their product picker (a bundle is never purchased or transferred directly — see `DATABASE_DESIGN.md` section 1l) |
| `/catalog/media` (reusable, browsable media library) | ⏳ | 5 Wave 2b — product/category/brand images upload directly today, no shared library yet |
| `/inventory` | ✅ | 6 (redirects to Stock Levels) |
| `/inventory/stock-levels` | ✅ | 6 (per-warehouse on-hand quantity, low-stock filter, search, adjust dialog) |
| `/inventory/movements` | ✅ | 6 (read-only ledger, filter by warehouse/type) |
| `/inventory/transfers` | ✅ | 6 |
| `/inventory/transfers/new`, `/inventory/transfers/[id]` | ✅ | 6 (multi-item warehouse-to-warehouse transfer) |
| `/inventory/warehouses` | ✅ | 6 (list, create, edit — backend model existed since Phase 4, admin UI was the Phase 6 gap this closes) |
| `/inventory/warehouses/new`, `/inventory/warehouses/[id]` | ✅ | 6 |
| `/inventory/stock-levels` (Reserved/Available columns) | ✅ | 6/8 (stock reservations tied to orders — Phase 8 built the consumer; each row now sums a variable product's stock across all its variants rather than a single row per variant, see `DATABASE_DESIGN.md` section 1c) |
| `/purchasing` | ✅ | 7 (redirects to Purchase Orders) |
| `/purchasing/purchase-orders` | ✅ | 7 (status/supplier filters, total shown per order) |
| `/purchasing/purchase-orders/new` | ✅ | 7 (supplier/warehouse + line-item builder with unit cost) |
| `/purchasing/purchase-orders/[id]` | ✅ | 7 (items w/ received-so-far, place/cancel actions, record-receipt form, receipt history, returns summary + request-a-return form — drives real `stock_movements`) |
| `/purchasing/purchase-returns` | ✅ | 7 Wave 2a (list, filter by status) |
| `/purchasing/purchase-returns/[id]` | ✅ | 7 Wave 2a (items, status history, approve/reject/ship-back/credit actions) |
| `/purchasing/suppliers` | ✅ | 7 (list, search, pagination) |
| `/purchasing/suppliers/new`, `/purchasing/suppliers/[id]` | ✅ | 7 |
| `/purchasing` (supplier ledger/payment terms, PO approval workflow, reorder suggestions) | ⏳ | 7 Wave 2 remaining items — no real consumer yet, see `DATABASE_DESIGN.md` |
| `/orders` | ✅ | 8 (redirects to Orders) |
| `/orders/orders` | ✅ | 8 (status/customer filters, total shown per order); 16 added a Source column (Admin/Storefront badge, once guest checkout existed to actually produce the latter) |
| `/orders/orders/new` | ✅ | 8 (customer/warehouse/payment method + saved-or-manual shipping address + line-item builder) |
| `/orders/orders/[id]` | ✅ | 8/9/10/16 (items, shipping address, status badge, Process/Ship/Deliver/Cancel actions gated by status+permission, status history timeline — ship drives real `stock_movements`; courier-assignment + shipment status card from Phase 9; Returns list + "Request a return" form, shown once `delivered`, from Phase 10; Source badge next to the status badge from Phase 16) |
| `/orders/customers` | ✅ | 8 (list, search, pagination); 17 added an Account column (Claimed/Guest badge, once customers could actually claim one) |
| `/orders/customers/new` | ✅ | 8 |
| `/orders/customers/[id]` | ✅ | 8 (edit customer + inline saved-address manager, no separate address pages); 17 added the same Claimed/Guest badge next to the page title |
| `/orders` (payments/refunds, order edit-while-pending UI, guest checkout) | ⏳ | 8 Wave 2 — no real consumer yet, see `DATABASE_DESIGN.md` |
| `/delivery` | ✅ | 9 (redirects to Shipments) |
| `/delivery/shipments` | ✅ | 9 (status/courier filters) |
| `/delivery/shipments/[id]` | ✅ | 9 (status card w/ Picked up/In transit/Delivered/Failed/Returned actions, COD capture dialog, order summary, status history timeline) |
| `/delivery/couriers` | ✅ | 9 (list, search, pagination) |
| `/delivery/couriers/new`, `/delivery/couriers/[id]` | ✅ | 9 |
| `/delivery/cod-settlements` | ✅ | 9 (filter by courier) |
| `/delivery/cod-settlements/new` | ✅ | 9 (pick courier, select unsettled COD shipments, enter amount received) |
| `/delivery/cod-settlements/[id]` | ✅ | 9 (expected vs. received, discrepancy, covered shipments) |
| `/delivery` (delivery zones/rates, multi-shipment orders) | ⏳ | 9 Wave 2 — no real consumer yet, see `DATABASE_DESIGN.md` |
| `/orders/returns` | ✅ | 10 (status filter, tab under Orders alongside Orders/Customers) |
| `/orders/returns/[id]` | ✅ | 10 (status card w/ Approve/Reject/Receive/Refund actions — receive has a per-item restock checklist, refund has a suggested-amount-prefilled input — items table, order summary, status history timeline) |
| `/orders` (exchanges, store credit, cross-return partial-refund reconciliation) | ⏳ | 10 Wave 2 — no real consumer yet, see `DATABASE_DESIGN.md` |
| `/dashboard` (custom date-range picker, per-warehouse/per-courier breakdowns, low-stock-products widget w/ names, export) | ⏳ | 11 Wave 2 — no real consumer yet, see `DATABASE_DESIGN.md`; full reporting suite is Phase 18 |
| `/reports` | ✅ | 18 (redirects to Sales) |
| `/reports/sales` | ✅ | 18 (date range + warehouse + granularity filters, revenue/orders/AOV stat cards each with a vs.-previous-period trend badge, reused `SalesTrendChart`, by-payment-method and by-courier tables, Export dropdown: CSV/PDF) |
| `/reports/products` | ✅ | 18 (date range + warehouse filters, units-sold/revenue table ranked by revenue, variant sales rolled up to the parent product, Export dropdown: CSV/PDF) |
| `/reports/low-stock` | ✅ | 18 (cross-warehouse on-hand/reserved/available vs. threshold, Export dropdown: CSV/PDF) |
| `/content/pages` | ✅ | 12 — a small, deliberate path change beyond this table's original `/website/pages` sketch, grouping under a new "Content" nav section rather than a "Website" one (list w/ Title/Slug/Status, delete w/ confirmation) |
| `/content/pages/new`, `/content/pages/[id]` | ✅ | 12 (title/slug w/ auto-fill, plain-text content, draft/published status, optional meta title/description) |
| `/content/homepage` | ✅ | 13 — same deliberate `/content/*` grouping as Pages above, not this table's original `/website/homepage` sketch; the tab-nav infrastructure both now share is documented in `COMPONENT_INVENTORY.md`. A real live-WYSIWYG drag-and-drop builder: left sidebar (Blocks palette/Layers/Saved sections), center canvas (dnd-kit sortable, renders every block through the exact same component the live storefront uses, via a new `/homepage-blocks/preview` endpoint), right panel (Content/Design/Layout/Animation/Advanced/SEO tabs, local autosave + undo/redo, a revision-history sheet with restore). All ~30 block types have a real Content-tab editor — see `COMPONENT_INVENTORY.md`. Gated on a granular `builder.view`/`builder.edit`/`builder.publish` split, not one umbrella permission like Pages. |
| `/website/navigation` | ⏳ | 12 Wave 2 — no `navigation_menus`/`navigation_items` schema yet, see `DATABASE_DESIGN.md` |
| `/website/media` | ⏳ | 12 Wave 2 — same reusable media library gap Catalog Wave 2 lists |
| `/website/theme` | ⏳ | 12 Wave 2 |
| `/content/blog/posts` | ✅ | 14 — a small, deliberate path change beyond this table's original `/blog` sketch, following Pages/Homepage's own `/content/*` grouping; the first Content sub-resource to get its own nested tab-nav (Posts/Categories/Tags, documented in `COMPONENT_INVENTORY.md`) |
| `/content/blog/posts/new`, `/content/blog/posts/[id]` | ✅ | 14 (title/slug w/ auto-fill, category `Select`, tag checkboxes, TipTap body, excerpt w/ auto-fallback note, featured image URL, draft/published status + a `datetime-local` schedule field, optional SEO section, a Version History sheet on the edit page) |
| `/content/blog/categories`, `.../new`, `.../[id]` | ✅ | 14 (name/slug w/ auto-fill, description; list shows each category's post count) |
| `/content/blog/tags`, `.../new`, `.../[id]` | ✅ | 14 (name/slug w/ auto-fill; list shows each tag's post count) |
| `/seo` | ⏳ | 15 |
| `/settings/general` | ✅ | 4 (store name/domain/currency/timezone/locale/status) |
| `/settings/users` | ✅ | 4 (list, search, create, edit, role assignment, delete w/ confirmation) |
| `/settings/users/new`, `/settings/users/[id]` | ✅ | 4 |
| `/settings/roles` | ✅ | 4 (list, create, edit grouped permission matrix, delete w/ confirmation; Super Admin/Store Owner locked) |
| `/settings/roles/new`, `/settings/roles/[id]` | ✅ | 4 |
| `/settings/localization` | ⏳ | 4 (BD divisions/districts seeded; managing them via UI is a later increment — not needed until Phase 6+ warehouse/address forms) |

## Storefront (`frontend/src/app/(storefront)/`)

| Route | Status | Phase |
|---|---|---|
| `/` | ✅ | 16/13 — fully block-driven since Phase 13: fetches every active block via `GET storefront/homepage-blocks` and renders each through `HomepageBlockRenderer`, the same registry component the admin builder's canvas uses. No hardcoded sections of its own left (the original Phase 16 hardcoded hero/category-grid/featured-products JSX this table used to describe is gone). |
| `/products` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch, which had no all-products/search index; search/category/brand/featured filters and sort all live in the URL query string so a filtered link is shareable |
| `/products/[slug]` | ✅ | 16 (image gallery, variant picker gating "Add to Cart" until a full attribute selection resolves to a real variant, bundle component list + derived availability, out-of-stock state) |
| `/category/[slug]` | ✅ | 16 (category + its subcategories as quick links, paginated active products) |
| `/brand/[slug]` | ✅ | 16 |
| `/brands` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch (a brand index page for the header/footer's "Brands" link to point at, mirroring `/products`' justification) |
| `/blog` | ✅ | 14/16 (paginated, `?search=`) |
| `/blog/[slug]` | ✅ | 14/16 (body rendered from real HTML via `dangerouslySetInnerHTML`, same trust model as the homepage builder's Rich Text/Custom HTML blocks; clickable category/tags; up to 3 related posts sharing its category) |
| `/blog/category/[slug]`, `/blog/tag/[slug]` | ✅ | 14/16 (paginated archives, mirroring `/category/[slug]`'s shape) |
| `/pages/[slug]` | ✅ | 12/16 (title + plain-text content rendered `whitespace-pre-line`, matching the product description convention; draft pages and pages from another store both 404) |
| `/cart` | ✅ | 16 (line items w/ quantity stepper, subtotal — reused `CartLineItem` also backs the header's cart drawer) |
| `/checkout` | ✅ | 16 (guest-only: name/phone/email, shipping address w/ live BD division/district/upazila cascade, order summary; payment method is a fixed "Cash on Delivery" label, not a selector — Wave 1 has only the one method, so a picker would be a fake choice) |
| `/order-confirmation/[uuid]` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch, which had a `/checkout` row but nowhere named where a successful checkout lands; looked up by uuid only, same public-receipt contract as the API route |

## Customer Account (`frontend/src/app/account/`)

Lives at the real `account/` segment, not a parenthesized group as this
table's original sketch implied — `/account/login` and `/account/register`
must stay reachable while signed out, and a parenthesized group can't gate
some of its own pages but not others under one layout. The outer
`account/layout.tsx` carries no auth gate (just storefront chrome —
header/footer/cart drawer, reused as-is); a nested `account/(dashboard)/`
route group carries its own gated layout (redirects to `/account/login`,
tab nav) around everything that actually needs a signed-in customer.

| Route | Status | Phase |
|---|---|---|
| `/account/login` | ✅ | 17 |
| `/account/register` | ✅ | 17 — copy calls out the claim mechanic directly ("Already ordered as a guest? Use the same phone number to link your past orders automatically") |
| `/account` | ✅ | 17 (dashboard overview: order count, a Manage-addresses link, sign out, and up to 3 most recent orders) |
| `/account/orders`, `/account/orders/[uuid]` | ✅ | 17 — `[uuid]`, not `[id]` as this table's original sketch had it, matching the storefront's own no-sequential-id convention (`order-confirmation/[uuid]`); the detail page adds a status-history timeline the storefront's public receipt view doesn't have |
| `/account/addresses` | ✅ | 17 (reuses the admin's own `CustomerAddressForm` directly, inside the same add/edit `Dialog` + delete-confirmation pattern as the admin customer detail page's address manager — its location pickers needed repointing to a public endpoint for this signed-in-customer context to work at all; see `DEVELOPMENT_ROADMAP.md`'s Phase 17 Wave 1 scope note) |
| `/account/profile` | ✅ | 17 (name/email only; phone is the login identifier and isn't editable here) |
| `/account/wishlist` | ⏳ | 17 Wave 2 — no backing schema or consumer anywhere yet (see `DATABASE_DESIGN.md` section 2) |
| `/account/returns` | ⏳ | 17 Wave 2 — return creation is still the staff-only admin flow (Phase 10); a customer-initiated version reuses the same `returns` table, just needs its own `Auth::id()`-scoped creation endpoint |

## API Routes (`backend/routes/api.php`)

| Route | Status |
|---|---|
| `POST /api/v1/auth/register` | ✅ |
| `POST /api/v1/auth/login` | ✅ |
| `POST /api/v1/auth/logout` | ✅ |
| `GET /api/v1/auth/me` | ✅ |
| `POST /api/v1/auth/forgot-password` | ✅ |
| `POST /api/v1/auth/reset-password` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/stores` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/warehouses` | ✅ |
| `GET /api/v1/locations/divisions` `/districts` `/upazilas` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/users` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/roles`, `GET /api/v1/permissions` | ✅ |
| `GET /api/v1/currencies` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/categories` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/brands` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/products` | ✅ |
| `POST /api/v1/products/{id}/images`, `DELETE .../images/{id}`, `POST .../images/{id}/primary` | ✅ |
| `POST /api/v1/uploads` (generic category/brand image upload) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/product-attributes` | ✅ |
| `POST/PUT/DELETE /api/v1/product-attributes/{id}/values(/{value})` | ✅ |
| `POST /api/v1/products/{id}/variants/generate`, `PUT/DELETE .../variants/{variantId}` | ✅ |
| `GET /api/v1/products/export`, `POST /api/v1/products/import` | ✅ |
| `GET /api/v1/stock-levels`, `GET .../low-stock-count` | ✅ |
| `GET /api/v1/stock-movements` | ✅ |
| `POST /api/v1/stock-adjustments` | ✅ |
| `GET/POST /api/v1/stock-transfers`, `GET .../{id}` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/suppliers` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/purchase-orders` | ✅ |
| `POST /api/v1/purchase-orders/{id}/place`, `.../cancel` | ✅ |
| `POST /api/v1/purchase-orders/{id}/receipts` (drives `stock_movements`/`stock_levels`) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/customers` | ✅ |
| `POST/PUT/DELETE /api/v1/customers/{id}/addresses(/{address})` | ✅ |
| `GET/POST/PUT /api/v1/orders`, `GET .../{id}` | ✅ |
| `POST /api/v1/orders/{id}/process`, `.../ship` (drives `stock_movements`/`stock_levels`), `.../deliver`, `.../cancel` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/couriers` | ✅ |
| `POST /api/v1/orders/{id}/shipments`, `GET /api/v1/shipments`, `GET .../{id}` | ✅ |
| `POST /api/v1/shipments/{id}/picked-up`, `.../in-transit`, `.../delivered` (drives `stock_movements`-adjacent `orders.status`/`payment_status` for COD), `.../failed`, `.../returned` (drives `stock_movements`/`stock_levels` since Phase 10) | ✅ |
| `GET/POST /api/v1/cod-settlements`, `GET .../{id}` | ✅ |
| `POST /api/v1/orders/{id}/returns`, `GET /api/v1/returns`, `GET .../{id}` | ✅ |
| `POST /api/v1/returns/{id}/approve`, `.../reject`, `.../receive` (drives `stock_movements`/`stock_levels`), `.../refund` | ✅ |
| `GET /api/v1/dashboard/sales-trend`, `GET /api/v1/dashboard/order-status-breakdown` | ✅ |
| `GET /api/v1/reports/sales`, `.../products-performance`, `.../low-stock` (each with `/export` and `/export-pdf` twins) | ✅ — this row was stale (Phase 18 shipped these but never flipped it) |
| `GET /api/v1/storefront/store` | ✅ |
| `GET /api/v1/storefront/categories`, `GET .../{slug}` (+ its products, paginated) | ✅ |
| `GET /api/v1/storefront/brands`, `GET .../{slug}` (+ its products, paginated) | ✅ |
| `GET /api/v1/storefront/products` (search/category/brand/featured/sort, paginated), `GET .../{slug}` (PDP: variants, bundle components + availability) | ✅ |
| `GET /api/v1/storefront/locations/divisions` `/districts` `/upazilas` (same `LocationController` the admin app uses, reachable without a token — nationwide reference data, nothing store-scoped or sensitive) | ✅ |
| `POST /api/v1/storefront/checkout` (guest-only, server-priced, auto-selects warehouse — see `DATABASE_DESIGN.md` section 1n), `GET /api/v1/storefront/orders/{uuid}` (receipt lookup by uuid only) | ✅ |
| `POST /api/v1/account/auth/register` (claims an unclaimed guest `customers` row by phone, or creates a fresh one — see `DATABASE_DESIGN.md` section 1o), `POST .../login` | ✅ |
| `POST /api/v1/account/auth/logout`, `GET .../me` (all three `account/*` groups below are gated by `auth:sanctum` + the new `customer` middleware, never `staff`) | ✅ |
| `GET /api/v1/account/orders` (own orders only, never a client-supplied customer id), `GET .../{uuid}` (404, not 403, for someone else's) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/account/addresses(/{address})` (own addresses only) | ✅ |
| `PUT /api/v1/account/profile` (name/email only) | ✅ |
| `GET /api/v1/notifications` (own inbox, paginated, `meta.unread_count`), `POST .../read-all`, `POST .../{id}/read` — no order/return write endpoints of their own; a notification fires as a side effect of the order/return endpoints above (see `API_DESIGN.md`'s Notifications note) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/pages` (admin, store-scoped, gated on the single `pages.manage` permission) | ✅ |
| `GET /api/v1/storefront/pages` (published-only, backs the footer), `GET .../{slug}` (published-only, 404 on draft or wrong-store) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/homepage-blocks`, `GET .../preview` (all blocks incl. drafts, resolved), `POST .../reorder`, `.../{id}/duplicate`, `.../{id}/publish`, `.../{id}/unpublish`, `.../{id}/schedule`, `GET .../{id}/revisions`, `POST .../{id}/revisions/{revision}/restore`, `POST .../{id}/save-as-section` | ✅ |
| `GET/POST/DELETE /api/v1/saved-sections`, `POST .../{id}/insert` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/testimonials` | ✅ |
| `GET/DELETE /api/v1/newsletter-subscribers` | ✅ |
| `GET /api/v1/storefront/homepage-blocks` (active blocks, resolved), `POST /api/v1/storefront/newsletter/subscribe` (throttled) | ✅ |
| `GET/POST/PUT/DELETE /api/v1/blog-posts` (Phase 14 takes over the Phase 13 placeholder), `GET .../{id}/versions`, `POST .../{id}/versions/{versionId}/restore` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/blog-categories`, `.../blog-tags` | ✅ |
| `GET /api/v1/storefront/blog` (paginated, `?search=`), `GET .../{slug}` (+ related posts), `GET .../category/{slug}`, `.../tag/{slug}` (each paginated), `GET .../rss` (RSS 2.0 XML) | ✅ |
| Everything else under CMS (navigation/media)/SEO/etc. | ⏳ — added phase by phase |

This table is the map for future sessions: pick the next ⏳ row in
phase order (see `DEVELOPMENT_ROADMAP.md`), implement backend + frontend
together, flip it to ✅, move on.
