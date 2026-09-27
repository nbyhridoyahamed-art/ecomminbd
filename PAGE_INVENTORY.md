# PAGE / ROUTE INVENTORY

Legend: ✅ built & functional this session · ⏳ planned, route does not
exist yet (per spec rule 178, we do not create routes/pages ahead of
their backing functionality — no dead pages).

## Admin (`frontend/src/app/(admin)/`)

| Route | Status | Phase |
|---|---|---|
| `/login` | ✅ | 3 (Auth) |
| `/dashboard` | ✅ (shell + real KPI wiring, sparse data expected pre-catalog) | 3/11 |
| `/catalog/products` | ⏳ | 5 |
| `/catalog/categories` | ⏳ | 5 |
| `/catalog/brands` | ⏳ | 5 |
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
| `/settings/*` | ⏳ | 4 (backend settings table exists; admin settings UI ships incrementally per section as each domain lands) |
| `/settings/users` | ⏳ | 4 (backend user/role CRUD exists via API; UI screen next) |
| `/settings/roles` | ⏳ | 4 |
| `/settings/localization` | ⏳ | 4 (BD divisions/districts seeded; UI to manage them is a later increment) |

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
| `GET/POST/PUT/DELETE /api/v1/roles` | ✅ |
| Everything under products/orders/inventory/etc. | ⏳ — added phase by phase |

This table is the map for future sessions: pick the next ⏳ row in
phase order (see `DEVELOPMENT_ROADMAP.md`), implement backend + frontend
together, flip it to ✅, move on.
