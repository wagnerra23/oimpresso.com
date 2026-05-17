---
tela: ads/Admin/Metricas
controller: ADS\Http\Controllers\Admin\MetricasController (inferido)
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
- Inertia::defer: 0 props (`kpis` ~10 agregados + `distribuicao` + `por_dominio` + `por_event_type` — TODOS agregados COUNT/SUM, defer crítico)
- localStorage: ausente
- Tailwind tokens canon: **misto** — `bg-emerald-500`/`bg-blue-500`/`bg-red-500`/`bg-amber-500` em `StackedBar`
- useMemo/useCallback: 0
- Imports: shared (PageHeader, KpiGrid, KpiCard, EmptyState) + Card/Badge — zero lucide

**Risco prévio:**
- Charter ausente
- **ALTO custo backend**: 4 SUMs + 2 GROUP BY (por_dominio, por_event_type) sem defer — em ADS maduro pode passar 1s
- Cores hardcoded `bg-red-500` viola design-system tokens semânticos (red-500 funciona dark/light por sorte, mas não é canon)
- USD formatter ok pra `custo_total_usd`

**Smoke pendente:**
- screenshot 1440 + 1280
- **Performance API mark** — tela mais agregadora ADS
- StackedBar responsivo 1280

**Decisão Wagner:** [pendente — defer obrigatório nas 4 props pesadas]
