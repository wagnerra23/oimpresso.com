---
tela: ads/Admin/Skills/Test
controller: ADS\Http\Controllers\Admin\SkillsTestController (inferido)
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
- Inertia::defer: 0 props (recentRuns pode crescer — candidato defer)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, Play, Clock, Hash, ShieldAlert) + useForm + shadcn (Card/Badge/Button/Textarea)
- `dryRun` flag — bom signaling pra desenvolvedor

**Risco prévio:**
- Charter ausente
- Test invoca LLM (provavelmente) — **custo IA** precisa estar tracked (ADR 0094 §4) + `<feature>_FORCE_MOCK` em test env
- `source: 'manual' | 'real_conversations'` — `real_conversations` provavelmente abre PII; precisa LGPD review
- `pii_count` na lista runs — boa visibilidade

**Smoke pendente:**
- screenshot 1440 + 1280
- `dryRun=true` UI mostra banner claro?
- run com prompt longo — UI scroll OK?
- recent runs >10 — pagination ou só limit?

**Decisão Wagner:** [pendente — verificar `<feature>_FORCE_MOCK` em test]
