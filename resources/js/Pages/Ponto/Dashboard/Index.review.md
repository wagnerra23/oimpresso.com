---
tela: Ponto/Dashboard/Index
controller: PontoWr2\Http\Controllers\DashboardController (inferido)
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
- Inertia::defer: 0 props (`kpis` + `aprovações` + `marcações` + presence — TODOS candidatos a defer; dashboard é a tela #1 que se beneficia)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowRight, AlertTriangle, CheckCheck) + shared/ponto/* (PresenceStrip, ActivityFeed, AlertInbox) + shared/* (KpiGrid, KpiCard, StatusBadge)
- `useEffect` no início (provável polling/refresh)

**Risco prévio:**
- Charter ausente
- **Hot path**: dashboard é a primeira tela após login. 4+ widgets eager (KPIs + 3 listas) pode passar 800ms first-paint
- `PresenceStrip` + `ActivityFeed` + `AlertInbox` cada um consome props que cheiram a `count()`/`with()` — RUNBOOK Inertia::defer aplica
- `useEffect` no início precisa cleanup confirmado (sem leak interval)

**Smoke pendente:**
- screenshot 1440 + 1280
- **Performance API mark first-paint** (target ≤800ms)
- teste polling — interval para ao navegar?
- console errors em PresenceStrip se zero colaboradores presentes

**Decisão Wagner:** [pendente]
