---
tela: Ponto/Espelho/Show
controller: PontoWr2\Http\Controllers\EspelhoController@show (inferido)
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
- Inertia::defer: 0 props (espelho mensal com `totais` agregados + `marcacoes/dias` muito provável paginado → ALTO candidato a defer)
- localStorage: ausente
- Tailwind tokens canon: ok (`cn`, `formatMinutes`)
- useMemo/useCallback: 0
- Imports: lucide (AlertTriangle, ArrowLeft, ChevronLeft/Right, ClipboardList, Printer) + shared/ponto/MonthHeatmap

**Risco prévio:**
- Charter ausente
- **Hot tela**: espelho mensal calcula HE diurna/noturna/DSR — Service tem SOMA por dia 30+ vezes. Sem defer = first-paint pesado
- `MonthHeatmap` precisa array de 30 dias com cor — se Controller calcula tudo eager, latência grande
- Botão `Printer` deveria abrir window.print() limpo (sem AppShell) — precisa smoke pra confirmar print CSS

**Smoke pendente:**
- screenshot 1440 + 1280
- **Performance API mark** — espelho é a tela mais cara do módulo Ponto
- print preview (Ctrl+P) — layout legível?
- navegação mês anterior/próximo preserva colaborador
- divergências amarelas com `AlertTriangle` visíveis em 1280

**Decisão Wagner:** [pendente — defer aplicável obrigatório se latência >800ms]
