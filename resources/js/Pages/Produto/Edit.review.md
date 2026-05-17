---
tela: Produto/Edit
controller: App\Http\Controllers\ProductController@edit
charter: ./Edit.charter.md
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

# Screen Review — Produto/Edit

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@edit` usa `Inertia::defer` ✓
- Form editar produto (espelha Create) — pre-fill de produto existente + histórico changes possível

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (same as Create + pre-fill query)
- console errors: 0 esperado
- 1440/1280 sem scroll: same risco Create

**Desvios potenciais:**
- Dirty form warning ao navegar away
- Histórico changes (audit) se exposto na tela
- Multi-tenant: produto ID autorizado ao business (CRITICAL — bug clássico cross-tenant)
- Variações já existentes preservadas (não duplicar)

**Pest GUARD pendente:**
- Defer, RBAC, multi-tenant cross-biz (produto biz=99 NÃO acessível biz=1), 404 ID inexistente, dirty preservation

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
