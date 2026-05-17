---
tela: ads/Admin/MetaSkills
controller: ADS\Http\Controllers\Admin\MetaSkillsController (inferido)
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
- Inertia::defer: 0 props (`rules_by_category` agregado — defer aplicável se >50 rules)
- localStorage: ausente
- Tailwind tokens canon: ok no header
- useMemo/useCallback: 0
- Imports: lucide (Plus, Play, X, CheckCircle2) + useForm + shadcn (Card/Badge/Switch/Button/Input/Textarea/Label) + shared (PageHeader, KpiGrid, KpiCard, EmptyState)
- `useState` pra forms inline

**Risco prévio:**
- Charter ausente
- `condition: any` + `action: { type: string; params?: any }` — schema flexível, frontend precisa validar
- `categoryLabel` map estático em PT-BR (bom)
- Sem ordenação por `triggered_count` visível (pode ser feature pendente)

**Smoke pendente:**
- screenshot 1440 + 1280
- toggle Switch enabled — optimistic UI? rollback se 500?
- click Play (test rule) — feedback claro

**Decisão Wagner:** [pendente]
