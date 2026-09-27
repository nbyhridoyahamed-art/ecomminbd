# PAGE / ROUTE INVENTORY

Legend: ✅ built & functional this session · ⏳ planned, route does not
exist yet (per spec rule 178, we do not create routes/pages ahead of
their backing functionality — no dead pages).

## Admin (`frontend/src/app/(admin)/`)

| Route | Status | Phase |
|---|---|---|
| `/login` | ✅ | 3 (Auth) |
| `/dashboard` | ✅ (shell + real KPI wiring, incl. product count) | 3/11 |
| `/catalog/products` | ✅ | 5 (search, filter by category/brand/status, pagination) |
| `/catalog/products/new`, `/catalog/products/[id]` | ✅ | 5 (tabbed form: General/Pricing/Media/SEO; multi-image upload with primary selection) |
| `/catalog/categories` | ✅ | 5 (hierarchical list, unlimited nesting, cycle-safe) |
| `/catalog/categories/new`, `/catalog/categories/[id]` | ✅ | 5 |
| `/catalog/brands` | ✅ | 5 (list, search, pagination) |
| `/catalog/brands/new`, `/catalog/brands/[id]` | ✅ | 5 |
| `/catalog/products` (variable/bundle/combo types, attributes, variant generator) | ⏳ | 5 Wave 2 — schema reserves `type` for this; only `simple` is functional |
| `/catalog/media` (reusable, browsable media library) | ⏳ | 5 Wave 2 — product/category/brand images upload directly today, no shared library yet |
| `/inventory` | ⏳ | 6 |
| `/inventory/warehouses` | ⏳ | 6 (backend model exists; admin UI is Phase 6) |
| `/inventory/transfers` | ⏳ | 6 |
| `/inventory/adjustments` | ⏳ | 6 |
| `/purchases/suppliers` | ⏳ | 7 |
| `/purchases/orders` | ⏳ | 7 |
| `/orders` | ⏳ | 8 |
| `/orders/[id]` | ⏳ | 8 |
| `/customers` | ⏳ | 8 |
| `/delivery/couriers` | ⏳ | 9 |
| `/delivery/shipments` | ⏳ | 9 |
| `/delivery/zones` | ⏳ | 9 |
| `/delivery/cod-settlement` | ⏳ | 9 |
| `/returns` | ⏳ | 10 |
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
| Everything under orders/inventory/purchasing/etc. | ⏳ — added phase by phase |

This table is the map for future sessions: pick the next ⏳ row in
phase order (see `DEVELOPMENT_ROADMAP.md`), implement backend + frontend
together, flip it to ✅, move on.
