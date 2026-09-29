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
| Dropdown Menu | ✅ | used in topbar (profile, notifications — real data since Phase 19); since Phase 18 Wave 2c also backs the Export button on all three Reports pages (CSV/PDF) |
| Sheet (drawer) | ✅ | mobile sidebar; since Phase 16 also backs the storefront's mobile nav menu and the cart drawer — a `side="right"` `SheetContent`, no new primitive needed |
| Select | ✅ | Radix-based; store currency/locale/status, user status, role assignment, product category/brand/status, warehouse pickers, stock adjustment direction, supplier/warehouse pickers on purchase orders, customer/warehouse/payment-method/saved-address pickers on orders, cascading BD division/district/upazila pickers (customer addresses + order shipping), courier pickers on shipments/COD settlements, variant pickers on order/purchase-order/stock-transfer line items and the variant stock-adjustment dialog's warehouse picker, warehouse/granularity filters on the Sales report and warehouse filter on the Product Performance report, bundle component picker (product + `VariantPicker`) on the product form's Components tab; since Phase 16, the identical primitive also backs the storefront checkout's own cascading BD division/district/upazila picker and the `/products` browse page's category/brand/sort filters — same component, no storefront-specific variant, confirming `UI_UX_ARCHITECTURE.md`'s "shared tokens/primitives, different layout" split holds in practice; since Phase 7 Wave 2b also backs the supplier form's payment-terms picker (due-on-receipt/net-15/net-30/net-60) and the record-supplier-payment dialog's method picker (cash/bank transfer/bKash/Nagad/cheque); since Phase 8 Wave 2 also backs the coupon form's status and discount-type (percentage/fixed) pickers and the order show page's record-payment dialog's method picker — the exact same method list as the supplier one, now with a second consumer; since Phase 9 Wave 2 also backs the delivery zone form's division/district ("None" sentinel for a division-wide or store-wide zone, the same optional-parent pattern `CategoryForm`'s "None (top-level)" already established) and status pickers |
| Textarea | ✅ | category/brand/product descriptions, SEO meta description, warehouse address, transfer note, supplier address, PO notes/receipt note, order notes, shipment delivered/failed/returned notes, COD settlement note, return request reason, return reject/receive notes, stock-transfer cancel note, stocktake session note/per-line reason; since Phase 7 Wave 2b also the PO reject-reason note and the record-supplier-payment note; since Phase 8 Wave 2 also the order show page's record-payment note |
| Checkbox | ✅ | Radix-based; role permission matrix, user role assignment, product track-stock/featured flags, low-stock-only filter, customer address default flag, COD settlement shipment picker, per-item restock decision on return receive |
| Radio | ⏳ | Not needed yet |
| Switch | ⏳ | Not needed yet — every boolean setting so far reads fine as a Checkbox or Select |
| Tooltip | ⏳ | Not needed yet |
| Modal/Dialog | ✅ | centered dialog (distinct from Sheet); delete confirmations for users/roles/categories/brands/products/warehouses/suppliers/customers/customer addresses, stock adjustment form, purchase-order cancel confirmation, customer address add/edit form, order cancel confirmation, shipment delivered/failed/returned-to-seller confirmations (COD capture on delivery), return reject/receive/refund confirmations (receive has a per-item restock checklist, refund has a suggested-amount-prefilled input), purchase-return reject/credit confirmations (credit has the same suggested-amount-prefilled input as a customer refund), stock-transfer cancel confirmation; since Phase 7 Wave 2b also the PO reject dialog (reason note) and the supplier ledger's record-payment dialog (amount/method/reference/note); since Phase 8 Wave 2 also a coupon delete confirmation (naming past usage if any) and the order show page's record-payment dialog — the same amount/method/reference/note shape as the supplier one; since Phase 9 Wave 2 also a delivery zone delete confirmation |
| Tabs | ✅ | Two flavors: a route-driven Link sub-nav (Settings, Catalog — now Products/Categories/Brands/Attributes, Inventory — now Stock Levels/Movements/Transfers/Stocktakes/Warehouses, Purchasing — now Purchase Orders/Purchase Returns/Suppliers, Orders/Customers/Returns, Delivery, Reports — Sales/Product Performance/Low Stock, and since Phase 20 Analytics — Overview/Products/Searches/Funnel/Customers) and a client-state tab switcher inside the product form (General/Pricing/Variants*/Components†/Media/SEO — *shown only when type is Variable, †shown only when type is Bundle) — plain buttons + conditional rendering, not the Radix Tabs primitive, since neither use case needed its accessibility semantics beyond what a nav/button already gives |
| Accordion | ✅ | Phase 15 — Radix-based (`@radix-ui/react-accordion`), `type="multiple"`; groups the shared `SeoFields` component's Open Graph/Twitter Card/Advanced sections (see the new SEO section below) |
| Table / DataTable | ✅ | Server-paginated table w/ loading/empty states. Built as a small dependency-free component rather than on TanStack Table — the installed major version (v9) shipped a completely different, unfamiliar API; safer to write ~100 lines directly than guess at an API with no reliable reference. Now also backs Warehouses, Stock Levels, Movements, Transfers, Stocktakes, Suppliers, Purchase Orders, Purchase Returns, Customers, Orders, Couriers, Shipments, COD Settlements, Returns, Attributes, the Reports suite (Sales' payment-method and courier breakdowns, Product Performance, Low Stock, and since Phase 7 Wave 2b Reorder Suggestions), since Phase 20 the Analytics suite (Products, Searches, Customers' by-period table), and since Phase 9 Wave 2 Delivery Zones. The supplier Ledger card (Phase 7 Wave 2b) is a plain `<table>`, not this component — its rows are a computed running balance that must render in full date order, not a server-paginated page of them, the same reasoning `SupplierPaymentController::ledger()` already applies server-side (see `DATABASE_DESIGN.md`). |
| Pagination | ✅ | ships with DataTable (prev/next, server-driven) |
| Breadcrumb | ✅ | topbar |
| Toast | ✅ | global toaster for mutations |
| Timeline | ✅ | order, shipment, return, and stock-transfer status history — a plain `<ol>` of status badges + timestamp/actor, same "plain markup over a new primitive" call as Tabs; now has six consumers (order/shipment/return/stock-transfer/purchase-order show pages, plus Phase 17's `/account/orders/[uuid]`) but still duplicated inline rather than extracted — the newest one isn't even the same shape (a customer-safe version with no staff note/actor, see `Account\OrderResource` in `API_DESIGN.md`), which if anything argues against a shared component more than for one; promote only once a consumer actually needs to change in a way the others must match |
| Chart | ✅ | Recharts (v3, React 19-compatible); see the Charts section below — no generic `ui/chart.tsx` wrapper, each chart is its own focused component under `components/charts/` since the two built so far (trend vs. breakdown) have different enough shapes that a shared abstraction would be premature |
| Date Picker | ✅ | No dedicated component — a plain native `<input type="date">` via the existing `Input` primitive, which just forwards arbitrary native `type` props. First used by the blog post form's `datetime-local` schedule field (Phase 14); Phase 8 Wave 2 reused the identical approach for the coupon form's starts-at/expires-at fields, confirming a real "Date Picker" primitive still isn't worth building |
| Command Palette | ⏳ | Products is now a searchable resource, but the palette itself is still unbuilt — next natural pickup |
| File Upload | ✅ | Two components: `ImageUploadField` (single image — category/brand) and `ProductImageGallery` (multi-image with primary selection, delete, drag-free grid). Since Phase 5 Wave 3, both also offer a "Browse library" action opening the shared `MediaPickerDialog` (see below) alongside their original direct-upload button, so a file uploaded once is reusable anywhere. |
| AttributeForm / AttributeValuesManager | ✅ | Phase 5 Wave 2a — `AttributeForm` (name/slug, mirrors `CategoryForm`'s shape minus the hierarchy) and `AttributeValuesManager` (inline add/edit/delete for one attribute's values; no drag-reorder — `sort_order` is just assignment order, reordering wasn't worth a drag-and-drop dependency for Wave 2a's scope). |
| VariantsManager | ✅ | Phase 5 Wave 2a — attribute-value checkboxes (pre-checked from a product's existing variants) + a "Generate variants" action calling the cartesian-product endpoint, plus a per-variant row (SKU/price override/status, inline Save when dirty, Delete). Lives under `components/catalog/`, reused by the product form's Variants tab. Since the variant-aware retrofit, each row also shows a Stock column (`stock_summary` total available / on-hand / reserved) and an "Adjust stock" action opening `VariantStockAdjustmentDialog` — this is the only place variant-level stock is visible, since the global Stock Levels list stays product-centric (see `DATABASE_DESIGN.md` section 1c). |
| VariantPicker / VariantStockAdjustmentDialog | ✅ | Added for the variant-aware retrofit. `VariantPicker` renders nothing for a simple product (or one with no generated variants yet) and otherwise a `Select` of that product's variants (attribute values joined with " / ", plus SKU) — reused identically by the Order, Purchase Order, and Stock Transfer forms' line-item rows. `VariantStockAdjustmentDialog` is `StockAdjustmentDialog`'s counterpart for a specific product+variant rather than a `StockLevel` row: it adds its own warehouse picker (the Variants tab has no ambient warehouse context, unlike the already-warehouse-scoped Stock Levels page) and otherwise reuses the same `useCreateStockAdjustment` mutation. Both live under `components/catalog/`. |
| ComponentsManager | ✅ | Phase 5 Wave 2c — a bundle's Components tab: an add-component row (product `Select`, reusing `VariantPicker` unchanged for the optional variant sub-pick, and a quantity input) plus a per-component row (editable quantity, inline Save when dirty, Remove) — same shape as `VariantsManager`'s own add-then-list layout. The product picker excludes the bundle itself and every other bundle-type product (nested bundles aren't allowed), matching the same exclusion `PurchaseOrderForm`'s and `StockTransferForm`'s product pickers now apply, since a bundle can never be purchased, adjusted, or transferred directly (`App\Rules\ProductIsNotBundle`). Also shows the bundle's derived `bundle_availability` (total sellable quantity + a per-warehouse breakdown) above the picker. Lives under `components/catalog/`, reused by the product form's Components tab. The order show page's line-item table also gained an inline, read-only component breakdown for any bundle line item (product/SKU/variant/quantity), for staff/packing visibility — not a new component, just a nested list in the existing items table. |
| ProductImportDialog | ✅ | Phase 5 Wave 2b — a plain native file input (`accept=".csv"`) plus a Save action calling `useImportProducts()`, then swaps to a results view: created/updated/skipped counts and, if any rows failed, a scrollable per-row error list. Lives under `components/catalog/`, opened from the Products list page's new Import button next to the pre-existing Export button (`useExportProducts()`, downloading via the new `api.download()` helper — see `API_DESIGN.md`'s Catalog CSV import/export note for why a plain `<a href>` can't carry the auth token an authenticated file download needs). |
| Media Library (`app/(admin)/catalog/media/page.tsx`) | ✅ | Phase 5 Wave 3 — a searchable, paginated grid of the store's `media` rows; upload, per-item hover actions (edit alt text via a `Dialog`, delete via a confirmation `Dialog`), gated on `media.create`/`media.update`/`media.delete` respectively. The library's own page and `MediaPickerDialog` (below) share the same `useMedia`/`useUploadMedia` hooks — same data, two different UIs for it. |
| MediaPickerDialog | ✅ | Phase 5 Wave 3 — a reusable dialog (`components/settings/`) that browses the store's `media` library (search + pagination) or uploads a new file straight from within it, resolving to one selected `Media`. Three callers wire it in: `ImageUploadField` (category/brand), `ProductImageGallery` (its own icon tile, both the populated-grid and empty-state layouts), and any future image field the same way — one component, no per-entity reimplementation. Picking an existing image never re-uploads the file; for the product gallery specifically, picking calls the new `useAttachProductImage()` hook (`POST /products/{id}/images/attach`) rather than `useUploadProductImage()`. |
| Reviews moderation (`app/(admin)/catalog/reviews/page.tsx`) | ✅ | Phase 5 Wave 3 — status + rating `Select` filters, a `DataTable` (product/customer/star-rating/review-excerpt/status columns), row actions gated on `reviews.moderate` (Approve/Reject, pending only) and `reviews.delete` (always), a delete confirmation `Dialog` — same list-page shape as `orders/returns/page.tsx`, its own template. |
| WriteReviewDialog | ✅ | Phase 5 Wave 3 — a reusable review-submission dialog (`components/reviews/`) taking a `productId`/`productName`/`trigger` element, so the caller supplies its own button. Two callers: the storefront PDP's "Write a review" button, and a per-item "Rate this product" button on the account order-detail page (`/account/orders/[uuid]`) for any line item flagged `reviewable`. Submission is optimistic about eligibility — the button shows whenever the customer hasn't already reviewed that product — and lets the server's own verified-purchase check (see `API_DESIGN.md`) surface the real error if the order isn't actually delivered. |
| Rich Editor (TipTap) | ✅ | Phase 13 — `RichTextEditor` (`components/builder/`), a shared toolbar (bold/italic/heading/bullet+numbered list/blockquote/link) over `@tiptap/react` + `starter-kit`, backing the Rich Text block's Content panel. First real use of the "professional rich editor" the tech stack always called for; Phase 14 reused this exact component unchanged for the blog post body editor, confirming the bet made when Phase 12 chose plain text for CMS pages instead. |
| Stat Card | ✅ | dashboard KPI cards; supports a `tone="danger"` accent, added for the Phase 6 low-stock-alerts card; since Phase 11 every card on the dashboard is filtered by the permission that backs its number before rendering, rather than showing 0 for data the viewer can't see. Also backs the Sales report's Revenue/Orders/Average Order Value cards (Phase 18), whose own `trend` prop — defined since this component was first built, but with no real consumer until now — went live in Wave 2b as each card's vs.-previous-period badge. |
| Empty State | ✅ | generic empty-state component (icon/title/description/CTA) |

## Layout Components (`frontend/src/components/layout/`)

| Component | Status | Notes |
|---|---|---|
| AdminSidebar | ✅ | collapsible, responsive drawer below 768px |
| AdminTopbar | ✅ | breadcrumb, theme toggle, profile menu, and — since Phase 19 — a real notification bell: `useNotifications()` polls `GET /notifications` every 30s, an unread-count badge on the Bell icon (same dot-badge pattern as the storefront cart icon), each row click marks it read and navigates to the order, plus a "Mark all read" action; empty/loading states still render honestly (a skeleton while loading, "No notifications yet." only once the real list has actually come back empty) |
| AdminShell | ✅ | composes sidebar+topbar+content, persists sidebar collapsed state |

## Storefront Components (`frontend/src/components/storefront/`)

| Component | Status | Notes |
|---|---|---|
| StorefrontHeader | ✅ | Phase 16 — sticky header: mobile hamburger opening a `Sheet` nav (All Products/Brands/Blog since Phase 14/category list), store name (from `GET storefront/store`) as a text logo, `CategoryMegaMenu`, desktop All Products/Brands/Blog links, a search form (`router.push`'s to `/products?search=...`, no live-filter debounce needed since it's a full navigation), and a cart button with an item-count badge reading `useCartStore` |
| CategoryMegaMenu | ✅ | Phase 16 — a `DropdownMenu` sized as a wide grid rather than a narrow list (`w-[min(90vw,720px)]`), one column per top-level category with its `children` listed underneath as links — reuses the existing Dropdown Menu primitive rather than adding a new one, since a bigger `DropdownMenuContent` is all a "mega menu" needs on top of it |
| StorefrontFooter | ✅ | Phase 16 — store name/tagline, Shop links (All Products/Brands, since Phase 14 also Blog), and up to 5 top-level categories; since Phase 12 also an "Information" column listing every published CMS page (`useStorefrontPages()`), rendered only once at least one exists |
| MobileBottomNav | ✅ | Phase 16 — fixed bottom bar, hidden at `desktop:` width: Home, Shop, and a Cart button (badge from `useCartStore`) opening the same drawer the header's cart icon does |
| CartDrawer | ✅ | Phase 16 — a `Sheet` listing `CartLineItem`s, subtotal, and View Cart/Checkout actions; empty state when the cart has nothing in it |
| CartLineItem | ✅ | Phase 16 — image/name/variant-label, a quantity stepper, remove button, and line total; shared as-is between `CartDrawer` (compact) and the full `/cart` page, both reading/writing the same `useCartStore` (`zustand` + `persist`, `localStorage`-backed — see `ARCHITECTURE.md` section 9) |
| ProductCard | ✅ | Phase 16 — the product-grid tile used on the homepage, `/products`, `/category/[slug]`, and `/brand/[slug]`: image (or a `Package` icon placeholder), name, price with a strikethrough original price when on sale, and an Out of Stock/Featured badge. Links to the PDP only — no quick-add-to-cart from a listing card, since a variable product needs a real variant selection first and a bundle needs its own availability check, both of which only the PDP does |
| StorefrontPagination | ✅ | Phase 16 — the same server-paginated Previous/Next footer, reused identically across `/products`, `/category/[slug]`, and `/brand/[slug]` rather than copied three times; since Phase 14 also `/blog`, `/blog/category/[slug]`, and `/blog/tag/[slug]`, via a new `itemLabel` prop (defaults to "products") so the footer text reads "posts" there instead |
| BlogPostCard | ✅ | Phase 14 — the blog-grid tile used on `/blog`, `/blog/category/[slug]`, `/blog/tag/[slug]`, and the post detail page's related-posts strip: image (or a `Newspaper` icon placeholder), category label, title, excerpt, and a published-date/reading-time footer — same "whole card links to the detail page" shape as `ProductCard`, so no nested category/tag links inside it |

## Customer Account (`frontend/src/app/account/`)

No new components under `components/` — Phase 17's account pages
(overview, orders list/detail, addresses, profile) are each self-contained
page components in the same style as the Reports pages, built directly on
existing primitives (Card/Badge/Skeleton/EmptyState/Dialog) rather than
introducing new ones. Two reuse decisions worth recording: `/account/
addresses` renders the existing `components/customers/CustomerAddressForm`,
but it wasn't quite "completely unchanged" — a real bug only Playwright
verification against a production build caught. The form's division/
district/upazila `Select`s originally called `useDivisions`/`useDistricts`/
`useUpazilas` (`@/hooks/use-locations`), which hit `/locations/*` — and
that prefix sits behind the admin `staff` middleware (see
`ARCHITECTURE.md` section 3), so a signed-in *customer* (no staff token at
all) got a silent 401 and every cascading picker rendered permanently
empty. Fixed by pointing the same form at `useStorefrontDivisions`/
`useStorefrontDistricts`/`useStorefrontUpazilas` (`@/hooks/
use-storefront-catalog`) instead — the identical, already-public
`/storefront/locations/*` endpoints Storefront Wave 1's own checkout page
already uses, returning the exact same `BdLocation`-shaped rows. Safe for
the *admin* call site too, precisely because this reference data was
already documented as "nationwide reference data, not store-scoped or
sensitive" — a staff token attached to the request changes nothing, since
the endpoint never checks for one. Its companion
`CustomerAddressList`, by contrast, was *not* reused as-is — it's wired
directly to admin hooks that take an explicit `customerId`
(`useCreateCustomerAddress(customerId)`, etc.), while the account
equivalents are always implicitly scoped to the signed-in customer via
their token, so `/account/addresses` reimplements that list/dialog shell
itself around the account hooks rather than forcing an admin-shaped
dependency into a customer page. `StorefrontHeader` (Phase 16, above)
gained one addition: an account icon (`User`, `lucide-react`) linking to
`/account`, reading the new `useCustomerAuthToken()` only to choose its
`aria-label` ("My account" vs "Sign in") — the `(dashboard)` route
group's own auth gate, not this icon, is what actually decides where a
click lands.

## Content / CMS (`frontend/src/components/content/`, `frontend/src/app/(admin)/content/`)

| Component | Status | Notes |
|---|---|---|
| PageForm | ✅ | Phase 12 Wave 1 — title/slug (slug auto-filled from title via the existing `slugify()` helper until manually touched, the exact `CategoryForm` pattern), a plain `Textarea` for content (not TipTap — see the Primitives table's Rich Editor row; a helper line tells the editor line breaks are preserved on the storefront), a draft/published `Select`, and (since Phase 15) the shared `SeoFields` section in place of the old flat meta title/description inputs. Lives under `components/content/`, reused by both the admin new-page and edit-page routes exactly like `CategoryForm`. |

No new list/table primitive — the admin `/content/pages` list reuses the
existing `DataTable` + delete-confirmation `Dialog` shell verbatim (Title/
Slug/Status columns, a status `Badge`), the same shape as every other
flat-list admin resource (Categories, Brands, Suppliers). Gating is a
single `can(user, "pages.manage")` check rather than Category's separate
view/create/update/delete checks, reflecting `PagePolicy`'s one-permission
design (`DATABASE_DESIGN.md` section 1q) — both the page-level
`PermissionDenied` guard and the row-level Edit/Delete actions read the
same boolean.

## Content / Blog (`frontend/src/components/content/`, `frontend/src/app/(admin)/content/blog/`)

| Component | Status | Notes |
|---|---|---|
| BlogPostForm | ✅ | Phase 14 — title/slug (same auto-fill-until-touched pattern as `PageForm`/`CategoryForm`), a category `Select` (store's `blog_categories`, "No category" default), a tag picker (`Checkbox` per store tag in a wrapped flex row — no combobox/multi-select primitive exists yet, and this store's tag counts don't need one), the shared `RichTextEditor` for `body`, an excerpt `Textarea` with a note that it's auto-generated from the body when left blank, a draft/published `Select` paired with a `datetime-local` publish-date input (blank = publish now, future = real scheduling), and (since Phase 15) the same shared `SeoFields` section `PageForm` uses. Lives under `components/content/`, reused by both the new-post and edit-post routes. |
| BlogCategoryForm / BlogTagForm | ✅ | Phase 14 — name/slug (BlogCategoryForm adds a description `Textarea`), the same auto-fill-until-touched slug pattern as every other form in the app. Simple enough that, like Category/Brand/Page, each gets its own dedicated create/edit route rather than an inline dialog. Since Phase 15, both also carry a bordered "SEO" section (the shared `SeoFields` component) — a brand-new addition, neither entity had any SEO field before. |
| BlogPostVersionHistorySheet | ✅ | Phase 14 — a `Sheet` listing a post's server-side `blog_post_versions` (timestamp, editor, the snapshot's title, Restore), opened from the edit page's header. Deliberately simpler than Homepage Builder's `RevisionHistorySheet`: a blog post edit page has no local in-session draft to re-sync on restore (it's a traditional Save-button form, not a live-autosave canvas), so restoring just refetches and the page shows the restored state directly. |

No new list/table primitive — `/content/blog/posts|categories|tags` each
reuse the existing `DataTable` + delete-confirmation `Dialog` shell
verbatim, the same shape `/content/pages` already established. A new
`content/blog/layout.tsx` adds a second, nested level of tab-nav
(Posts/Categories/Tags) directly under the outer Content layout's own
Pages/Homepage/Blog tabs — the first Content sub-resource to need one,
since `blog.manage` is a single umbrella permission with nothing to
filter per-tab the way Purchasing's own top-level tabs gate on different
permissions.

## SEO (`frontend/src/components/shared/`, `frontend/src/components/content/`, `frontend/src/app/(admin)/content/seo/`, `frontend/src/lib/`)

| Component | Status | Notes |
|---|---|---|
| SeoFields | ✅ | Phase 15 — the one shared form section behind every entity's SEO editing: title/description inputs each with a live character-count target indicator (30–60/120–160 chars, green/amber against the target), a focus-keyword input, a real rule-based checklist (title/description length, focus-keyword presence in title/description — deterministic pass/fail against plain string checks, never a fabricated AI-style score, per spec rule 178), and an `Accordion` (Open Graph / Twitter Card / Advanced-canonical+robots) for the fields most edits won't touch. A plain controlled `{value, onChange}` component (not wired to any one form's `react-hook-form` instance), the same shape `AdvancedPanel`/homepage-block panels already established, so each of the seven forms below owns its own local `useState` and passes `seoFieldsToPayload()`'s result into its own submit payload. Lives under `components/shared/` — the first component with no single owning domain, used by both `components/settings/` (Product/Category/Brand) and `components/content/` (Page/BlogPost/BlogCategory/BlogTag) forms alike. |
| RedirectForm / SeoTemplateForm | ✅ | Phase 15 — plain CRUD forms for the two genuinely independent SEO resources, same shape as `PageForm`. `RedirectForm`: from-path/to-path text inputs (from-path validated to start with `/`) plus a status-code `Select` (301/302/307/308). `SeoTemplateForm`: an entity-type `Select` of the seven SEO-bearing model types (locked to plain text once set — changing it after creation would collide with the `unique(store_id, entity_type)` constraint) plus title/description template text inputs (free-text hints like `{{title}} \| {{store_name}}`; no templating engine reads these yet). Both live under `components/content/`, reused by their own new/edit routes under a new `/content/seo/redirects` and `/content/seo/templates`, a 4th tab alongside Pages/Homepage/Blog in the existing Content nav section. |

No new list/table primitive — `/content/seo/redirects|templates` each
reuse the existing `DataTable` + delete-confirmation `Dialog` shell
verbatim, the same shape every other flat-list admin resource uses.

Not components, but the frontend-side pieces that make real per-page
`<head>` metadata and JSON-LD possible — worth recording here since
they're new architectural surface, not just new UI:
- `lib/storefront-api.ts` — a server-only fetch client for the public
  storefront API, used only from Server Components/`generateMetadata()`/
  `sitemap.ts`. A separate module from the existing `lib/api.ts` because
  that one imports `auth-token.ts` (a `"use client"` module reading
  `localStorage`), and a Server Component can't call a function exported
  from a Client Component module.
- `lib/storefront-seo.ts` — `buildStorefrontMetadata()` (turns a `Seo`
  object, or `null`, into a complete Next `Metadata` object with sensible
  entity-name/excerpt fallbacks) and `resolveRedirectOrNotFound()` (the
  per-leaf-page redirect check, called only on a 404).
- `lib/json-ld.tsx` — a `JsonLd` component (a `<script type="application/
  ld+json">` with the necessary `<` escaping against script-tag-breakout
  injection from user-generated text) plus builder functions for Product/
  BreadcrumbList/Article/Organization+WebSite schemas.
- Every storefront leaf page's own `page.tsx` (Product/Category/Brand/
  BlogPost/BlogCategory/BlogTag archives/CMS Page/homepage) is now a
  Server Component pair with a sibling `*-client.tsx` holding the
  pre-existing interactive body unchanged — see `ARCHITECTURE.md` section
  9 and `PAGE_INVENTORY.md` for the full per-route breakdown.

## Homepage Builder (`frontend/src/components/builder/`, `frontend/src/components/homepage-blocks/`)

| Component | Status | Notes |
|---|---|---|
| BuilderCanvas | ✅ | Phase 13 — the center panel: a real `@dnd-kit` sortable list rendering every block (draft included) through `HomepageBlockRenderer`, the exact component the live storefront uses (via `GET homepage-blocks/preview`), inside a device-width frame (desktop/tablet/mobile). Each block's hover overlay carries drag handle, Live/Draft badge, and (permission-gated) Save as section/Duplicate/Delete/Publish-toggle actions. Not a click-anywhere-to-edit-inline canvas — selecting a block opens BuilderPanel for that, per spec section 58's own split between canvas and settings panel. |
| BuilderPanel | ✅ | Phase 13 — the right panel: Content/Design/Layout/Animation/Advanced/SEO tabs (spec's Content/Design/Spacing/Responsive/Animation/Advanced/SEO list, with Spacing+Responsive+per-breakpoint Visibility combined into one Layout tab — they edit one shared JSON shape, see `LayoutPanel`'s own code comment), a Save-status label (Saved/Saving/Unsaved), Undo/Redo, and a Revision History button. Owns one block's local edit session via `useEditableHomepageBlock` (debounced autosave + an in-session undo/redo stack — deliberately separate from server-side revisions below). |
| BuilderSidebar | ✅ | Phase 13 — the left panel: Blocks (a categorized palette of all ~30 types), Layers (a flat reorderable-by-drag list mirroring the canvas), and Saved (the saved-sections library: insert-to-page, remove). |
| RevisionHistorySheet | ✅ | Phase 13 — a `Sheet` listing a block's server-side revisions (timestamp, creator, Restore), opened from BuilderPanel's header. Restoring re-syncs the open panel's local draft via `useEditableHomepageBlock.syncFrom()`, since restoring changes a block's content without changing its id (the hook's normal reset is keyed on id changing). Deliberately distinct from BuilderPanel's own Undo/Redo — this is the durable, already-saved history; that's the current editing session's not-yet-saved keystrokes. |
| DesignPanel / LayoutPanel / AnimationPanel / AdvancedPanel | ✅ | Phase 13 — four generic, type-agnostic, per-block tabs every block type shares (background/text color/border-radius/shadow; per-breakpoint width/height/padding/margin/font-size/columns/gap/alignment/display + visibility; animation preset; a custom-class escape hatch). `BlockStyleScope` is the renderer-side counterpart: it turns this stored JSON into a real scoped `<style>` tag per block (`.hb-{id}`, with `@media` breakpoints), the one place that translation happens. |
| SeoPanel | ✅ | Phase 13 placeholder, wired to real data Phase 15 — unlike the four panels above, this one is deliberately NOT per-block: it edits one site-wide `seo` record (`Store.seoMetadata()`, a new `store-seo` endpoint) regardless of which block is selected on the canvas, with its own Save button rather than joining the selected block's autosave session — matching its own original placeholder copy ("Page-level SEO metadata... arrives with Phase 15"). Renders the shared `SeoFields` component, same as every admin content form. |
| AutoManualPicker | ✅ | Phase 13 — the shared "auto vs. hand-picked" selector for every catalog/content-sourcing block type (Category Grid/Carousel, Brand Carousel, Featured Products, Testimonials, Reviews): a search-filtered checkbox list, the same pattern `VariantsManager` already established, not a new searchable-combobox primitive. `Product Carousel`'s panel is the one exception — it has a third "from a category" mode `AutoManualPicker`'s binary API can't express, so it's a bespoke inline implementation instead of reusing this component. |
| Block registry: ~30 renderer + Content-panel pairs | ✅ | Phase 13 — `content-panel-registry.tsx` and `homepage-block-renderer.tsx` are the block registry's entire integration surface (spec section 60: "create a registry, not one giant conditional") — every block type's renderer (`components/homepage-blocks/renderers/`) and Content panel (`components/builder/panels/`) plugs into exactly one of these two files and nothing else. Four were hand-built as the exemplar pattern (Hero, Category Grid, Featured Products, Rich Text); the remaining ~25 followed the identical pattern (verified end to end in a running browser, not just by type-checking — see `DEVELOPMENT_ROADMAP.md`'s Phase 13 scope note for the two real bugs that caught). Not enumerated type-by-type here — the pattern, not any one instance of it, is what's worth documenting. Two pairs are intentionally shared rather than duplicated: `MultiColumnBannerBlock`/`Panel` back both Two- and Three-Column Banner (identical shape, only the array length differs), and `TestimonialsPanel` backs both Testimonials and Reviews (identical settings; only the storefront card — quote-forward vs. star-rating-forward — differs, so those keep separate renderers). |

## Charts (`frontend/src/components/charts/`)

| Component | Status | Notes |
|---|---|---|
| SalesTrendChart | ✅ | Recharts `ComposedChart` — an `Area` for revenue (left axis) + a dashed `Line` for order count (right axis), colored from CSS custom properties (`var(--color-primary)` etc.) so it repaints for dark mode automatically, same mechanism as every other themed component. Its heading was a hardcoded "last N days" until Phase 18 generalized the `days: number` prop to a plain `title: string`, since the Sales report reuses the same chart over a user-chosen date range/granularity instead of a fixed trailing window (dashboard call site now passes `title="Sales trend (last 14 days)"` — same text, now just a string instead of computed). |
| OrderStatusChart | ✅ | Recharts `BarChart`, one bar per order status, each `Cell` colored to match the same status badge variant used everywhere else (pending/processing/shipped/delivered/cancelled) |
| TrafficTrendChart | ✅ | Phase 20 — the identical `ComposedChart` shape as `SalesTrendChart` (`Area` + dashed `Line`, dual axes, CSS-variable colors), but for `page_views`/`unique_sessions` instead of revenue/orders. A new, separate component rather than a further-generalized `SalesTrendChart` — the two datasets' shapes (money+count vs. two plain counts) didn't share enough to be worth a shared abstraction beyond copying the proven visual pattern, same reasoning the "no generic `Chart` wrapper" note below already gives. Adds an explicit empty state ("No traffic recorded for this range") alongside the loading skeleton, which the original didn't need since a store always has at least one order by the time Reporting shipped. |

All three were the Recharts consumers so far (Phase 11 for the first
two). A generic reusable `Chart` wrapper wasn't built — see the
Primitives table above for why.

## Analytics (`frontend/src/components/charts/`, `frontend/src/app/(admin)/analytics/`, `frontend/src/components/storefront/`, `frontend/src/lib/`)

Not components in the traditional sense, but the new architectural
surface Phase 20 adds — worth recording here since it's the storefront's
first behavioral-tracking client, distinct from every existing `lib/*`
fetch client:
- `lib/analytics.ts` — `trackEvent(eventType, payload)`, a fire-and-forget
  client called from the storefront (never awaited, never throws).
  Deliberately `fetch(..., {keepalive: true})`, not `navigator.
  sendBeacon` — see `ARCHITECTURE.md` section 9 for why sendBeacon's
  forced-credentialed mode silently fails against this API's CORS config,
  confirmed against a real browser, not assumed. A per-browser anonymous
  session id is generated once and persisted to `localStorage`.
- `components/storefront/AnalyticsPageViewTracker` — a single invisible
  (`return null`) component mounted once in the storefront layout; a
  `usePathname()`-keyed effect fires `page_view` on every route change,
  covering every storefront page without each one wiring it individually.
- Five admin report pages under `(admin)/analytics/` (Overview/Products/
  Searches/Funnel/Customers), each following the exact structural pattern
  Reports (`(admin)/reports/`) already established — date-range filters, a
  `PermissionDenied` gate on `analytics.view`, loading skeletons, empty
  states, and CSV/PDF export via `use-analytics.ts`'s hooks. No new form
  components: Funnel's 4-stage view is a plain `Card` stack (spec rule 21:
  don't reach for a chart where a short list communicates better), and
  Searches' "No results" flag reuses the existing `Badge` primitive
  (`variant="danger"`) rather than a bespoke indicator.

## Rule Followed

No component above marked ⏳ is referenced anywhere in the shipped
code this session. A component only appears in the tree once the
screen using it has real data behind it (spec rule 178: no fake
functionality, no dead buttons).
