---
tela: Ponto/Importacoes/Show
controller: PontoWr2\Http\Controllers\ImportacoesController@show (inferido)
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
- Inertia::defer: 0 props (1 objeto `importacao` — eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok (`formatBytes`)
- useMemo/useCallback: 0
- Imports: lucide (AlertTriangle, ArrowLeft, Download, FileUp) + Alert/Badge/Button/Card
- **`useEffect` polling 3s** enquanto `ESTADO_PROCESSANDO|PENDENTE` via `router.reload({only:['importacao']})` — `Inertia::defer` style partial reload

**Risco prévio:**
- Charter ausente
- Polling 3s sem backoff exponencial — em conexão instável dispara muito request
- Sem `visibilitychange` listener — polling roda em background tab desnecessariamente
- `router.reload({only:[...]})` é eficiente (partial reload), mas Controller precisa ter `Inertia::defer` na prop `importacao` pra ser realmente leve — atualmente provavelmente eager

**Smoke pendente:**
- screenshot 1440 + 1280
- Network tab DevTools — polling 3s confirma
- abrir tab em background — polling pausa? (provavelmente não, sem listener)
- erro download (404) UI graceful?

**Decisão Wagner:** [pendente — recomendar pausar polling em background tab + backoff]
