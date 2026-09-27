# DESIGN SYSTEM

Source of truth: `frontend/src/styles/tokens.css` (values) and
`frontend/src/styles/theme.css` (semantic mapping + dark mode). Nothing
in components hard-codes a hex color, font, radius, or breakpoint —
everything reads a CSS variable or Tailwind token that maps to one.

## 1. Color Tokens (spec section 45)

| Token | Light | Usage |
|---|---|---|
| `--color-primary` | `#2563EB` | primary actions, links, focus ring |
| `--color-success` | `#16A34A` | success states, delivered/paid badges |
| `--color-warning` | `#F59E0B` | low stock, pending, warnings |
| `--color-danger` | `#DC2626` | destructive actions, errors, out of stock |
| `--color-info` | `#0EA5E9` | informational badges |
| `--color-background` | `#F8FAFC` | app background |
| `--color-surface` | `#FFFFFF` | cards, panels, tables |
| `--color-border` | `#E2E8F0` | dividers, input borders |
| `--color-text-primary` | `#0F172A` | headings, body text |
| `--color-text-secondary` | `#64748B` | secondary text |
| `--color-text-muted` | `#94A3B8` | placeholders, captions |

## 2. Dark Mode (spec section 46)

A true dark theme, not an inverted one:

| Token | Dark |
|---|---|
| `--color-background` | `#0B1120` |
| `--color-surface` | `#111827` |
| `--color-surface-elevated` | `#172033` |
| `--color-border` | `#243044` |
| `--color-text-primary` | `#F8FAFC` |
| `--color-text-secondary` | `#94A3B8` |

Applied via `@media (prefers-color-scheme: dark)` guarded by
`:root:not([data-theme="light"])`, and `:root[data-theme="dark"]` for an
explicit user toggle (persisted client-side), matching the pattern the
platform's own artifact/theming convention uses.

## 3. Typography

- **English:** Inter.
- **Bangla:** Noto Sans Bengali (loaded alongside Inter; Bangla text
  gets a slightly taller `line-height` — 1.6 vs. 1.5 for Latin — because
  Bengali glyphs need more vertical room to avoid clipping).
- Scale: Display 32–40px / Page title 24–28px / Section 18–20px / Body
  14–16px / Table 13–14px / Caption 12px — implemented as Tailwind
  `text-*` utility aliases (`text-display`, `text-page-title`, etc.) in
  `tailwind.config.ts`, not ad-hoc `text-[32px]` in components.

## 4. Spacing, Radius, Shadow

- Spacing scale: Tailwind default 4px base unit, used consistently
  (no arbitrary `px-[13px]` values).
- Radius tokens: `--radius-sm` (6px), `--radius-md` (8px), `--radius-lg`
  (12px) — buttons/inputs use `sm`, cards use `md`, modals use `lg`.
- Shadow tokens: `--shadow-sm`, `--shadow-md`, `--shadow-lg` for
  elevation (cards, dropdowns, modals respectively).

## 5. Breakpoints (spec section 119)

| Name | Range |
|---|---|
| mobile | `<640px` |
| tablet | `640–1024px` |
| desktop | `1024–1440px` |
| large | `1440px+` |

Large-desktop content is capped at `max-width: 1600px` and centered
(spec section 121) — it never stretches full-bleed on ultra-wide
monitors.

## 6. Component States

Every interactive component (see `COMPONENT_INVENTORY.md`) is built to
support: **default, hover, focus, active, disabled, loading, error,
success** — enforced by building each primitive once in
`frontend/src/components/ui/` on top of Radix (which gives correct
focus/keyboard/ARIA behavior for free) and Tailwind variants, rather
than re-implementing state handling per screen.

## 7. Motion

Subtle only: fade/slide/scale on enter-exit, hover micro-transitions.
Everything respects `prefers-reduced-motion` (global CSS guard disables
non-essential transitions). ERP screens are intentionally calm — no
decorative animation on data tables or forms (spec section 122).

## 8. Accessibility Baseline

Target WCAG 2.2 AA where practical (spec section 123): all primitives
come from Radix for correct keyboard/focus/ARIA semantics; color pairs
in both themes are chosen to meet 4.5:1 contrast for body text; focus
rings use `--color-primary` at full opacity, never `outline: none`
without a visible replacement.

## 9. What This Session Implements

Tokens, theme.css (light+dark), typography setup (Inter + Noto Sans
Bengali), and the first tranche of primitives (Button, Input, Label,
Card, Badge, Avatar, Separator, Sidebar/Topbar shell) — enough to build
the login page and dashboard shell. The remaining primitives in
`COMPONENT_INVENTORY.md` are added as the phases that need them land,
so nothing ships half-wired.
