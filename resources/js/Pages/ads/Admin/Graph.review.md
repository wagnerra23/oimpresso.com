---
tela: ads/Admin/Graph
controller: ADS\Http\Controllers\Admin\GraphController (inferido)
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
- Inertia::defer: 0 props (`nodes` + `edges` — graph pode ter 500+ nós em ADS maduro, defer crítico)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: **1 useMemo** (layout determinístico — bom)
- Imports: ReactFlow + Background/Controls/MiniMap + lucide (Brain, Zap, Wrench, Shield, Database) + shared (PageHeader, KpiGrid, KpiCard)
- **`data: any`** em GraphNode — tipagem fraca

**Risco prévio:**
- Charter ausente
- ReactFlow é pesado (~150KB bundle) — verificar se está code-split
- Graph layout determinístico O(N) — bom mas se nodes > 500 fica visual confuso
- `data: any` — TypeScript lint provavelmente warning

**Smoke pendente:**
- screenshot 1440 + 1280
- bundle size impact (Vite analyzer)
- graph com 100+ nodes → frame rate
- MiniMap útil em 1280?

**Decisão Wagner:** [pendente — verificar lazy import ReactFlow]
