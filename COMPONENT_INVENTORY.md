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
| Dropdown Menu | ✅ | used in topbar (profile, theme) |
| Sheet (drawer) | ✅ | mobile sidebar |
| Select | ⏳ | Phase 5 (catalog forms) |
| Textarea | ⏳ | Phase 5 |
| Checkbox | ⏳ | Phase 5 (bulk table selection) |
| Radio | ⏳ | Phase 5 |
| Switch | ⏳ | Phase 5 (settings toggles) |
| Tooltip | ⏳ | Phase 5 |
| Modal/Dialog | ⏳ | Phase 5 |
| Tabs | ⏳ | Phase 5 (product editor tabs) |
| Accordion | ⏳ | Phase 5/15 (FAQ, SEO analysis groups) |
| Table / DataTable | ⏳ | Phase 5 (first real list screen: products) |
| Pagination | ⏳ | ships with DataTable |
| Breadcrumb | ✅ | topbar |
| Toast | ✅ | global toaster for mutations |
| Timeline | ⏳ | Phase 8 (order timeline) |
| Chart | ⏳ | Phase 11 (dashboard KPI charts, Recharts) |
| Date Picker | ⏳ | Phase 5/8 |
| Command Palette | ⏳ | Phase 5+ (needs a searchable resource first) |
| File Upload | ⏳ | Phase 5 (media library) |
| Rich Editor (TipTap) | ⏳ | Phase 14 (blog) |
| Stat Card | ✅ | dashboard KPI cards |
| Empty State | ✅ | generic empty-state component (icon/title/description/CTA) |

## Layout Components (`frontend/src/components/layout/`)

| Component | Status | Notes |
|---|---|---|
| AdminSidebar | ✅ | collapsible, responsive drawer below 768px |
| AdminTopbar | ✅ | breadcrumb, theme toggle, profile menu, notifications placeholder (disabled — no notifications backend yet, so the bell is present but intentionally shows an empty state rather than fake data) |
| AdminShell | ✅ | composes sidebar+topbar+content, persists sidebar collapsed state |
| StorefrontHeader/Footer | ⏳ | Phase 16 |
| BuilderCanvas / BuilderPanel | ⏳ | Phase 13 |

## Charts (`frontend/src/components/charts/`)

All ⏳ — Phase 11 (Dashboard) is the first consumer.

## Rule Followed

No component above marked ⏳ is referenced anywhere in the shipped
code this session. A component only appears in the tree once the
screen using it has real data behind it (spec rule 178: no fake
functionality, no dead buttons).
