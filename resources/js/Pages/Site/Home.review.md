---
tela: Site/Home
controller: Modules\Cms\Http\Controllers\CmsController@index
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

# Screen Review — Site/Home

> Append-only — rounds anteriores NUNCA editados.

---

## Round 1 — 2026-05-17 (W31 Bulk Review)

**Status:** awaiting-smoke-browser

**Análise estática (bulk W31):**
- Charter: ✗ AUSENTE — criar `Home.charter.md` pré-req Round 2.
- Controller: `CmsController@index` (`SiteContentService`) usa `Inertia::defer` ✓
- Landing page pública oimpresso.com — hero, features, social proof, CTA pricing

**Smoke browser MCP:** **pendente** (público, sem auth — smoke direto via curl pode validar HTML inicial).

**UX targets esperados (sem smoke):**
- first_paint_ms ≤ 800: confiança alta (página marketing otimizada)
- console errors: 0 OBRIGATÓRIO (página pública vista por leads — qualquer erro vira churn)
- 1440/1280 sem scroll: validar; landing precisa também 768/375 (mobile-first)
- Lighthouse/SEO score: meta secundária (não no charter base mas crítico pra SEO)

**Desvios potenciais:**
- Performance LCP (Largest Contentful Paint) — hero image precisa lazy + WebP
- Bundle size (page pública = bundle visible to Lighthouse)
- Acessibilidade WCAG AA (página pública obrigatório)
- SSR/SEO meta tags (Inertia + SSR config?)

**Pest GUARD pendente:**
- Sem auth (rota pública), no business_id leak em props (página é multi-tenant-agnostic — biz=null OK)

**Decisão Wagner:** [pendente]

---

## Próximos rounds

Skill `tela-smoke-pos-merge` auto-cria Round 2.
