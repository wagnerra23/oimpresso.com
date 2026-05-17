---
tela: Atendimento/Macros/Variants
controller: Modules\Whatsapp\Http\Controllers\Admin\MacroVariantsController@index
charter: null
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

# Screen Review — Atendimento/Macros/Variants

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Variants.charter.md` pré-req Round 2.
- Controller: `MacroVariantsController@index` usa `Inertia::defer` ✓
- Tela de variantes A/B-test de macros — edição + preview de variantes; possivelmente split-test metrics

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (variantes + métricas histórico)
- console errors: 0 esperado
- 1440/1280 sem scroll: validar layout variantes lado a lado

**Desvios potenciais:**
- A/B split-test metrics (charts ou agregações DB)
- Workflow ativar/desativar variante (estado intermediário)
- Multi-tenant: variantes por macro do business

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant cross-biz, vazio (0 variantes)

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
