---
tela: Produto/Create
controller: App\Http\Controllers\ProductController@create
charter: ./Create.charter.md
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

# Screen Review — Produto/Create

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✓ presente
- Controller: `ProductController@create` usa `Inertia::defer` ✓
- Form criar produto — campos densos (SKU, nome, categoria, fiscal, variações, imagens, estoque inicial)

**Smoke browser MCP:** **pendente**.

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança média (form denso + dropdowns categorias/marcas pesados)
- console errors: 0 esperado
- 1440/1280 sem scroll: forms costumam quebrar em 1280 se 2-col rígido

**Desvios potenciais:**
- Upload imagens drag-drop (preview)
- Variações combinatórias (cor × tamanho — UI matriz pesada)
- Fiscal NCM/CEST/CFOP (autocomplete service externo?)
- Validação inline vs submit
- Multi-tenant: business_id assumido sessão

**Pest GUARD pendente:**
- Defer, RBAC (criar), multi-tenant, validação SKU único per business

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
