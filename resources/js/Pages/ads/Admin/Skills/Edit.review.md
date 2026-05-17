---
tela: ads/Admin/Skills/Edit
controller: ADS\Http\Controllers\Admin\SkillsController@edit (inferido)
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
- Inertia::defer: 0 props (1 skill + frontmatterYaml + currentVersion — eager ok)
- localStorage: ausente
- Tailwind tokens canon: ok
- useMemo/useCallback: 0
- Imports: lucide (ArrowLeft, Save, AlertCircle) + useForm + shadcn (Card/Badge/Button/Textarea/Label)

**Risco prévio:**
- Charter ausente
- `frontmatter_yaml` editado como string em `<Textarea>` — sem syntax highlighting YAML (recomenda Monaco/CodeMirror lazy)
- `body_markdown` em Textarea — sem preview (recomenda split-view ou tab preview)
- Rationale 4 campos (problem/hypothesis/success_metric/rollback) — bom desenho governança
- Sem auto-save draft (perde tudo se F5)

**Smoke pendente:**
- screenshot 1440 + 1280
- YAML inválido — erro inline?
- responsivo textarea grande em 1280
- F5 acidental — perda confirmada?

**Decisão Wagner:** [pendente — futuro CodeMirror + preview Markdown]
