---
tela: Produto/Index
controller: App\Http\Controllers\ProductController@index
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

# Screen Review — Produto/Index

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@index` usa `Inertia::defer` ✓ (5 defers no controller total)
- Listagem master de produtos (DataTable canon UltimatePOS migrado p/ Inertia/React) — filtros categoria/marca/SKU/status, ações em massa

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média — lista grande pode ter milhares produtos (paginação obrigatória)
- console errors: 0 esperado
- 1440/1280 sem scroll: tabela densa com muitas colunas (SKU/nome/categoria/preço/estoque/ações) — risco real scroll horizontal 1280

**Desvios potenciais:**
- Scroll horizontal 1280 quase certo se colunas não responsivas
- Bulk actions seleção (estado UI)
- Filtros complexos (combobox categorias árvore?)
- Multi-tenant: produtos por business (core scope)

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant biz=1 vs biz=99, paginação, bulk action seleção limpa após ação

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
