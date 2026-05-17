---
tela: Ponto/Importacoes/Create
controller: PontoWr2\Http\Controllers\ImportacoesController@create/store (inferido)
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
- Inertia::defer: 0 props (form puro, sem props server)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, FileUp, Info, Upload) + useForm + Alert/Card/Input/Label/Select
- `forceFormData: true` (upload AFD)

**Risco prévio:**
- Charter ausente
- Upload de arquivo grande (AFD pode chegar a 50MB+) — sem `progress` callback do useForm pra mostrar % subida
- Sem `accept` no `<Input type="file">` (precisa ver source completo) — pode aceitar qualquer arquivo
- Sem validação client-side de extensão (.txt para AFD)

**Smoke pendente:**
- screenshot 1440 + 1280
- upload arquivo 5MB → progresso visível?
- arquivo errado (PDF) → erro friendly?
- responsivo form 1280

**Decisão Wagner:** [pendente]
