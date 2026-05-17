---
tela: Ponto/Intercorrencias/Show
controller: PontoWr2\Http\Controllers\IntercorrenciasController@show (inferido)
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
- Inertia::defer: 0 props (1 objeto `intercorrencia` — eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok (`estadoVariant` map)
- useMemo/useCallback: 0
- Imports: lucide (AlertTriangle, ArrowLeft, Check, Send, X, XCircle) + Alert/Badge/Button/Card
- **`window.confirm()`** nativo no `submeter()` — UX inferior a `AlertDialog` shadcn

**Risco prévio:**
- Charter ausente
- `confirm()` nativo browser quebra estética (sem dark mode, sem PT-BR garantido em alguns OS) — recomenda AlertDialog shadcn
- Action `submeter` envolve mudança de estado — sem audit log visível na própria tela (precisa scroll pro fim?)

**Smoke pendente:**
- screenshot 1440 + 1280
- click submeter → confirm nativo aparece (UX feel?)
- timeline aprovação visível?
- responsivo botões 1280 — quebra linha bonito?

**Decisão Wagner:** [pendente — recomendar substituir confirm() por AlertDialog]
