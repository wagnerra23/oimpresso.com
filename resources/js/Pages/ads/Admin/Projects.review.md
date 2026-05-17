---
tela: ads/Admin/Projects
controller: ADS\Http\Controllers\Admin\ProjectsController@index (inferido)
charter: null
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
created_by: bulk-w31-agent-static
ux_targets:
  first_paint_ms: 800
  no_console_errors: true
  responsive_1440: true
  responsive_1280: true
---

## Round 1 — 2026-05-17 (bulk review estática)

**Status:** awaiting-smoke-browser

**Análise estática:**
- Charter: ausente
- AppShellV2: sim
- Inertia::defer: 0 props (`projects: Project[]` + kpis — sem paginação visível?)
- localStorage: ausente
- Tailwind tokens canon: **misto** — `statusColor` map hardcoded `bg-zinc-100`/`bg-blue-100`/etc
- useMemo/useCallback: 0
- Imports: lucide (Plus, X) + shared (PageHeader, KpiGrid, KpiCard, EmptyState) + useForm + shadcn (Card/Button/Badge/Input/Textarea/Label)
- `useState` pra form inline

**Risco prévio:**
- Charter ausente
- Lista sem paginação aparente — se >50 projetos render fica pesado
- `progress_pct` calc backend (parts_done/parts_total × 100) ok
- BRL formatter helper local — bom; dataset `null` handled

**Smoke pendente:**
- screenshot 1440 + 1280
- criar projeto inline (form embarcado?) UX feel
- many projects (50+) scroll perf

**Decisão Wagner:** [pendente — paginar se >30]
