---
tela: Ponto/Intercorrencias/Create
controller: PontoWr2\Http\Controllers\IntercorrenciasController@create (inferido)
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
- Inertia::defer: 0 props (`colaboradores` list + `tipos` lookup → list pode crescer)
- localStorage: ausente
- Tailwind tokens canon: ok (`cn`)
- useMemo/useCallback: 0
- Imports: lucide (AlertTriangle, ArrowLeft, Loader2, Save, **Sparkles, Zap**) + useForm + Alert/Card/Input/Label/Select/Textarea
- **Sparkles/Zap** sugerem botão "sugerir tipo via IA" (Jana?) — feature exploratória

**Risco prévio:**
- Charter ausente
- `colaboradores: Colaborador[]` (não paginated) — em business 500+ vira `<SelectItem>` 500× (lag select dropdown)
- Recomenda Combobox com busca (cmdk) em vez de Select nativo
- Se Sparkles chama LLM → custo IA precisa estar tracked (ADR 0094 §4 — `<feature>_FORCE_MOCK=true` em test)

**Smoke pendente:**
- screenshot 1440 + 1280
- abrir select colaboradores — performance render?
- click Sparkles/Zap → comportamento (chama API? mock?)
- submit form com `justificativa` vazia → validation inline

**Decisão Wagner:** [pendente — provável recomendar Combobox + cap colaboradores via API search]
