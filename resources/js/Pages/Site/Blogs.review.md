---
tela: Site/Blogs
controller: Modules\Cms\Http\Controllers\CmsController@getBlogList
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

# Screen Review — Site/Blogs

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Blogs.charter.md` pré-req Round 2.
- Controller: `CmsController@getBlogList` (via `SiteContentService`) usa `Inertia::defer` ✓
- Listagem blogs públicos — grid cards + paginação + filtro categoria/tag

**Smoke browser MCP:** **pendente** (página pública).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (lista + defer)
- console errors: 0 OBRIGATÓRIO
- 1440/1280 sem scroll: grid cards responsivo (validar 768/375 mobile também)

**Desvios potenciais:**
- Imagens cards (lazy load obrigatório)
- SEO: paginação `rel=prev/next`
- Filtros via query string (URL shareable)

**Pest GUARD pendente:**
- Lista pública (sem auth), paginação, sem business_id leak

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
