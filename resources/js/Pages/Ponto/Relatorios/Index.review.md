---
tela: Ponto/Relatorios/Index
controller: PontoWr2\Http\Controllers\RelatoriosController@index (inferido)
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
- Inertia::defer: 0 props (`relatorios` array config estático — eager perfeito)
- localStorage: ausente
- Tailwind tokens canon: ok (CorKey `'blue' | 'emerald' | 'amber' | 'red' | 'violet'`)
- useMemo/useCallback: 0
- Imports: lucide (AlertCircle, ClipboardList, Clock, FileCheck, FileSpreadsheet, FileText, PiggyBank, Send) + Card/Badge/Button

**Risco prévio:**
- Charter ausente
- Catálogo de relatórios — risco perf baixo
- `disponivel: boolean` mostra badge "Em breve" ou similar — bom signaling
- Cores hardcoded em `CorKey` — vir como tokens semânticos seria mais consistente design-system

**Smoke pendente:**
- screenshot 1440 + 1280
- click relatório disponivel=false → desabilitado visual claro?
- responsivo 1280 — grid mantém 2-3 colunas?

**Decisão Wagner:** [pendente]
