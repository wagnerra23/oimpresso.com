# CHANGELOG — Modules/Cms

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento alinhado a Wave governance ([ModuleGradeService](../Governance/Services/ModuleGradeService.php) D3.d).

## [Não publicado]

### Wave 28 governance FINAL — Cms polish 71-85 → ≥92 (+7pp) (2026-05-17)

- **ADD D9 span** `SiteContentService::getRenderChromePayload()` — novo método público + span `cms.service.render_chrome` (5º span canon SiteContentService, cumulativo W18 4 + W28 1). Agrega `CmsSiteDetail::getValue` de chaves canon (`site_name` / `logo` / `contact_email` / `social`) num único payload reaproveitável pelo Inertia shell (header/footer site público). Sem `business_id` (site oimpresso.com é GLOBAL — preserva D1 guard Wave 26 IRREVOGÁVEL US-CMS-002).
- **ADD D2** `Tests/Feature/Wave28CmsSaturationTest.php` (~7 cenários):
  - D9 W28 método novo + 5º span + render GLOBAL sem business_id
  - D2 W28 shape 4 chaves canon + ≥5 métodos públicos cumulativo + D1 guard preservado
  - D3 W28 CHANGELOG entry (este)
- **D3 W28 doc**: CHANGELOG (este entry); BRIEFING.md atualizado próxima sessão (out-of-scope deste agent).
- **Preservado**: D1 `cms_pages.business_id` AUSENTE canônico (US-CMS-002 schema pendente IRREVOGÁVEL) + D2 W26 Services contract (criar nullable, remover bool, DI Repository, baseQuery canon) + D7 LogsActivity baseline.

### Wave 27 governance — Cms polish 71-85 → ≥88 (2026-05-17)

- **ADD D4/D9.a** `CmsRenderService` (`Modules/Cms/Services/CmsRenderService.php`) —
  4º Service do módulo, focado em leitura/render de metadados SEO + tracking snippets.
  3 métodos públicos, 3 spans novos canon:
  - `cms.render.meta_for_page` — resolve title/description/tags por CmsPage com fallback
  - `cms.render.site_default_meta` — defaults do site (CmsSiteDetail)
  - `cms.render.tracking_snippets` — GA/Pixel/custom_js/custom_css filtrados não-vazios
  Separação clara de `SiteContentService` (payload completo home/blog) e `CmsPageService`
  (CRUD). Candidato natural a `Cache::remember()` futuro sem refactor de Controllers.
- **ADD D8.c** `DeleteCmsPageRequest` — FormRequest dedicada pra `destroy` de CmsPage,
  whitelist `type` espelha StoreCmsPageRequest (page/post/banner) + retorna 422 com
  mensagem clara em vez de 404 silencioso (anti-mapping).
- **ADD D8.c** `UpdateSiteDetailsRequest` — variante slim de `UpdateCmsSiteDetailsRequest`
  pra endpoints REST/JSON (sem upload de logo, sem snippets XSS-risk). Útil pra
  ações administrativas via API interna (CT 100 → MCP server).
- **ADD D2** `Wave27CmsPolishTest.php` — 13 cenários (todos passed local, 36 assertions):
  - 3 cenários DeleteCmsPageRequest (whitelist type, anti-mapping, payload vazio)
  - 4 cenários UpdateSiteDetailsRequest (email, faqs cap, payload realista, PATCH parcial)
  - 6 cenários CmsRenderService (DI, OtelHelper canon, 3 spans, ≥12 spans cross-services, SEO truncate, no-regression)
- **Schema preservado:** `cms_pages.business_id` AUSENTE preservado intencionalmente
  (US-CMS-002 pendente). Site público GLOBAL — `Wave25CmsSaturationTest` segue
  detectando drift positivo se alguém adicionar coluna sem ADR.
- **Total Services Cms agora:** 4 (CmsPageService, CmsLeadService, SiteContentService,
  CmsRenderService) com ≥12 spans OtelHelper canon (zero-cost quando otel.enabled=false).

### Wave 26 governance — Cms polish 71→≥85 (+14pp) (2026-05-17)

- **ADD D2** `Tests/Feature/Wave26CmsSaturationTest.php` (~24 cenários):
  - **D1 guard schema preservado**: `cms_pages.business_id` AUSENTE permanece canônico (US-CMS-002 schema pendente IRREVOGÁVEL). Test detecta drift positivo + valida NOT NULL + migration explícita se schema mudar.
  - **D2 Services expansão**: CmsPageService return types canon (`criar` nullable + `remover` bool), DI Repository (CmsPageRepository com `baseQuery()` canon), CmsLeadService PII redaction (D7.a span `cms.lead.captured`), SiteContentService 4 chaves home + ≥4 métodos públicos.
  - **D2 Entities expansão**: CmsPage accessor `feature_image_url` + `feature_image_arquivo` (Sprint 4 backbone Arquivos ADR 0123) + appends slug + HasArquivos trait; CmsSiteDetail + CmsPageMeta existem.
  - **D6 defer**: CmsPageController::showPage helper `buildPagePayload` private + pre-check 404 ANTES de defer (rollback Wave L/W7 PR #963 preservado) + `OtelHelper::spanBiz('cms.page.render')` + `Inertia::render('Site/Page')` + `showPageLegacy` /old preservado.
  - **D3 docs**: CHANGELOG (este entry) + BRIEFING.md atualizado.
  - **D7 baseline preservado**: Config/retention.php + `LogsActivity` config canon `logOnlyDirty + dontSubmitEmptyLogs`.

### Wave 25 governance — Cms saturation 71→≥85 (2026-05-16)

- **ADD D1/D2** `Tests/Feature/Wave25CmsSaturationTest.php` — 12 cenários (9 passed + 3 MySQL-skipped):
  - **Guard schema cms_pages.business_id ausente** preservado (US-CMS-002 schema pendente IRREVOGÁVEL) — site público GLOBAL. Test detecta drift positivo se alguém adicionar coluna sem ADR.
  - Reuse contract Services Wave 18 via Reflection: CmsPageService (OtelHelper canon, spans `cms.service.page.*`, ≥3), CmsLeadService (OtelHelper + PiiRedactor D7.a LGPD), SiteContentService (≥4 spans canon).
  - Métodos canon: `criar` (3 params, typed array), `atualizar` (4 params + metas nullable), `remover` (return bool), `capturar` (return bool — sent ou no-op).
  - DI: CmsPageService injeta `CmsPageRepository` + `Util` (SoC brutal ADR 0094 §5); CmsLeadService 1-dep stateless.
  - CmsPage usa LogsActivity (LGPD D7.b — auditoria conteúdo público) confirmado.

### Wave 18 governance — FULL SATURATION (2026-05-16)

- **ADD D4.a** `CmsPageService` (`Modules/Cms/Services/CmsPageService.php`) — orquestração
  CRUD de páginas (criar/atualizar/remover) extraída de `CmsPageController`. Service recebe
  `CmsPageRepository` + `Util` por DI; Controller fica thin (HTTP only). SoC brutal (ADR 0094 §5).
- **ADD D4.a** `CmsLeadService` (`Modules/Cms/Services/CmsLeadService.php`) — captura +
  notificação de leads via formulário público. Extraído de `CmsController@postContactForm`.
  Wraps `PiiRedactor` (D7.a LGPD) + `NewLeadGeneratedNotification`.
- **ADD D4.a** `CmsPageRepository` (`Modules/Cms/Repositories/CmsPageRepository.php`) —
  query layer isolando Eloquent. Método único `baseQuery()` será ponto de injeção de
  `business_id` global scope quando US-CMS-002 entregar (hoje gap conhecido).
- **ADD D2** 22 Pest tests novos:
  - `ServiceArchitectureTest.php` (10) — garante Services/Repository existem + OTel + LogsActivity
  - `FormRequestValidationTest.php` (12) — valida rules de Store/Update CmsPage + SubmitContactForm
  - `RepositoryContractTest.php` (10) — DI resolution + assinatura pública + multi-tenant compliance
- **ADD governance** `module.json` ganhou bloco `governance.fsm_n_a: true` — CMS é conteúdo
  estático (sem state machine de negócio); audit trail via spatie/activitylog cobre histórico.

### Wave 17 governance (2026-05-16)

- **FIX D9.a** OTel spans em `SiteContentService` — `OtelHelper::spanBiz(...)` em todos
  os 4 métodos públicos (`getHomePayload`, `getBlogList`, `findBlogPost`, `getPageByLayout`).
  Wave 16 instrumentou Controllers mas o `ModuleGradeService::dim9Observability()` mede
  D9.a sobre `Modules/<X>/Services/`, não Controllers — score saiu de 0/4 → 4/4.
- **ADD D3.d** CHANGELOG.md (este arquivo) — histórico per-wave.

### Wave 16 governance (2026-05-15)

- **ADD D9.a** OTel spans em 5 Controllers (`CmsController`, `CmsPageController`,
  `SettingsController`, `DataController`, `InstallController`) — 9 chamadas
  `OtelHelper::spanBiz(...)` cobrindo render home, blog list/view, page create/update/delete,
  settings save, lead notify. Zero-cost quando `otel.enabled=false`.

### Wave 11 governance (2026-05-12 a 2026-05-13)

- **ADD D7.c** `Config/retention.php` — retenção LGPD declarada (leads 730d, contacts 1095d,
  blog comments 1825d, activity_log 2555d). RUNBOOK separado faz purge respeitando estes valores.
- **ADD D7.b** Activity log spatie em `CmsPage`, `CmsSiteDetail`, `CmsPageMeta` — audit trail
  de mudanças em conteúdo publicado (compliance Marco Civil + LGPD).
- **ADD D8.c** FormRequests dedicadas: `StoreCmsPageRequest`, `StoreBlogPostRequest`,
  `SubmitContactFormRequest`, `UpdateCmsSiteDetailsRequest`, `UpdateSiteHomeRequest` —
  validação extraída dos Controllers (D4.a SoC brutal).
- **ADD** `CmsHealthCommand` — health check artisan (`php artisan cms:health`).

### Wave anterior — site público + multi-tenant

- **ADD** Site público dinâmico `/c` — home + páginas estáticas + blog (`Modules\Cms\Http\Controllers\CmsController`).
- **ADD** Multi-tenant slug isolation (`MultiTenantSlugIsolationTest`) — duas businesses
  podem ter páginas com mesmo slug sem colisão (ADR 0093 Tier 0).
- **ADD** WordPress importer (`ImportWpOfficeImpressoCommand`) — migra conteúdo legacy
  OfficeImpresso.com.br pra Modules/Cms.
- **ADD** Notificação `NewLeadGeneratedNotification` — envio mail admin ao receber lead via
  formulário público `postContactForm`.

## Cross-ref

- [ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0155](../../memory/decisions/0155-module-grade-v3.md) — Module grade v3 (D6–D9)
- [ADR 0156](../../memory/decisions/0156-module-grade-v3-errata-1.md) — Errata regex D9.a OtelHelper
- [App\Util\OtelHelper](../../app/Util/OtelHelper.php) — facade canônica zero-cost
- [memory/requisitos/Cms/SPEC.md](../../memory/requisitos/Cms/SPEC.md) — SPEC Cms
