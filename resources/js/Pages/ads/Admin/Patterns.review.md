---
tela: ads/Admin/Patterns
controller: ADS\Http\Controllers\Admin\PatternsController (inferido)
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
- Inertia::defer: 0 props (`patterns` + `candidates` + `drifts` — 3 arrays, Wilson Score calculado backend)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (Lightbulb, AlertTriangle, Lock) + shared (PageHeader, KpiGrid, KpiCard, EmptyState)

**Risco prévio:**
- Charter ausente
- Wilson Score calc backend pesa (depende sample_size) — Service pode ter cache mas precisa confirmar
- 3 arrays sem defer — `candidates` (promotion-ready) e `drifts` envolvem comparar histórico
- Sem ordenação por `wilson_lower_bound` visível

**Smoke pendente:**
- screenshot 1440 + 1280
- click "promote candidate" se houver botão — UX confirma?
- drifts visual destaque vermelho?

**Decisão Wagner:** [pendente]
