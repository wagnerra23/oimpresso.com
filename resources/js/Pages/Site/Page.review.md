---
tela: Site/Page
controller: Modules\Cms\Http\Controllers\CmsPageController@showPage
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

# Screen Review — Site/Page

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Page.charter.md` pré-req Round 2.
- Controller: `CmsPageController@showPage` usa `Inertia::defer` ✓ + helper `buildPagePayload` + `OtelHelper::spanBiz('cms.page.render')` (canon Wave L/W7 PR #963 preservado) + pre-check 404 ANTES do defer
- Página estática CMS genérica (about, contato, termos, privacidade etc) — render markdown/blocks via `SiteContentService`

**Smoke browser MCP:** **pendente** (páginas públicas).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta — controller bem otimizado (defer + OTel + 404 antes)
- console errors: 0 OBRIGATÓRIO
- 1440/1280 sem scroll: depende do conteúdo CMS (validar)
- 768/375 mobile: obrigatório página pública

**Desvios potenciais:**
- Conteúdo CMS pode ter HTML embed (XSS risk se não sanitizado)
- SEO meta tags por página (canonical, og:image)
- Página inexistente → 404 page (validar não-vazamento info interna)

**Pest GUARD pendente:**
- Pre-check 404 antes defer (anti-regressão Wave L/W7 PR #963 preservada), sanitização HTML, sem business_id leak em página pública

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
