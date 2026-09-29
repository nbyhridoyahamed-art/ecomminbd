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
| `/catalog/products/new`, `/catalog/products/[id]` | ✅ | 5/5-Wave2a/5-Wave2c/15 (tabbed form: General/Pricing/Variants*/Components†/Media/SEO — *Variants tab only shown once type is set to Variable, †Components tab only shown once type is set to Bundle, showing a product+variant+quantity picker, the bundle's derived availability by warehouse, and per-row edit/remove; multi-image upload with primary selection; the SEO tab is the shared `SeoFields` component since Phase 15, not the flat seo_title/seo_description/focus_keyword inputs it originally shipped with) |
| `/catalog/categories` | ✅ | 5 (hierarchical list, unlimited nesting, cycle-safe) |
| `/catalog/categories/new`, `/catalog/categories/[id]` | ✅ | 5 (since Phase 15, also a bordered "SEO" section — the shared `SeoFields` component) |
| `/catalog/brands` | ✅ | 5 (list, search, pagination) |
| `/catalog/brands/new`, `/catalog/brands/[id]` | ✅ | 5 (since Phase 15, also a bordered "SEO" section — the shared `SeoFields` component) |
| `/catalog/attributes` | ✅ | 5 Wave 2a (list showing each attribute's values inline) |
| `/catalog/attributes/new`, `/catalog/attributes/[id]` | ✅ | 5 Wave 2a (edit page also manages the attribute's values inline: add/edit/delete, blocked while a value is still used by a variant) |
| `/catalog/products/[id]` (Variants tab: Stock column + "Adjust stock" action) | ✅ | variant-aware retrofit — per-variant stock summary and adjustment entry point, since the global `/inventory/stock-levels` list stays product-centric (see `DATABASE_DESIGN.md` section 1c) |
| `/orders/orders/new`, `/purchasing/purchase-orders/new`, `/inventory/transfers/new` (variant picker on line items) | ✅ | variant-aware retrofit — a variable product's row grows a second `Select` for its variant (required client-side before submit, though the API itself still accepts a bare product); `order_items`/`purchase_order_items`/`stock_transfer_items`/`stock_levels`/`stock_movements` are now all variant-aware, see `DATABASE_DESIGN.md` section 1i |
| `/catalog/products/[id]` (Components tab, bundle/combo type) | ✅ | 5 Wave 2c — component picker (product + optional variant + quantity), derived per-warehouse availability, editable quantity, remove; `/orders/orders/[id]` shows a bundle line item's resolved component breakdown inline for packing; `/purchasing/purchase-orders/new`, `/inventory/transfers/new` exclude bundles from their product picker (a bundle is never purchased or transferred directly — see `DATABASE_DESIGN.md` section 1l) |
| `/catalog/media` (reusable, browsable media library) | ✅ | 5 Wave 3 — grid browse/upload/search/edit-alt-text/delete; a `MediaPickerDialog` wires the same library into category/brand image fields and the product image gallery as a "Browse library" option alongside direct upload, so one uploaded file is reusable across every entity |
| `/catalog/reviews` (moderation queue) | ✅ | 5 Wave 3 — status/rating filters, star rating + product/customer columns, Approve/Reject (`reviews.moderate`) and Delete (`reviews.delete`) row actions |
| `/inventory` | ✅ | 6 (redirects to Stock Levels) |
| `/inventory/stock-levels` | ✅ | 6 (per-warehouse on-hand quantity, low-stock filter, search, adjust dialog) |
| `/inventory/movements` | ✅ | 6 (read-only ledger, filter by warehouse/type) |
| `/inventory/transfers` | ✅ | 6 (status Badge column + filter) |
| `/inventory/transfers/new`, `/inventory/transfers/[id]` | ✅ | 6 (multi-item warehouse-to-warehouse transfer; 6 Wave 2 turned the show page into a real workflow view — `StockTransferStatusCard` with status-gated ship/receive/cancel actions, status history timeline, and a stock-movements table once shipped) |
| `/inventory/warehouses` | ✅ | 6 (list, create, edit — backend model existed since Phase 4, admin UI was the Phase 6 gap this closes) |
| `/inventory/warehouses/new`, `/inventory/warehouses/[id]` | ✅ | 6 |
| `/inventory/stocktakes` | ✅ | 6 Wave 2 (list of stocktake sessions — reference, warehouse, line count, recorded-by/when) |
| `/inventory/stocktakes/new` | ✅ | 6 Wave 2 (multi-line form: warehouse, optional reference, per-line product/variant/direction/quantity/reason; accepts a `?warehouse_id=` prefill from the Stock Levels page's "Start stocktake" link) |
| `/inventory/stocktakes/[id]` | ✅ | 6 Wave 2 (header + lines table: product/variant, direction Badge, quantity, before→after, reason) |
| `/inventory/stock-levels` ("Start stocktake" entry point) | ✅ | 6 Wave 2 (links to `/inventory/stocktakes/new` prefilled with the currently-selected warehouse, gated on `inventory.adjust`) |
| `/inventory/stock-levels` (Reserved/Available columns) | ✅ | 6/8 (stock reservations tied to orders — Phase 8 built the consumer; each row now sums a variable product's stock across all its variants rather than a single row per variant, see `DATABASE_DESIGN.md` section 1c) |
| `/purchasing` | ✅ | 7 (redirects to Purchase Orders) |
| `/purchasing/purchase-orders` | ✅ | 7 (status/supplier filters, total shown per order) |
| `/purchasing/purchase-orders/new` | ✅ | 7 (supplier/warehouse + line-item builder with unit cost) |
| `/purchasing/purchase-orders/[id]` | ✅ | 7 (items w/ received-so-far, record-receipt form, receipt history, returns summary + request-a-return form — drives real `stock_movements`); 7 Wave 2b turned the header into a real workflow view — submit-for-approval/approve/reject (with a reason dialog)/cancel actions gated by status + the new `purchase_orders.approve` permission, a status-history timeline, and a payments list |
| `/purchasing/purchase-returns` | ✅ | 7 Wave 2a (list, filter by status) |
| `/purchasing/purchase-returns/[id]` | ✅ | 7 Wave 2a (items, status history, approve/reject/ship-back/credit actions) |
| `/purchasing/suppliers` | ✅ | 7 (list, search, pagination) |
| `/purchasing/suppliers/new` | ✅ | 7 |
| `/purchasing/suppliers/[id]` | ✅ | 7 (edit form, gated on `suppliers.update` — a read-only detail card shows instead for a `suppliers.view`-only user, e.g. Accountant); 7 Wave 2b added a payment-terms field to the form and a always-visible Ledger card (entries table + running balance, "Record payment" gated on the new `suppliers.pay` permission) |
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
| `/reports/reorder-suggestions` | ✅ | 7 Wave 2b (low-stock products enriched with 30-day sales velocity, a suggested reorder quantity — deficit plus a fixed 14-day lead-time buffer — and the most recent non-cancelled supplier/unit cost, Export dropdown: CSV/PDF) |
| `/analytics` | ✅ | 20 (redirects to Overview) |
| `/analytics/overview` | ✅ | 20 (date range + granularity filters, page views/unique sessions/conversion-rate stat cards each with a vs.-previous-period trend badge, a compact secondary row — product views/searches/added-to-cart/checkout-starts, new `TrafficTrendChart`, Export dropdown: CSV/PDF) |
| `/analytics/products` | ✅ | 20 (date range filter, view-count/added-to-cart/view-to-cart-rate table ranked by views — real storefront behavior, not sales, so a product can rank high here with zero orders, Export: CSV) |
| `/analytics/searches` | ✅ | 20 (search term/count/avg-results table, a "No results" badge flagging queries that never once returned anything — the single most actionable row in the whole phase, Export: CSV) |
| `/analytics/funnel` | ✅ | 20 (date range filter, unique sessions reaching each of 4 stages — product view/added to cart/started checkout/purchased — as a plain vertical `Card` stack with stage-over-stage conversion, deliberately not a chart per spec rule 21; no export, it's a 4-row summary) |
| `/analytics/customers` | ✅ | 20 (date range + granularity filters, new/returning-customer stat cards + an all-time repeat-purchase-rate stat, by-period table, Export: CSV) |
| `/content/pages` | ✅ | 12 — a small, deliberate path change beyond this table's original `/website/pages` sketch, grouping under a new "Content" nav section rather than a "Website" one (list w/ Title/Slug/Status, delete w/ confirmation) |
| `/content/pages/new`, `/content/pages/[id]` | ✅ | 12/15 (title/slug w/ auto-fill, plain-text content, draft/published status, the shared `SeoFields` section since Phase 15 — was flat meta title/description before) |
| `/content/homepage` | ✅ | 13 — same deliberate `/content/*` grouping as Pages above, not this table's original `/website/homepage` sketch; the tab-nav infrastructure both now share is documented in `COMPONENT_INVENTORY.md`. A real live-WYSIWYG drag-and-drop builder: left sidebar (Blocks palette/Layers/Saved sections), center canvas (dnd-kit sortable, renders every block through the exact same component the live storefront uses, via a new `/homepage-blocks/preview` endpoint), right panel (Content/Design/Layout/Animation/Advanced/SEO tabs, local autosave + undo/redo, a revision-history sheet with restore). All ~30 block types have a real Content-tab editor — see `COMPONENT_INVENTORY.md`. Gated on a granular `builder.view`/`builder.edit`/`builder.publish` split, not one umbrella permission like Pages. |
| `/website/navigation` | ⏳ | 12 Wave 2 — no `navigation_menus`/`navigation_items` schema yet, see `DATABASE_DESIGN.md` |
| `/website/media` | ⏳ | 12 Wave 2 — the underlying reusable media library itself now exists (`/catalog/media`, Phase 5 Wave 3); what's still missing is wiring its `MediaPickerDialog` into CMS-specific image fields (blog posts, pages, the Homepage Builder), not the library itself |
| `/website/theme` | ⏳ | 12 Wave 2 |
| `/content/blog/posts` | ✅ | 14 — a small, deliberate path change beyond this table's original `/blog` sketch, following Pages/Homepage's own `/content/*` grouping; the first Content sub-resource to get its own nested tab-nav (Posts/Categories/Tags, documented in `COMPONENT_INVENTORY.md`) |
| `/content/blog/posts/new`, `/content/blog/posts/[id]` | ✅ | 14/15 (title/slug w/ auto-fill, category `Select`, tag checkboxes, TipTap body, excerpt w/ auto-fallback note, featured image URL, draft/published status + a `datetime-local` schedule field, the shared `SeoFields` section since Phase 15, a Version History sheet on the edit page) |
| `/content/blog/categories`, `.../new`, `.../[id]` | ✅ | 14/15 (name/slug w/ auto-fill, description; list shows each category's post count; since Phase 15, also a bordered "SEO" section — a brand-new addition, this entity had no SEO field before) |
| `/content/blog/tags`, `.../new`, `.../[id]` | ✅ | 14/15 (name/slug w/ auto-fill; list shows each tag's post count; since Phase 15, also a bordered "SEO" section — a brand-new addition) |
| `/content/seo/redirects` | ✅ | 15 — a 4th tab alongside Pages/Homepage/Blog in the existing Content nav section (list w/ From/To/Status code/Hits columns, delete w/ confirmation) |
| `/content/seo/redirects/new`, `/content/seo/redirects/[id]` | ✅ | 15 (from-path/to-path text inputs, from-path validated to start with `/`, a status-code `Select` — 301/302/307/308) |
| `/content/seo/templates` | ✅ | 15 (list w/ Entity type (friendly label)/Title template/Description template columns, delete w/ confirmation) |
| `/content/seo/templates/new`, `/content/seo/templates/[id]` | ✅ | 15 (an entity-type `Select` of the seven SEO-bearing model types, locked once set; title/description template text inputs — free-text hints, no templating engine reads them yet) |
| `/settings/general` | ✅ | 4 (store name/domain/currency/timezone/locale/status) |
| `/settings/users` | ✅ | 4 (list, search, create, edit, role assignment, delete w/ confirmation) |
| `/settings/users/new`, `/settings/users/[id]` | ✅ | 4 |
| `/settings/roles` | ✅ | 4 (list, create, edit grouped permission matrix, delete w/ confirmation; Super Admin/Store Owner locked) |
| `/settings/roles/new`, `/settings/roles/[id]` | ✅ | 4 |
| `/settings/localization` | ⏳ | 4 (BD divisions/districts seeded; managing them via UI is a later increment — not needed until Phase 6+ warehouse/address forms) |

## Storefront (`frontend/src/app/(storefront)/`)

Every leaf route below marked "Phase 15 Server Component" is now a
`page.tsx` Server Component (real `generateMetadata()`, JSON-LD, and a
redirect/404 check on its own by-slug lookup failing) wrapping a sibling
`*-client.tsx` Client Component that keeps doing the exact same
interactive TanStack Query fetch it always did — see `ARCHITECTURE.md`
section 9 and `COMPONENT_INVENTORY.md`'s new SEO section. Listing/browse
pages (`/products`, `/brands`, `/blog` index, `/cart`, `/checkout`) stay
plain Client Components — they were never missing a per-entity `<title>`.

| Route | Status | Phase |
|---|---|---|
| `/` | ✅ | 16/13/15 — fully block-driven since Phase 13: fetches every active block via `GET storefront/homepage-blocks` and renders each through `HomepageBlockRenderer`, the same registry component the admin builder's canvas uses. No hardcoded sections of its own left (the original Phase 16 hardcoded hero/category-grid/featured-products JSX this table used to describe is gone). Phase 15 Server Component: real `<title>`/description from the store's own site-wide SEO override (set via the Homepage Builder's `SeoPanel`) falling back to the store name, plus Organization+WebSite JSON-LD. |
| `/products` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch, which had no all-products/search index; search/category/brand/featured filters and sort all live in the URL query string so a filtered link is shareable |
| `/products/[slug]` | ✅ | 16/15/5-Wave3 — Phase 15 Server Component (image gallery, variant picker gating "Add to Cart" until a full attribute selection resolves to a real variant, bundle component list + derived availability, out-of-stock state; Product + BreadcrumbList JSON-LD); Phase 5 Wave 3 added a rating summary linking to a Reviews section (paginated approved-only list, average rating + count) and a "Write a review" entry point, shown only to a signed-in customer who hasn't already reviewed the product — the real verified-purchase gate is still server-side, so an ineligible attempt surfaces the API's own error rather than being pre-checked client-side |
| `/category/[slug]` | ✅ | 16/15 — Phase 15 Server Component (category + its subcategories as quick links, paginated active products; BreadcrumbList JSON-LD) |
| `/brand/[slug]` | ✅ | 16/15 — Phase 15 Server Component (BreadcrumbList JSON-LD) |
| `/brands` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch (a brand index page for the header/footer's "Brands" link to point at, mirroring `/products`' justification) |
| `/blog` | ✅ | 14/16 (paginated, `?search=`) |
| `/blog/[slug]` | ✅ | 14/16/15 — Phase 15 Server Component (body rendered from real HTML via `dangerouslySetInnerHTML`, same trust model as the homepage builder's Rich Text/Custom HTML blocks; clickable category/tags; up to 3 related posts sharing its category; Article + BreadcrumbList JSON-LD) |
| `/blog/category/[slug]`, `/blog/tag/[slug]` | ✅ | 14/16/15 — Phase 15 Server Components (paginated archives, mirroring `/category/[slug]`'s shape; BreadcrumbList JSON-LD) |
| `/pages/[slug]` | ✅ | 12/16/15 — Phase 15 Server Component (title + plain-text content rendered `whitespace-pre-line`, matching the product description convention; draft pages and pages from another store both 404; BreadcrumbList JSON-LD) |
| `/cart` | ✅ | 16 (line items w/ quantity stepper, subtotal — reused `CartLineItem` also backs the header's cart drawer) |
| `/checkout` | ✅ | 16 (guest-only: name/phone/email, shipping address w/ live BD division/district/upazila cascade, order summary; payment method is a fixed "Cash on Delivery" label, not a selector — Wave 1 has only the one method, so a picker would be a fake choice) |
| `/order-confirmation/[uuid]` | ✅ | 16 — a small, deliberate addition beyond this table's original sketch, which had a `/checkout` row but nowhere named where a successful checkout lands; looked up by uuid only, same public-receipt contract as the API route |
| `/sitemap.xml`, `/robots.txt` | ✅ | 15 — Next.js's own native `app/sitemap.ts` (`force-dynamic`, so it reflects the live catalog rather than a stale build-time snapshot)/`app/robots.ts` special files, not a Laravel endpoint (robots.txt must disallow this same app's own admin/account/cart/checkout paths, which the Laravel API has no visibility into) |

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
| `/account/reviews` | ✅ | 5 Wave 3 — the customer's own reviews, any status (Pending approval/Published/Not approved); the order-detail page (`/account/orders/[uuid]`) also grows a "Rate this product" action per delivered line item not yet reviewed |
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
| `GET/POST /api/v1/stock-transfers`, `GET .../{id}` (creates `pending`, no immediate stock movement) | ✅ |
| `POST /api/v1/stock-transfers/{id}/ship`, `.../receive`, `.../cancel` (drives `stock_movements`/`stock_levels`) | ✅ |
| `GET/POST /api/v1/stock-adjustment-sessions`, `GET .../{id}` | ✅ |
| `GET/POST/PUT/DELETE /api/v1/suppliers` | ✅ |
| `GET /api/v1/suppliers/{id}/ledger` (per-receipt debits, credited-return and payment credits, running balance — computed in PHP, not SQL, for MySQL/SQLite portability), `POST .../payments` (gated on `suppliers.pay`, separate from `suppliers.update`) | ✅ 7 Wave 2b |
| `GET/POST/PUT/DELETE /api/v1/purchase-orders` | ✅ |
| `POST /api/v1/purchase-orders/{id}/submit-for-approval`, `.../approve`, `.../reject`, `.../cancel` (approve/reject gated on the new `purchase_orders.approve` permission, separate from `update` — see `DATABASE_DESIGN.md`) | ✅ 7 Wave 2b replaced the old single-step `place` action with a real `draft → pending_approval → ordered` workflow |
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
| `GET /api/v1/reports/reorder-suggestions` (+ `/export`, `/export-pdf` twins; low-stock rows enriched with 30-day sales velocity and the most recent non-cancelled supplier/unit cost) | ✅ 7 Wave 2b |
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
| `GET/POST/PUT/DELETE /api/v1/redirects` (store-scoped path-based redirect rules, gated on `seo.manage`) | ✅ — 15 |
| `GET/POST/PUT/DELETE /api/v1/seo-templates` (per-`entity_type` title/description template, one per type per store) | ✅ — 15 |
| `GET/PUT /api/v1/store-seo` (site-wide `seo_metadata` row on the `Store` entity itself; gated directly on `seo.manage`, not `stores.manage`, so a Content/SEO manager doesn't need full store-management access) | ✅ — 15 |
| `GET /api/v1/storefront/redirects/lookup?path=` (no-auth; the Next.js middleware/server-side redirect resolver hits this before falling through to a real page; increments `hits_count` on match) | ✅ — 15 |
| `POST /api/v1/storefront/analytics/events` (no-auth, throttled higher than checkout/newsletter — a real browsing session fires many more of these; the eight event types a storefront page ever sends) | ✅ — 20 |
| `GET /api/v1/analytics/overview` (+ `/export`, `/export-pdf`), `.../products` (+ `/export`), `.../searches` (+ `/export`), `.../funnel` (no export), `.../customers` (+ `/export`) — all gated on the new `analytics.view` permission | ✅ — 20 |
| `GET/DELETE /api/v1/reviews` (`reviews.view`/`.delete`, filterable), `POST .../{id}/approve`, `.../reject` (`reviews.moderate`) | ✅ — 5 Wave 3 |
| `GET/POST /api/v1/account/reviews` (own reviews any status; submitting re-checks verified-purchase server-side, never trusts a client `order_id`) | ✅ — 5 Wave 3 |
| `GET /api/v1/storefront/products/{slug}/reviews` (paginated, approved-only) | ✅ — 5 Wave 3 |
| `GET/POST/PUT/DELETE /api/v1/media` (`media.view/create/update/delete`; `GET` requires `store_id`), `POST /api/v1/products/{id}/images/attach` (pick an existing `media` row into a product's gallery, no re-upload) | ✅ — 5 Wave 3 |
| Everything else under CMS navigation/theme (`/website/navigation`, `/website/theme`) | ⏳ — added phase by phase |

This table is the map for future sessions: pick the next ⏳ row in
phase order (see `DEVELOPMENT_ROADMAP.md`), implement backend + frontend
together, flip it to ✅, move on.
