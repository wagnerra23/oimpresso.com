---
tela: ads/Admin/Skills/Show
controller: ADS\Http\Controllers\Admin\SkillsController@show (inferido)
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
- Inertia::defer: 0 props (1 skill + versions timeline + editable flag)
- localStorage: ausente
- Tailwind tokens canon: ok (`statusVariant` map usa variants shadcn ✓)
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, ExternalLink, Pencil, History, Play, Upload, GitBranch) + **react-markdown + remark-gfm** + shadcn (Card/Badge/Button)

**Risco prévio:**
- Charter ausente
- `react-markdown + remark-gfm` bundle cost — verificar code-split
- `versions: Version[]` timeline — em skill antiga 50+ versions vira lista longa (paginate ou collapse antigas)
- Markdown body renderizado XSS? react-markdown sanitiza por default ✓

**Smoke pendente:**
- screenshot 1440 + 1280
- skill com 20+ versions — visual scroll
- bundle analyzer markdown impact

**Decisão Wagner:** [pendente]
