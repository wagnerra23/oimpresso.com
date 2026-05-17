---
tela: Ponto/Configuracoes/Reps
controller: PontoWr2\Http\Controllers\ConfiguracoesRepsController (inferido)
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
- Inertia::defer: 0 props (`reps` paginated → candidato leve)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Comentário TODO no source: `// TODO inertia-v3: revisar timing reset (agora so no onFinish)` — débito declarado
- Imports: lucide (ArrowLeft, Plus, Server) + useForm + shadcn (Badge/Button/Card/Input/Label/Select)

**Risco prévio:**
- Charter ausente
- TODO inertia-v3 não resolvido — form reset timing pode causar UX glitch em criação consecutiva (precisa smoke pra confirmar)
- CNPJ sem mask de input (formatação só server-side)

**Smoke pendente:**
- screenshot 1440 + 1280
- criar REP, depois criar outro rápido — form reseta antes do toast?
- CNPJ inválido validação server-side

**Decisão Wagner:** [pendente]
