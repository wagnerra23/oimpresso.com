---
page_id: cms.site.home
route: GET /c
controller: Modules\Cms\Http\Controllers\CmsController@index
component: resources/js/Pages/Site/Home.tsx
status: draft
owner: "[W]"
created: 2026-05-16
sprint: S4
service: Modules\Cms\Services\SiteContentService::getHomePayload()
---

# Charter — Site Home pública (`/c`)

## Mission

Home pública do tenant `oimpresso.com` (e mirrors por business) — landing page que apresenta o ERP a visitantes não autenticados. Hidrata Hero/Features/SocialProof a partir de `cms_pages` + `cms_site_details` editáveis pelo dono do business em `/admin/cms/settings`, sem deploy.

## Goals

- **G1** — Carregar Hero + Features + Statistics + FAQ + Testimonials em ≤300ms (LCP target). Payload vem 100% de `SiteContentService::getHomePayload()` (D4.a SoC brutal).
- **G2** — Permitir A/B test de copy via campo `cms_pages.content` (markdown) sem novo deploy. Wagner edita em `/admin/cms/pages`.
- **G3** — Multi-tenant safe — quando `business_id` resolvido por subdomínio (futuro), payload retorna SOMENTE conteúdo daquele business via global scope dos Models (ADR 0093 Tier 0).
- **G4** — Acessibilidade WCAG 2.1 AA — Lighthouse a11y ≥95 (skill `accessibility-review`).

## Non-Goals

- ❌ Catálogo de produtos público (vai virar `/c/produtos` charter separada — Modules/Vestuario/ComunicacaoVisual)
- ❌ Auth flow (Login/Register são charters próprias em Modules/Auth)
- ❌ Checkout/pricing dinâmico (charter `Site/Pricing` separada — `SitePricingDinamicoTest` cobre)
- ❌ Hidratar copy de fontes externas (Sanity/Strapi/Contentful) — DB próprio é canon

## UX targets

- LCP ≤ 2.5s @ 4G simulado
- CLS < 0.1
- Hero CTA principal acima da fold em 1280px (monitor ROTA LIVRE)
- Mobile-first: portrait 375px legível sem scroll horizontal
- PT-BR default, copy editável em `cms_pages` (sem hardcoded fora de fallback)

## Anti-hooks (proibido — sem ADR mãe)

- ⛔ Query SQL inline no `.tsx` (consumir só props `useForm`/`usePage().props`)
- ⛔ `withoutGlobalScopes` em `CmsPage::query()` (multi-tenant Tier 0)
- ⛔ Lead form submit sem CSRF + sem rate-limit (`postContactForm` já tem demo-check, faltam ambos)
- ⛔ Carregar imagens não-otimizadas no Hero — usar `Image::format('webp')` ou `loading="lazy"`
- ⛔ Hardcoded copy PT-BR em `.tsx` após PR2 (PR1 hardcoded é débito conhecido — ver comment Controller)
- ⛔ Adicionar prop cara (count/paginate/eager-load aggregated) sem `Inertia::defer()` (skill `inertia-defer-default`)

## Métricas de saúde

- Test cobertura: `SiteHomeTest.php`, `SiteHomeDinamicoTest.php`, `SiteHomeMultiTenantTest.php` (3 Pest tests existem)
- Manual smoke biz=1 antes de merge (ADR 0101 — NUNCA biz=4 cliente)

## Histórico

- 2026-05-16 — charter criada (Wave J — agent isolado boost Cms 58→70)
- 2026-05-16 — `getHomePayload()` extraído de CmsController pra SiteContentService

## Referências

- SPEC: `memory/requisitos/Cms/SPEC.md`
- BRIEFING: `memory/requisitos/Cms/BRIEFING.md`
- Service: `Modules/Cms/Services/SiteContentService.php`
- Component (PR1): `resources/js/Pages/Site/Home.tsx` (a criar — PR1 ainda tem só Blade legacy `cms::frontend.pages.home` em `/c/old`)
- Tests: `Modules/Cms/Tests/Feature/SiteHome*Test.php`
- ADRs: 0093 (multi-tenant), 0094 (Constituição §5 SoC), 0104 (MWART processo), 0114 (prototipo-ui loop)
