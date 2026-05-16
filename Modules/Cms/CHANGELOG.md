# CHANGELOG — Modules/Cms

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/).
Versionamento alinhado a Wave governance ([ModuleGradeService](../Governance/Services/ModuleGradeService.php) D3.d).

## [Não publicado]

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
