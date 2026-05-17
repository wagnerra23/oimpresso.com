---
tela: Atendimento/JanaTemplates
controller: Modules\Whatsapp\Http\Controllers\Admin\SettingsController@janaTemplates
charter: ./JanaTemplates.charter.md
current_round: 1
status: awaiting-smoke-browser
created_at: 2026-05-17
ux_targets:
  first_paint_ms: 800
  fcp_ms: 1200
  no_console_errors: true
  responsive_1440_no_scroll_horizontal: true
  responsive_1280_no_scroll_horizontal: true
---

# Screen Review — Atendimento/JanaTemplates

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `SettingsController@janaTemplates` — **NÃO usa `Inertia::defer`** ⚠ (risco MWART canon `inertia-defer-default`)
- Tela de templates IA Jana — edição/preview/categorização de prompts

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: ⚠ risco se templates query pesada (lista + variantes) — defer NÃO aplicado
- console errors: 0 esperado
- 1440/1280 sem scroll: validar editor + preview lado a lado

**Desvios potenciais:**
- **Inertia::defer ausente** — props possivelmente pesadas servidas eager → first_paint inflado
- Editor markdown/jinja (lib externa) check bundle
- Multi-tenant: templates por business (verificar scope)

**Pest GUARD pendente:**
- Defer obrigatório em listagem templates (fix recomendado pré Round 2), RBAC IsAdmin, multi-tenant

**Decisão Wagner:** [pendente] — recomendar fix `Inertia::defer` na props pesada antes do Round 2.

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
