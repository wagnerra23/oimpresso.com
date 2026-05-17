---
tela: Ponto/Colaboradores/Edit
controller: PontoWr2\Http\Controllers\ColaboradoresController@edit (inferido)
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
- Inertia::defer: 0 props (Edit puro — `colaborador` + `escalas` list, ambos esperados eager)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, Save, UserCog) + useForm + shadcn (Card/Input/Label/Select/Switch)

**Risco prévio:**
- Charter ausente
- `escalas: Array<{id, nome, tipo}>` eager — se business tem 100+ escalas vira `<SelectItem>` × 100 (no caso é improvável, mas vale conferir)
- CPF/PIS sem mask (formatação BR só em validação server)
- Dois `<Switch>` sem `aria-describedby` — accessibility-review recomenda

**Smoke pendente:**
- screenshot 1440 + 1280
- teste validação CPF inválido (mostra erro inline?)
- focus order tab-keyboard

**Decisão Wagner:** [pendente]
