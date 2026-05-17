---
tela: Produto/Unificado/Index
controller: App\Http\Controllers\ProdutoUnificadoController@index
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

# Screen Review — Produto/Unificado/Index

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProdutoUnificadoController@index` — **NÃO usa `Inertia::defer`** ⚠ (canon `inertia-defer-default` violado)
- Tela unificada produto (provavelmente fundir variações + estoque + preço numa visão única — workflow vestuário ROTA LIVRE?)

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: ⚠ risco alto — visão unificada agrega N queries; sem defer = first_paint inflado
- console errors: 0 esperado
- 1440/1280 sem scroll: validar layout unificado

**Desvios potenciais:**
- **Inertia::defer ausente** — múltiplas queries agregadas servidas eager (CRITICAL fix pré-Round 2)
- Multi-tenant: produtos por business
- Workflow específico vertical Vestuario (cliente piloto Larissa biz=4)

**Pest GUARD pendente:**
- Defer obrigatório (recomendar fix), RBAC, multi-tenant, validação biz=4 ROTA LIVRE caso piloto

**Decisão Wagner:** [pendente] — recomendar fix `Inertia::defer` na agregação pesada antes do Round 2.

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
