---
tela: Ponto/BancoHoras/Index
controller: PontoWr2\Http\Controllers\BancoHorasController (inferido)
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
- Charter: ausente — recomenda criar `Index.charter.md`
- AppShellV2: sim
- Inertia::defer: 0 props (Paginated `saldos` + Totais agregados → candidatos óbvios a defer)
- localStorage prefix: ausente
- Tailwind tokens canon: ok (`cn`, `formatMinutes`)
- useMemo/useCallback: 0
- Imports: lucide-react (ArrowRight, PiggyBank) + shared/* (PageHeader, KpiGrid, EmptyState)

**Risco prévio (sem browser):**
- Charter ausente
- Props `saldos` (paginated) + `totais` (4 agregados) sem `Inertia::defer` — provável SUM(saldo_minutos) varrer tabela `banco_horas_movimentos` (append-only, cresce). Em business grande pode passar 300ms
- `KpiGrid` com 4 cards renderizados eager; se Controller faz 4 COUNT/SUM separados, every-row latência somada

**Smoke pendente:**
- screenshot 1440 + 1280, console errors, first-paint
- query plan EXPLAIN nos totais (Wagner pode rodar)
- teste responsivo paginação rodapé

**Decisão Wagner:** [pendente]
