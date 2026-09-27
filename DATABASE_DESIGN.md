# DATABASE DESIGN

Target: MySQL 8+. Every table follows the base conventions from spec
section 4: `id` (bigint autoincrement PK), `created_at`/`updated_at`,
`deleted_at` where soft-deletion makes sense, `created_by`/`updated_by`
where auditability matters, `status` where the entity has a lifecycle,
and `store_id` where the entity is store-scoped. Foreign keys, indexes,
unique constraints and cascade rules are explicit — JSON columns are
used only for genuinely flexible/unstructured data (e.g. block
`settings` in the page builder, gateway `metadata`), never as a
substitute for relational design.

## 1. Schema Built This Session (Phase 1–4 Foundation)

```
organizations
  id, uuid, name, slug (unique), email, phone, status, timestamps

stores
  id, uuid, organization_id (FK→organizations, cascade),
  name, slug (unique), domain (nullable, unique),
  default_currency_id (FK→currencies), default_timezone (varchar, default 'Asia/Dhaka'),
  default_locale (varchar, default 'en'), status, timestamps, deleted_at

warehouses
  id, uuid, store_id (FK→stores, cascade), name, code (unique per store),
  type (enum: main, branch, pickup_point, temporary),
  manager_name, phone, address_line, division_id, district_id, upazila_id,
  status, timestamps, deleted_at

users
  id, uuid, name, email (unique), phone (unique, nullable, normalized BD format),
  password, current_store_id (FK→stores, nullable),
  locale, timezone, avatar_path, status (active/suspended),
  email_verified_at, remember_token, timestamps, deleted_at

store_user (pivot)
  id, store_id (FK), user_id (FK), is_owner (bool), status, timestamps
  unique(store_id, user_id)

roles / permissions / model_has_roles / model_has_permissions /
role_has_permissions
  Standard spatie/laravel-permission tables (team-aware: `team_id` maps
  to `store_id` so the same user can hold different roles per store).

personal_access_tokens
  Standard Sanctum table.

password_reset_tokens, sessions
  Standard Laravel auth tables.

currencies
  id, code (ISO 4217, unique, e.g. BDT), symbol (৳), name,
  decimal_places (default 2), is_default, status, timestamps

settings
  id, store_id (FK, nullable = platform-level), group (varchar, e.g.
  'general', 'localization', 'checkout'), key, value (json),
  timestamps
  unique(store_id, group, key)

bd_divisions
  id, name_en, name_bn, code (unique), timestamps

bd_districts
  id, division_id (FK→bd_divisions, cascade), name_en, name_bn, code,
  timestamps
  unique(division_id, code)

bd_upazilas
  id, district_id (FK→bd_districts, cascade), name_en, name_bn, code,
  timestamps
  unique(district_id, code)

activity_logs
  id, uuid, store_id (nullable), user_id (nullable, FK, set null on delete),
  action (varchar, e.g. 'product.price_updated'), entity_type, entity_id,
  old_values (json, nullable), new_values (json, nullable),
  ip_address, user_agent, timestamps
  index(entity_type, entity_id), index(store_id, created_at)
```

### Indexing notes
- `stores.slug`, `stores.domain`: unique — storefront routing depends on these.
- `users.email` unique; `users.phone` unique-but-nullable (normalized
  before the unique check so `01712345678` and `+8801712345678` collide
  as intended — spec section 9).
- `settings` composite unique `(store_id, group, key)` supports both
  platform-wide and per-store overrides with one table.
- `activity_logs` indexed by `(entity_type, entity_id)` for "show history
  of this record" queries and by `(store_id, created_at)` for the audit
  log list view.

## 2. Target Schema for Future Phases (design intent, not yet migrated)

These are documented now so later phases don't have to re-derive the
shape, and so the foundation tables above (store_id placement, soft
deletes, currency as a table not a hardcoded symbol) are already
compatible with them.

- **Catalog:** `products`, `product_variants`, `product_attributes`,
  `product_attribute_values`, `categories` (nested set or parent_id +
  materialized path), `brands`, `product_images`, `product_media`,
  `reviews`.
- **Inventory:** `stock_levels` (product_variant_id, warehouse_id,
  available/reserved/incoming/damaged), `stock_movements` (append-only
  ledger: type enum, quantity delta, reference_type/reference_id,
  before/after snapshot), `stock_transfers`, `stock_transfer_items`,
  `stock_adjustments`.
- **Purchasing:** `suppliers`, `purchase_orders`, `purchase_order_items`,
  `purchase_receipts`, `purchase_returns`.
- **Orders:** `customers`, `customer_addresses`, `orders`, `order_items`,
  `order_status_history`, `payments`, `cod_settlements`, `coupons`,
  `coupon_usages`.
- **Delivery:** `couriers` (config per provider), `shipments`,
  `delivery_zones`, `delivery_zone_rates`.
- **Returns:** `returns`, `return_items`, `refunds`, `exchanges`.
- **CMS/Builder:** `pages`, `page_versions`, `navigation_menus`,
  `navigation_items`, `media`, `homepage_blocks` (ordered, `type` +
  `settings` JSON per the block registry pattern), `saved_sections`.
- **Blog:** `blog_posts`, `blog_post_versions`, `blog_categories`,
  `blog_tags`, `blog_post_tag` (pivot).
- **SEO:** `seo_metadata` (polymorphic: entity_type/entity_id, title,
  description, focus_keyword, og_*, twitter_*, schema_json, canonical,
  robots), `redirects`, `seo_templates`.
- **Reporting/Analytics:** materialized/aggregated tables populated by
  scheduled jobs rather than heavy runtime aggregation on raw tables.

All money columns in future phases use integer minor-unit columns
(`*_amount` in paisa) — never `float`/`double` — per spec rule 27.
All polymorphic SEO/media relations use `entity_type` + `entity_id`
rather than one FK column per entity type, to avoid N nullable FK
columns on a shared table.

## 3. Money Representation

`amount_minor` (bigint, paisa) + `currency_code` (char(3)) on every
financial column. A `Money` PHP value object (backend `Support/`) and a
`formatMoney()` TS helper (frontend `lib/`) are the *only* places that
convert to/from display strings — no component or controller formats
currency manually.
