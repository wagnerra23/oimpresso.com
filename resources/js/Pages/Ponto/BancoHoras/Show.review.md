---
tela: Ponto/BancoHoras/Show
controller: PontoWr2\Http\Controllers\BancoHorasController@show (inferido)
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
- Charter: ausente — recomenda criar
- AppShellV2: sim
- Inertia::defer: 0 props (`movimentos` paginado é candidato óbvio)
- localStorage: ausente
- Tailwind tokens canon: ok (`cn`, `formatMinutes`, `tipoVariant` map)
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, Info, PiggyBank, Save) + useForm + Alert/Badge/Card/Input/Label/Textarea

**Risco prévio:**
- Charter ausente
- `movimentos` (ledger append-only por business+colab) sem defer — em colaborador 5+ anos pode ser 5k+ rows, paginado mas Controller ainda roda count() pra paginação
- Formulário ajuste manual sem `disabled={form.processing}` checado (precisa smoke pra ver double-submit)

**Smoke pendente:**
- screenshot 1440 + 1280
- teste submit form com `data_referencia` no futuro (validation server-side)
- responsivo card "Saldo atual" empilha bonito em 1280

**Decisão Wagner:** [pendente]
