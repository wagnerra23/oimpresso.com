---
tela: Produto/SellingPrices
controller: App\Http\Controllers\ProductController@sellingPrices
charter: ./SellingPrices.charter.md
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

# Screen Review — Produto/SellingPrices

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@sellingPrices` usa `Inertia::defer` ✓
- Gestão preços-de-venda — possivelmente grid N produtos × M grupos preço (matriz densa)

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: ⚠ risco — matriz N×M pesada
- console errors: 0 esperado
- 1440/1280 sem scroll: matriz quase certa scroll horizontal 1280

**Desvios potenciais:**
- Edição inline bulk (estado UI pesado)
- Cálculo margem automático (form derivado)
- Multi-tenant: grupos preço por business

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant, edição persiste corretamente

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
