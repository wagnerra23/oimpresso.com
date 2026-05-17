---
tela: ads/Admin/Decisoes
controller: ADS\Http\Controllers\Admin\DecisoesController@index (inferido)
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
- Inertia::defer: 0 props (`decisions: Decision[]` + `kpis` — provavelmente paginado server-side mas vem inteiro?)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (CheckCircle2, XCircle, ShieldAlert, Clock, Archive, ExternalLink) + shared (PageHeader, KpiGrid, KpiCard, StatusBadge, EmptyState)
- 4 tabs estado (`tab: 'pendentes' | 'em_andamento' | 'subtarefas' | 'historico'`)
- **useEffect + useRef + useState** → provavelmente polling ou keyboard shortcuts

**Risco prévio:**
- Charter ausente
- 4 tabs com props inteiras eager — Controller traz tudo? Recomenda tab-specific endpoints + defer
- Hot path (DecisoesIndex = tela operacional ADS)
- `useEffect + useRef` precisa cleanup confirmado

**Smoke pendente:**
- screenshot 1440 + 1280 em cada tab
- Performance API mark
- alternar tabs — Network refresh ou client-side filter?
- empty state cada tab

**Decisão Wagner:** [pendente — tela crítica ADS, defer + sortable obrigatório]
