# DEVELOPMENT ROADMAP

26 phases, per master spec section 176. Status reflects reality, not
aspiration — a phase is only ✅ once its code, tests, and docs are all
in place and the app still builds/runs.

| # | Phase | Status | This session? |
|---|---|---|---|
| 0 | Project Discovery | ✅ Done | Yes — `PROJECT_AUDIT.md` |
| 1 | Architecture | ✅ Done | Yes — this doc suite + folder structure |
| 2 | Design System | ✅ Done | Yes — tokens, theme, first primitives |
| 3 | Authentication | ✅ Done | Yes — Sanctum, login/logout/me/reset, roles/permissions seeded |
| 4 | Store Foundation | 🟡 Backend done, admin UI pending | Yes (backend) — orgs/stores/users/roles/permissions/settings/localization/currency |
| 5 | Catalog | ⏳ Not started | No |
| 6 | Inventory | ⏳ Not started | No |
| 7 | Purchasing | ⏳ Not started | No |
| 8 | Orders | ⏳ Not started | No |
| 9 | Delivery | ⏳ Not started | No |
| 10 | Returns | ⏳ Not started | No |
| 11 | Admin Dashboard (full KPIs/charts) | 🟡 Shell only | Yes (shell) |
| 12 | CMS | ⏳ Not started | No |
| 13 | Homepage Builder | ⏳ Not started | No |
| 14 | Blog | ⏳ Not started | No |
| 15 | SEO | ⏳ Not started | No |
| 16 | Storefront | ⏳ Not started | No |
| 17 | Customer Dashboard | ⏳ Not started | No |
| 18 | Reporting | ⏳ Not started | No |
| 19 | Integrations (payment/courier/email/SMS/WhatsApp adapters) | ⏳ Not started | No |
| 20 | Analytics | ⏳ Not started | No |
| 21 | Security Hardening | ⏳ Ongoing baseline only | Partial — Sanctum, policies, rate limiting, validation from day one |
| 22 | Performance | ⏳ Not started | No |
| 23 | Accessibility | 🟡 Baseline in design system | Partial |
| 24 | Responsive QA | 🟡 Baseline (login/dashboard tested at all breakpoints) | Partial |
| 25 | Final Testing | 🟡 Backend feature tests + frontend build/lint/typecheck for what exists | Partial |
| 26 | Production | ⏳ Not started | No |

## Why the Scope Was Cut Here

The master specification describes a multi-quarter, multi-team SaaS
product (189 numbered sections covering ERP, CMS, page builder, blog,
SEO suite, and a full storefront). Building all 26 phases in a single
pass would violate the spec's own explicit rules:

- Rule 176/188: "Do NOT build the entire application in one pass...
  build incrementally... keep the application runnable after every
  phase."
- Rule 178: never generate fake functionality, dead buttons, or
  duplicated business logic — which is unavoidable if screens are
  scaffolded ahead of the backend that should drive them.
- Rule 189: the very first action mandated is producing these audit/
  architecture docs and starting **Phase 1 — Foundation**, not
  everything at once.

So this session delivers Phases 0–3 completely, and the backend half of
Phase 4, as real, tested, runnable code — a solid foundation (auth,
RBAC, multi-store data model, BD localization, design system, admin
shell) that every later phase builds directly on top of, with zero
placeholder/fake screens.

## Next Session Should Start With

1. Phase 4 admin UI: Users, Roles, Settings, Localization management
   screens (backend API already exists — see `PAGE_INVENTORY.md`).
2. Phase 5: Catalog (products, categories, brands, attributes, variants,
   media library, bulk import/export) — the biggest unblocking phase,
   since Inventory (6), Orders (8), and Storefront (16) all depend on
   products existing.
3. Follow the phase order above; do not skip ahead to CMS/SEO/Storefront
   before Orders/Inventory exist, since those phases both link to and
   depend on catalog + order data.

## Execution Protocol for Every Future Phase (spec section 177)

1. Explain phase objective.
2. Inspect relevant existing files.
3. Review architecture (this doc suite).
4. Database changes (migrations).
5. Laravel backend (Actions/Services/Repositories/Models).
6. API (Controllers/Requests/Resources/routes).
7. Next.js frontend (pages/components wired to the real API).
8. UI states (loading/empty/error/success/disabled).
9. Seed/demo data.
10. Tests.
11. Run PHP tests, TypeScript check, ESLint, PHPStan (once installed), build.
12. Fix errors.
13. Responsive review.
14. Accessibility review.
15. Update this roadmap + relevant inventory docs.
16. Summarize what's actually done — never claim more.
