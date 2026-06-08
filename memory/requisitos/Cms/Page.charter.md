---
page_id: cms.site.page
route: GET /c/page/{slug}
controller: Modules\Cms\Http\Controllers\CmsPageController@showPage
component: resources/js/Pages/Site/Page.tsx
status: draft
owner: "[W]"
created: 2026-05-16
sprint: S4
---

# Charter — Página pública estática (`/c/page/{slug}`)

## Mission

Renderizar páginas estáticas (sobre, contato, termos, privacidade, política LGPD, etc) criadas em `/admin/cms/pages` pelo dono do business. Slug = `strtolower(replace(' ', '-', title))`. Conteúdo markdown + `pageMeta` (SEO).

## Goals

- **G1** — Slug-routing case-insensitive — `/c/page/sobre-nos` resolve `title="Sobre Nos"`.
- **G2** — Carregar `CmsPage::with('pageMeta')` (eager) — meta tags SEO renderizadas no `<Head>` Inertia (title, description, og:image).
- **G3** — Multi-tenant — `slug` ÚNICO por `business_id`. Test `MultiTenantSlugIsolationTest.php` cobre.
- **G4** — 404 limpo via `abort(404)` quando slug inexistente (não vaza erro 500).

## Non-Goals

- ❌ Editor WYSIWYG no front (edição é em `/admin/cms/pages` via Blade legacy)
- ❌ Comments/disqus/like (não é blog — `Site/BlogPost` charter é separada)
- ❌ Auth/role gating na leitura (página pública = pública)
- ❌ i18n runtime (1 idioma por business, configurável no settings)

## UX targets

- LCP ≤ 1.8s (conteúdo majoritariamente texto)
- Hierarquia tipográfica: H1 → H2 → corpo via Prose Tailwind
- Imagens markdown lazy-load (`loading="lazy"` por padrão)
- Print stylesheet — `.no-print` em nav/footer (termos/privacidade comumente impressos)

## Anti-hooks (proibido — sem ADR mãe)

- ⛔ Renderizar `page.content` SEM sanitização (XSS) — usar `DOMPurify` ou `dangerouslySetInnerHTML` SÓ após sanitize
- ⛔ Eager load `with('pageMeta')` em listagem `/c/blogs` (já está no `Site/Page` apenas — manter)
- ⛔ Buscar página por `id` numérico via URL (slug é o contract — `findIdFromGivenUrl` é legacy blog only)
- ⛔ `withoutGlobalScopes` em `CmsPage::query()` (Tier 0)
- ⛔ Cache global sem chave `{business_id}:{slug}` (vaza cross-tenant)

## Métricas de saúde

- Test cobertura: `SitePageTest.php`, `MultiTenantSlugIsolationTest.php`
- 404 rate alvo < 2% (alertar via `jana:health-check` se subir — slug typo comum)

## Histórico

- 2026-05-16 — charter criada (Wave J — agent isolado boost Cms 58→70)

## Referências

- SPEC: `memory/requisitos/Cms/SPEC.md`
- BRIEFING: `memory/requisitos/Cms/BRIEFING.md`
- Controller: `Modules/Cms/Http/Controllers/CmsPageController.php@showPage`
- Component: `resources/js/Pages/Site/Page.tsx` (a criar — atualmente só Blade legacy `cms::frontend.pages.custom_view` em `/c/page/{slug}/old`)
- Tests: `Modules/Cms/Tests/Feature/SitePageTest.php`, `MultiTenantSlugIsolationTest.php`
- ADRs: 0093 (multi-tenant slug isolation), 0094 (§5 SoC), 0104 (MWART)
