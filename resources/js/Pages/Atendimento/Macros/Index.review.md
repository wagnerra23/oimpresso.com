---
tela: Atendimento/Macros/Index
controller: Modules\Whatsapp\Http\Controllers\Admin\MacrosController@index
charter: ./Index.charter.md
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

# Screen Review — Atendimento/Macros/Index

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `MacrosController@index` usa `Inertia::defer` ✓
- Tela lista de macros (respostas rápidas) — provavelmente tabela + filtros categoria

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta — lista simples + defer
- console errors: 0 esperado
- 1440/1280 sem scroll: tabela costuma caber

**Desvios potenciais:**
- Estado quando 0 macros (empty state copy)
- Paginação/scroll virtual em listas grandes (>500 macros)
- Multi-tenant: macros por business

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant, vazio

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
