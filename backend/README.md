# Eleventory — Backend

Laravel API backend for Eleventory, a Bangladesh-focused ecommerce ERP
platform. See the repository root for the full product specification and planning
docs (`ARCHITECTURE.md`, `DATABASE_DESIGN.md`, `API_DESIGN.md`,
`DEVELOPMENT_ROADMAP.md`, `PROJECT_AUDIT.md`).

## What's implemented so far

Phases 0–3 and the backend half of Phase 4 (see `DEVELOPMENT_ROADMAP.md`
at the repo root for the full phase plan):

- Sanctum token authentication (register/login/logout/me/forgot-password/reset-password)
- RBAC via `spatie/laravel-permission` — 17 default roles, granular `resource.action` permissions
- Multi-store foundation: organizations → stores → warehouses, with `store_user` for per-store membership
- Bangladesh localization data: `bd_divisions` / `bd_districts` / `bd_upazilas` (representative seed set)
- Currencies, database-driven settings
- Activity log table for future audit trails
- Consistent `{ success, message, data, meta }` API envelope, centralized exception → JSON mapping

## Requirements

- PHP 8.3+
- Composer
- MySQL 8+ (production target — see `.env.example`); SQLite works fine for quick local dev

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The seeder creates a demo Super Admin: `admin@eleventory.test` / `password`
(store: "Eleventory Flagship Store"). Change or remove this before any real
deployment.

## Tests

```bash
php artisan test        # PHPUnit feature/unit tests (runs against in-memory SQLite)
./vendor/bin/pint        # Code style
```

## Architecture

Controllers stay thin — see `ARCHITECTURE.md` at the repo root for the
full layered architecture (`Actions/`, `Services/`, `Repositories/`,
`Policies/`, etc.) and the rules this codebase follows (no floats for
money, no client-trusted totals, transactional writes, adapter pattern
for external integrations).
