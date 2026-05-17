---
tela: Produto/StockHistory
controller: App\Http\Controllers\ProductController@stockHistory
charter: ./StockHistory.charter.md
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

# Screen Review — Produto/StockHistory

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@stockHistory` usa `Inertia::defer` ✓
- Histórico movimentação estoque — append-only (auditável) tabela cronológica + filtros tipo movimento

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (lista + defer + paginação)
- console errors: 0 esperado
- 1440/1280 sem scroll: tabela cronológica deve caber

**Desvios potenciais:**
- Range histórico longo (default 30d?)
- Drill-down movimento → venda/compra origem
- Multi-tenant: histórico só do produto do business

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant cross-biz, append-only (zero UPDATE/DELETE permitido)

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
