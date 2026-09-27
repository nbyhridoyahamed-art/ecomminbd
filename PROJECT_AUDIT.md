# PROJECT AUDIT

**Date:** 2026-09-27
**Repository:** nbyhridoyahamed-art/ecomminbd
**Branch:** claude/friendly-knuth-kqj7zw

## 1. Current Repository State

The repository was **completely empty** at the start of this work: a bare git
repo with no commits, no files, and no framework of any kind installed.

```
$ git log --oneline -20
fatal: your current branch 'claude/friendly-knuth-kqj7zw' does not have any commits yet

$ ls -la
. .. .git
```

There is no existing:
- Laravel application
- Next.js application
- Database schema
- API
- Authentication system
- UI component library
- Package manifests (composer.json / package.json)
- CI configuration
- Documentation

**Conclusion:** this is a greenfield build. Nothing needs to be reverse
engineered or migrated; every architectural decision below is a fresh
choice, made to match the master specification.

## 2. Environment / Tooling Available

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.4.19 (CLI, NTS) | Matches "PHP 8.3+" requirement |
| Composer | 2.8.12 | Plugins disabled by default as root; not needed for our packages |
| Node.js | v22.22.2 | Matches Next.js / modern tooling requirements |
| npm | 10.9.7 | |
| MySQL client/server | **Not installed** in this container | See below |

**Database note:** MySQL 8+ is the target production database per the
spec (section 4), and all migrations/schema are written for MySQL
(engine-specific column types, JSON columns only where justified, proper
FK/index syntax). Because this sandbox has no MySQL server, automated
backend tests in this environment run against SQLite (an in-memory
connection configured only for the `testing` environment in
`backend/phpunit.xml`) purely so migrations and business logic can be
verified without a database server. This is a **test-environment
convenience only** — it does not change the production schema design,
and nothing in application code branches on driver. Deployment docs
(`docs/deployment.md`, to be written in a later phase) will specify
MySQL 8 as the only supported production driver.

## 3. What Exists Now (End of This Session)

This session scaffolds and implements **Phase 0–3** of the 26-phase
roadmap (see `DEVELOPMENT_ROADMAP.md`):

- Full documentation suite (this file + 8 others listed below)
- `backend/` — Laravel 11 API application with the mandated
  `app/{Actions,DTOs,Enums,Events,Exceptions,Jobs,Listeners,Models,
  Notifications,Policies,Repositories,Rules,Services,Support}` structure,
  Sanctum authentication, Spatie roles/permissions, multi-store
  foundation models (organizations, stores, warehouses), Bangladesh
  localization seed data (divisions/districts/upazilas), and settings.
- `frontend/` — Next.js 15 (App Router, TypeScript strict, Tailwind,
  shadcn/ui) application with the design token system, dark mode, the
  core reusable UI primitives, and a working login page + authenticated
  dashboard shell (sidebar + topbar) wired to the real backend API.

Everything else in the master specification (catalog, inventory,
orders, purchasing, delivery/COD, returns, CMS/page builder, blog, SEO,
storefront, customer account, reporting, analytics, notifications, etc.)
is **not yet built**. Those are explicitly out of scope for this pass and
are tracked phase-by-phase in `DEVELOPMENT_ROADMAP.md` so future sessions
pick up exactly where this one left off, without re-deriving decisions.

## 4. Documents Produced This Session

1. `PROJECT_AUDIT.md` (this file)
2. `ARCHITECTURE.md` — system architecture, module boundaries, adapter patterns
3. `DATABASE_DESIGN.md` — schema for the entities built so far, plus the
   target schema shape for future phases
4. `API_DESIGN.md` — REST conventions, response envelope, versioning, auth
5. `UI_UX_ARCHITECTURE.md` — admin/storefront layout system, responsive rules
6. `DESIGN_SYSTEM.md` — tokens, typography, color, dark mode, component states
7. `COMPONENT_INVENTORY.md` — reusable UI components, built vs. planned
8. `PAGE_INVENTORY.md` — full route list from the spec, built vs. planned
9. `DEVELOPMENT_ROADMAP.md` — the 26-phase plan with per-phase status

## 5. Key Architectural Decisions Made

- **Monorepo layout:** `backend/` (Laravel API) and `frontend/` (Next.js),
  sibling directories at repo root.
- **Backend is authoritative** for all money, stock, and permission
  calculations — never trust client-submitted totals (spec rules 182–183).
- **Multi-store from day one**: `organizations → stores → users`, with
  `store_id` on store-scoped tables, even though only one store is seeded
  today. This avoids a costly retrofit later.
- **RBAC via Spatie `laravel-permission`** rather than a hand-rolled
  system — it's battle-tested, supports the granular `resource.action`
  permission format the spec requires (e.g. `products.view`), and maps
  cleanly onto Laravel Policies.
- **Bangladesh localization as core data, not a plugin**: `bd_divisions`,
  `bd_districts`, `bd_upazilas` are first-class tables seeded at
  migration time, exactly as spec section 10 requires ("do not hard-code
  these into frontend components").
- **API versioning** under `/api/v1/` with a consistent
  `{ success, message, data, meta }` envelope (spec section 108).
- **Frontend never computes money/stock**; it only renders what the API
  returns, per spec rules 27/182.

## 6. Immediate Risks / Things a Future Session Must Watch

- No MySQL server in this sandbox — first real integration test against
  MySQL (not SQLite) should happen in a proper deployment/staging
  environment before Phase 4+ ships.
- Courier and payment gateway credentials do not exist yet — Phase 9/19
  will need mock providers (spec section 179) until real credentials are
  supplied.
- No design assets (logo, favicon) exist yet; theme defaults use the
  token values from spec section 45/46 verbatim.
