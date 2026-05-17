---
tela: Produto/Show
controller: App\Http\Controllers\ProductController@show
charter: ./Show.charter.md
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

# Screen Review — Produto/Show

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@show` usa `Inertia::defer` ✓
- Detalhe read-only do produto — sumário + variações + estoque por local + histórico movimento

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (read-only + defer)
- console errors: 0 esperado
- 1440/1280 sem scroll: validar tabs ou seções colapsáveis

**Desvios potenciais:**
- Histórico movimento estoque (defer obrigatório se >30d range)
- Multi-tenant: cross-biz check
- Imagens galeria carregamento progressivo

**Pest GUARD pendente:**
- Defer, RBAC visualizar, multi-tenant cross-biz, 404

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
