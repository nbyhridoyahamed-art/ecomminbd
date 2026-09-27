# NBY Commerce ERP — Frontend

Next.js (App Router, TypeScript, Tailwind v4) frontend for the NBY
Bangladesh-focused ecommerce ERP platform. See the repository root for
the full product specification and planning docs (`ARCHITECTURE.md`,
`DESIGN_SYSTEM.md`, `UI_UX_ARCHITECTURE.md`, `COMPONENT_INVENTORY.md`,
`PAGE_INVENTORY.md`, `DEVELOPMENT_ROADMAP.md`).

## What's implemented so far

Phase 2 (Design System) and Phase 3 (Authentication) plus a starter
Phase 11 dashboard shell — see `DEVELOPMENT_ROADMAP.md` at the repo root
for the full phase plan and `PAGE_INVENTORY.md` for exactly which
routes exist today versus are still planned (no placeholder/dead routes
are shipped ahead of their backing functionality):

- Design tokens + true light/dark theme (`src/styles/tokens.css`, `theme.css`)
- Core reusable UI primitives (`src/components/ui/`)
- Admin shell: collapsible sidebar, responsive mobile drawer, topbar with theme toggle and profile menu
- Real login flow wired to the Laravel API (Sanctum bearer tokens)
- Dashboard shell with real KPI data (stores/warehouses/staff/roles counts) and an honest empty state for phases not yet built

## Requirements

- Node.js 20.9+
- The backend running (see `../backend/README.md`) — set `NEXT_PUBLIC_API_URL` in `.env.local` to point at it

## Setup

```bash
npm install
cp .env.example .env.local
npm run dev
```

Open [http://localhost:3000](http://localhost:3000). Log in with the
backend's seeded demo account: `admin@nby.test` / `password`.

## Checks

```bash
npx tsc --noEmit   # TypeScript strict mode
npm run lint       # ESLint
npm run build      # Production build
```

## Architecture

- Server Components by default; Client Components only for interactive
  forms, charts, drag/drop, and real-time widgets.
- TanStack Query for all server state; Zustand only for genuine
  client-only UI state (e.g. sidebar collapsed).
- The backend is authoritative for money, stock, and permissions — this
  app never computes or trusts client-side totals.

See `ARCHITECTURE.md` and `UI_UX_ARCHITECTURE.md` at the repo root for
the full picture.
