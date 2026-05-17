---
tela: Ponto/Importacoes/Index
controller: PontoWr2\Http\Controllers\ImportacoesController@index (inferido)
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
- Inertia::defer: 0 props (`importacoes` paginated → leve eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok (`formatBytes`)
- useMemo/useCallback: 0
- Imports: lucide (ArrowRight, Plus) + shared (PageHeader, StatusBadge, EmptyState)
- `normalizeEstado()` helper local pra mapear ESTADO_PENDENTE → kind do StatusBadge

**Risco prévio:**
- Charter ausente
- `normalizeEstado` helper inline — deveria viver em `_lib/` ou `StatusBadge` aceitar nativamente (consistência)
- Lista sem auto-refresh — se usuário fez upload e fica na tela, não vê processamento concluindo (precisa F5)

**Smoke pendente:**
- screenshot 1440 + 1280
- upload + voltar pra Index — refresh manual mostra novo status?
- empty state aparece bonito quando primeira vez

**Decisão Wagner:** [pendente — recomendar polling 5s se houver registro PROCESSANDO]
