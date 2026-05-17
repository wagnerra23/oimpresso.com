---
tela: Site/BlogPost
controller: Modules\Cms\Http\Controllers\CmsController@getBlogPost
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

# Screen Review — Site/BlogPost

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `BlogPost.charter.md` pré-req Round 2.
- Controller: `CmsController` (linha 228) usa `Inertia::defer` ✓
- Post individual blog — markdown render + author + share + related posts

**Smoke browser MCP:** **pendente** (público).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta
- console errors: 0 OBRIGATÓRIO
- 1440/1280/768/375 sem scroll: artigo deve fluir natural

**Desvios potenciais:**
- Syntax highlight code blocks (Prism/Shiki — bundle peso)
- Imagens inline lazy
- SEO meta tags (og:image, canonical, schema.org Article)
- Related posts (defer obrigatório se query pesada)

**Pest GUARD pendente:**
- Sanitização HTML markdown, 404 slug inexistente, sem business_id leak

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
