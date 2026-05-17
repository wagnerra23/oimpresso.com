---
tela: ads/Admin/ProjectShow
controller: ADS\Http\Controllers\Admin\ProjectsController@show (inferido)
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
- Inertia::defer: 0 props (1 projeto + parts + métricas — eager faz sentido)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, Zap, ArrowRight) + shared (PageHeader, KpiGrid, KpiCard, EmptyState) + Card/Badge/Button

**Risco prévio:**
- Charter ausente
- `constraints: any` + `arquivos_estimados: string[]` — paths podem ser longos, precisa truncate UI
- `dependencias: number[]` requer lookup outros parts pra mostrar nome (provavelmente eager-load)
- Sem visual de dependency graph (poderia ser bonito mas overengineering pra V1)

**Smoke pendente:**
- screenshot 1440 + 1280
- projeto sem parts (draft) UI graceful?
- viability_score colorização semântica

**Decisão Wagner:** [pendente]
