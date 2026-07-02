---
module: Cms
slug: cms-spec
title: "SPEC — Modules/Cms"
type: spec
version: "1.0"
last_updated: "2026-06-13"
owner: wagner
status: rascunho
related_adrs: [0093-multi-tenant-isolation-tier-0, 0101-tests-business-id-1-nunca-cliente, 0104-processo-mwart-canonico-unico-caminho, 0106-recalibracao-velocidade-fator-10x-ia-pair, 0121-oimpresso-modular-especializado-por-vertical]
---

# SPEC — Modules/Cms

> **Estado:** rascunho v1 — criado 2026-05-16 (Wave Massive 12-agents).
> **Nota auditoria 2026-05-16:** 30/100 (Crítico) · Gap principal D1 5/30, D3 0/15.
> **Cliente alvo:** site público oimpresso.com (landing + blog + páginas dinâmicas) + (futuro) páginas por tenant.

## Contexto

Modules/Cms hoje serve o **landing oimpresso.com** (home, pricing, blog, páginas estáticas tipo "Sobre nós") + importer de WordPress + autenticação social. As tabelas (`cms_pages`, `cms_site_details`, `cms_page_metas`) foram criadas SEM coluna `business_id` — funcionam como tabelas globais.

**Risco Tier 0 IRREVOGÁVEL (ADR 0093):** quando o módulo evoluir pra suportar páginas por tenant (cada cliente com domínio/landing próprio), slug biz=1 NÃO pode aparecer em queries servindo biz=99 — vazamento de conteúdo, possível PII em depoimentos, branding misturado.

## Estado-da-arte referência

- **WordPress Multisite** — multi-tenant por subdomain/path com isolamento de posts/pages
- **Webflow / Squarespace** — sites per-business com builder visual
- **Bling/Tiny** — não têm CMS embarcado; oimpresso pode diferenciar
- **Sanity / Strapi** — headless CMS com tenant isolation nativo

## User Stories

### P0 (Tier 0 — bloqueia entrada de novos tenants no CMS)

#### US-CMS-001 — Site home pública oimpresso.com renderiza Inertia/React sem auth
**Como** visitante anônimo
**Quero** acessar `/` e ver landing institucional oimpresso.com
**Pra** conhecer o produto antes de cadastrar
**Implementado em:** `Modules/Cms/Http/Controllers/CmsController.php` · `resources/js/Pages/Site/Home.tsx` · `Modules/Superadmin/Http/Controllers/PricingController.php` · `resources/js/Pages/Site/Pricing.tsx` · verificado@8af585a (2026-07-02)

**Acceptance:**
- `GET /` retorna 200 sem auth
- Componente Inertia `Site/Home` carrega com props `testimonials`, `page`, `faqs`, `statistics`
- Rota `/old` mantém Blade legacy ainda funcional (rollback)
- Pricing público `/pricing` renderiza `Site/Pricing` com `packages` + `permissions`

**Status:** ✅ entregue (PR pré-2026-05-16, validado por `SiteHomeTest.php`)

#### US-CMS-002 — Adicionar `business_id` em `cms_pages` (multi-tenant CMS)
**Como** arquiteto multi-tenant
**Quero** que cada CmsPage pertença a um business específico
**Pra** que slug biz=1 NÃO aparece servindo biz=99 (vazamento de conteúdo)
**Implementado em:** _pendente_ — coluna `business_id` não existe em `cms_pages` (nenhuma migration criada); `MultiTenantSlugIsolationTest` segue em `markTestSkipped`

**Acceptance:**
- Migration idempotente adiciona `business_id BIGINT UNSIGNED NOT NULL` + index + FK pra `business.id`
- Backfill: registros existentes ficam com `business_id=1` (landing oimpresso) ou `business_id=NULL` se for página "global" (decisão arquitetural via ADR)
- CmsPage model usa `BusinessScope` global scope (padrão `Modules/ComunicacaoVisual`)
- Suite `MultiTenantSlugIsolationTest.php` (5 cenários) sai de `markTestSkipped` e roda verde
- Pest cross-tenant biz=1 vs biz=99 valida isolamento

**Effort:** ~6h (10x IA-pair) — migration + scope + backfill + ADR

#### US-CMS-003 — Adicionar `business_id` em `cms_site_details`
**Como** arquiteto multi-tenant
**Quero** que cada tenant tenha config própria de site (logo, headline, footer, depoimentos)
**Pra** servir landing personalizada por business sem vazar branding/PII
**Implementado em:** _pendente_ — coluna `business_id` não existe em `cms_site_details`; `SiteHomeMultiTenantTest` segue em `markTestSkipped`

**Acceptance:**
- Migration adiciona `business_id` em `cms_site_details` + unique constraint `[business_id, site_key]`
- `CmsSiteDetail::getValue()` e `getSiteDetails()` respeitam global scope
- Suite `SiteHomeMultiTenantTest.php` (3 cenários) sai de `markTestSkipped` e roda verde
- Backfill: registros atuais → `business_id=1`

**Effort:** ~4h

### P1 (gaps D1 — features básicas faltantes)

#### US-CMS-004 — CRUD CmsPage migrado Blade→Inertia/React (MWART)
**Como** admin (Wagner/Maiara)
**Quero** criar/editar páginas via UI moderna React
**Pra** parar de depender do Blade `cms::page.create`
**Implementado em:** _pendente_ — migração MWART não iniciada; CRUD admin segue Blade (`cms::page.*` via CmsPageController) e não existe Page Inertia de admin do Cms

**Acceptance:**
- Page Inertia em `resources/js/Pages/Cms/Pages/Index.tsx` + `Create.tsx` + `Edit.tsx`
- Editor rich-text (TipTap ou similar — alinhar com `_DesignSystem`)
- Charter `Index.charter.md` ao lado da .tsx
- RUNBOOK em `memory/requisitos/Cms/RUNBOOK-pages-crud.md`
- Pest 5+ fixtures (`store`, `update`, `destroy`, autorização, isolamento)

**Effort:** ~12h (gate visual F1.5 + MWART 5 fases)

#### US-CMS-005 — Páginas dinâmicas (`/p/{slug}` ou `/c/page/{slug}`)
**Como** visitante
**Quero** acessar páginas dinâmicas por slug
**Pra** ler termos, política, sobre, blog posts
**Implementado em:** _parcial_ · `Modules/Cms/Http/Controllers/CmsPageController.php` · `resources/js/Pages/Site/Page.tsx` · verificado@8af585a (2026-07-02) — rota pública renderiza Inertia; falta isolamento multi-tenant do slug (depende US-CMS-002)

**Acceptance:**
- Rota `/c/page/{slug}` renderiza Inertia `Site/Page` (já existe em `SitePageTest.php`)
- Slug é derivado do `title` (accessor `getSlugAttribute()`)
- 404 quando slug não existe OU pertence a outro tenant (cobre US-CMS-002)
- Suporte a meta description + tags pra SEO

**Status:** 🟡 parcial (rota existe, isolamento multi-tenant pendente — depende de US-CMS-002)

#### US-CMS-006 — Site Home dinâmico por tenant
**Como** business owner
**Quero** que `/` (ou `{tenant}.oimpresso.com`) carregue config própria de site
**Pra** ter landing personalizada (logo, headline, depoimentos, CTAs)
**Implementado em:** _parcial_ · `Modules/Cms/Services/SiteContentService.php` · `Modules/Cms/Entities/CmsSiteDetail.php` · verificado@8af585a (2026-07-02) — home lê config dinâmica do banco com fallback; falta detecção de tenant (domain/subdomain) e isolamento (depende US-CMS-003)

**Acceptance:**
- `SiteHomeDinamicoTest.php` valida (já existe — gap: isolamento multi-tenant)
- Detecção de tenant via domain/subdomain/path (definir via ADR)
- Fallback pro landing oimpresso.com se sem tenant detectado

**Effort:** ~10h (depende US-CMS-003)

### P2 (gaps D1 — features avançadas)

#### US-CMS-007 — Pricing dinâmico por tenant (planos custom)
**Como** business owner
**Quero** mostrar planos próprios na landing
**Pra** vender com pricing personalizado (não só do oimpresso)
**Implementado em:** _parcial_ · `Modules/Superadmin/Http/Controllers/PricingController.php` · `resources/js/Pages/Site/Pricing.tsx` · `Modules/Superadmin/Http/Controllers/PackagesController.php` · verificado@8af585a (2026-07-02) — pricing público lê packages reais do DB + CRUD admin superadmin; falta pricing per-tenant (packages são globais)

**Acceptance:**
- `SitePricingDinamicoTest.php` valida pricing per-tenant
- CRUD de packages via admin UI
- Display público `/pricing` lê packages do business correto

**Status:** 🟡 parcial (teste existe — `SitePricingDinamicoTest.php`)

#### US-CMS-008 — Autenticação social no landing
**Como** visitante
**Quero** logar com Google/Facebook
**Pra** acelerar cadastro
**Implementado em:** _parcial_ · `app/Http/Controllers/Auth/SocialAuthController.php` · `resources/js/Pages/Site/Login.tsx` · verificado@8af585a (2026-07-02) — Socialite Google+Microsoft com criação automática de usuário; falta provider Facebook previsto no aceite (regex de rota barra outros providers)

**Acceptance:**
- `AuthSocialTest.php` valida fluxo Socialite
- Suporta `google`, `facebook` (config via .env)
- Cria usuário automático se primeiro login

**Status:** 🟡 parcial (teste existe — `AuthSocialTest.php`)

#### US-CMS-009 — Importer WordPress (XML/REST API)
**Como** business com site WP legado
**Quero** importar páginas/posts pra `cms_pages`
**Pra** migrar conteúdo sem retrabalho manual
**Implementado em:** _parcial_ · `Modules/Cms/Console/ImportWpOfficeImpressoCommand.php` · verificado@8af585a (2026-07-02) — importer idempotente via conexão DB direta do WP (posts+pages publicados); falta suporte a XML export genérico previsto no aceite

**Acceptance:**
- `ImporterWpTest.php` valida importer (já existe)
- Suporta XML export WP
- Mapping: WP `post` → `cms_pages.type=blog`; WP `page` → `cms_pages.type=page`
- Preserva meta description, tags, feature_image

**Status:** 🟡 parcial (teste existe — `ImporterWpTest.php`)

### P3 (nice-to-have)

#### US-CMS-010 — Editor visual drag-drop (page builder)
**Como** admin
**Quero** montar landing com componentes drag-drop
**Pra** não precisar de dev pra cada mudança de copy
**Implementado em:** _pendente_ — page builder drag-drop não iniciado (backlog P3)

**Effort:** ~40h — comparar contra Elementor / Gutenberg / Webflow

**Status:** ❌ backlog

## Pré-requisitos Tier 0

- ADR 0093 multi-tenant — global scope obrigatório
- ADR 0101 — Pest sempre biz=1, NUNCA biz=4 (Larissa cliente)
- ADR 0104 MWART — única forma de migrar Blade→Inertia/React

## Roadmap sugerido (10x IA-pair calibrado — ADR 0106)

| Sprint | US | Effort calibrado | Bloqueia |
|--------|-----|------------------|----------|
| Sprint 1 | US-CMS-002, US-CMS-003 | ~10h | US-CMS-005, US-CMS-006 |
| Sprint 2 | US-CMS-004 (MWART) | ~12h | UI moderna admin |
| Sprint 3 | US-CMS-006, US-CMS-007 | ~14h | Multi-tenant CMS produto vendável |
| Sprint 4 | US-CMS-009, US-CMS-008 polish | ~8h | Onboarding business com WP legacy |

## Tests existentes (NÃO mexer — Wave Massive 2026-05-16)

- `SiteHomeTest.php` — home pública (US-CMS-001) ✅
- `SitePageTest.php` — slug dinâmico (US-CMS-005) 🟡
- `SiteHomeDinamicoTest.php` — home dinâmica (US-CMS-006) 🟡
- `SitePricingDinamicoTest.php` — pricing dinâmico (US-CMS-007) 🟡
- `AuthSocialTest.php` — social auth (US-CMS-008) 🟡
- `ImporterWpTest.php` — importer WP (US-CMS-009) 🟡

## Tests novos (criados 2026-05-16)

- `MultiTenantSlugIsolationTest.php` — isolamento slug per-tenant (5 cenários) → ativa quando US-CMS-002 entregar
- `SiteHomeMultiTenantTest.php` — site_details per-tenant (3 cenários) → ativa quando US-CMS-003 entregar

## Referências

- [ADR 0093 Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Pest biz=1](../../decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0106 Recalibração 10x IA-pair](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0121 Modular especializado por vertical](../../decisions/0121-oimpresso-modular-especializado-por-vertical.md)
- Pattern: `Modules/ComunicacaoVisual/Tests/Feature/MultiTenantTest.php`
