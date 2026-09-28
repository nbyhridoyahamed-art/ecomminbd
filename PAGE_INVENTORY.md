# PAGE / ROUTE INVENTORY

Legend: ✅ built & functional this session · ⏳ planned, route does not
exist yet (per spec rule 178, we do not create routes/pages ahead of
their backing functionality — no dead pages).

## Admin (`frontend/src/app/(admin)/`)

| Route | Status | Phase |
|---|---|---|
| `/login` | ✅ | 3 (Auth) |
| `/dashboard` | ✅ | 3/11 (real KPI stat cards, each permission-gated so a card never shows a misleading 0; sales-trend + order-status-breakdown Recharts visuals; recent-orders widget) |
| `/catalog/products` | ✅ | 5 (search, filter by category/brand/status, pagination) |
| `/catalog/products/new`, `/catalog/products/[id]` | ✅ | 5/5-Wave2a (tabbed form: General/Pricing/Variants*/Media/SEO — *Variants tab only shown once type is set to Variable; multi-image upload with primary selection) |
| `/catalog/categories` | ✅ | 5 (hierarchical list, unlimited nesting, cycle-safe) |
| `/catalog/categories/new`, `/catalog/categories/[id]` | ✅ | 5 |
| `/catalog/brands` | ✅ | 5 (list, search, pagination) |
| `/catalog/brands/new`, `/catalog/brands/[id]` | ✅ | 5 |
| `/catalog/attributes` | ✅ | 5 Wave 2a (list showing each attribute's values inline) |
| `/catalog/attributes/new`, `/catalog/attributes/[id]` | ✅ | 5 Wave 2a (edit page also manages the attribute's values inline: add/edit/delete, blocked while a value is still used by a variant) |
| `/catalog/products/[id]` (Variants tab: Stock column + "Adjust stock" action) | ✅ | variant-aware retrofit — per-variant stock summary and adjustment entry point, since the global `/inventory/stock-levels` list stays product-centric (see `DATABASE_DESIGN.md` section 1c) |
| `/orders/orders/new`, `/purchasing/purchase-orders/new`, `/inventory/transfers/new` (variant picker on line items) | ✅ | variant-aware retrofit — a variable product's row grows a second `Select` for its variant (required client-side before submit, though the API itself still accepts a bare product); `order_items`/`purchase_order_items`/`stock_transfer_items`/`stock_levels`/`stock_movements` are now all variant-aware, see `DATABASE_DESIGN.md` section 1i |
| `/catalog/products` (bundle/combo types) | ⏳ | 5 Wave 2b — needs Orders-integrated component stock decrement, not just a new `type` value |
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
| `/purchasing/purchase-orders/[id]` | ✅ | 7 (items w/ received-so-far, place/cancel actions, record-receipt form, receipt history — drives real `stock_movements`) |
| `/purchasing/suppliers` | ✅ | 7 (list, search, pagination) |
| `/purchasing/suppliers/new`, `/purchasing/suppliers/[id]` | ✅ | 7 |
| `/purchasing` (purchase returns, supplier ledger/payment terms, PO approval workflow, reorder suggestions) | ⏳ | 7 Wave 2 — no real consumer yet, see `DATABASE_DESIGN.md` |
| `/orders` | ✅ | 8 (redirects to Orders) |
| `/orders/orders` | ✅ | 8 (status/customer filters, total shown per order) |
| `/orders/orders/new` | ✅ | 8 (customer/warehouse/payment method + saved-or-manual shipping address + line-item builder) |
| `/orders/orders/[id]` | ✅ | 8/9/10 (items, shipping address, status badge, Process/Ship/Deliver/Cancel actions gated by status+permission, status history timeline — ship drives real `stock_movements`; courier-assignment + shipment status card from Phase 9; Returns list + "Request a return" form, shown once `delivered`, from Phase 10) |
| `/orders/customers` | ✅ | 8 (list, search, pagination) |
| `/orders/customers/new` | ✅ | 8 |
| `/orders/customers/[id]` | ✅ | 8 (edit customer + inline saved-address manager, no separate address pages) |
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
| `/website/homepage` | ⏳ | 13 |
| `/website/pages` | ⏳ | 12 |
| `/website/navigation` | ⏳ | 12 |
| `/website/media` | ⏳ | 12 |
| `/website/theme` | ⏳ | 12 |
| `/blog` | ⏳ | 14 |
| `/seo` | ⏳ | 15 |
| `/reports` | ⏳ | 18 |
| `/settings/general` | ✅ | 4 (store name/domain/currency/timezone/locale/status) |
| `/settings/users` | ✅ | 4 (list, search, create, edit, role assignment, delete w/ confirmation) |
| `/settings/users/new`, `/settings/users/[id]` | ✅ | 4 |
| `/settings/roles` | ✅ | 4 (list, create, edit grouped permission matrix, delete w/ confirmation; Super Admin/Store Owner locked) |
| `/settings/roles/new`, `/settings/roles/[id]` | ✅ | 4 |
| `/settings/localization` | ⏳ | 4 (BD divisions/districts seeded; managing them via UI is a later increment — not needed until Phase 6+ warehouse/address forms) |

## Storefront (`frontend/src/app/(storefront)/`)

| Route | Status | Phase |
|---|---|---|
| `/` | ⏳ | 16 |
| `/products/[slug]` | ⏳ | 16 |
| `/category/[slug]` | ⏳ | 16 |
| `/brand/[slug]` | ⏳ | 16 |
| `/blog`, `/blog/[slug]` | ⏳ | 14/16 |
| `/pages/[slug]` | ⏳ | 12/16 |
| `/cart`, `/checkout` | ⏳ | 16 |

## Customer Account (`frontend/src/app/(account)/`)

| Route | Status | Phase |
|---|---|---|
| `/account` | ⏳ | 17 |
| `/account/orders`, `/account/orders/[id]` | ⏳ | 17 |
| `/account/addresses` | ⏳ | 17 |
| `/account/wishlist` | ⏳ | 17 |
| `/account/profile` | ⏳ | 17 |
| `/account/returns` | ⏳ | 17 |

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
| Everything under CMS/blog/SEO/reports/etc. | ⏳ — added phase by phase |

This table is the map for future sessions: pick the next ⏳ row in
phase order (see `DEVELOPMENT_ROADMAP.md`), implement backend + frontend
together, flip it to ✅, move on.
