---
name: "SUPERFÍCIE — Cms"
description: "Índice GERADO dos artefatos do módulo Cms reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Cms
---

# 🗺️ Superfície de código — Cms

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Cms --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Cms/**` + `resources/js/Pages/Cms/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 100 arquivos em 13 papéis.

## Controllers — 5

- [CmsController.php](../../../Modules/Cms/Http/Controllers/CmsController.php)
- [CmsPageController.php](../../../Modules/Cms/Http/Controllers/CmsPageController.php)
- [DataController.php](../../../Modules/Cms/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Cms/Http/Controllers/InstallController.php)
- [SettingsController.php](../../../Modules/Cms/Http/Controllers/SettingsController.php)

## Requests (validação) — 9

- [DeleteCmsPageRequest.php](../../../Modules/Cms/Http/Requests/DeleteCmsPageRequest.php)
- [StoreBlogPostRequest.php](../../../Modules/Cms/Http/Requests/StoreBlogPostRequest.php)
- [StoreCmsPageRequest.php](../../../Modules/Cms/Http/Requests/StoreCmsPageRequest.php)
- [StoreCmsSettingsRequest.php](../../../Modules/Cms/Http/Requests/StoreCmsSettingsRequest.php)
- [SubmitContactFormRequest.php](../../../Modules/Cms/Http/Requests/SubmitContactFormRequest.php)
- [UpdateCmsPageRequest.php](../../../Modules/Cms/Http/Requests/UpdateCmsPageRequest.php)
- [UpdateCmsSiteDetailsRequest.php](../../../Modules/Cms/Http/Requests/UpdateCmsSiteDetailsRequest.php)
- [UpdateSiteDetailsRequest.php](../../../Modules/Cms/Http/Requests/UpdateSiteDetailsRequest.php)
- [UpdateSiteHomeRequest.php](../../../Modules/Cms/Http/Requests/UpdateSiteHomeRequest.php)

## Services — 4

- [CmsLeadService.php](../../../Modules/Cms/Services/CmsLeadService.php)
- [CmsPageService.php](../../../Modules/Cms/Services/CmsPageService.php)
- [CmsRenderService.php](../../../Modules/Cms/Services/CmsRenderService.php)
- [SiteContentService.php](../../../Modules/Cms/Services/SiteContentService.php)

## Models / Entities — 3

- [CmsPage.php](../../../Modules/Cms/Entities/CmsPage.php)
- [CmsPageMeta.php](../../../Modules/Cms/Entities/CmsPageMeta.php)
- [CmsSiteDetail.php](../../../Modules/Cms/Entities/CmsSiteDetail.php)

## Console / Commands — 2

- [CmsHealthCommand.php](../../../Modules/Cms/Console/Commands/CmsHealthCommand.php)
- [ImportWpOfficeImpressoCommand.php](../../../Modules/Cms/Console/ImportWpOfficeImpressoCommand.php)

## Providers — 2

- [CmsServiceProvider.php](../../../Modules/Cms/Providers/CmsServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Cms/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Cms/Routes/api.php)
- [web.php](../../../Modules/Cms/Routes/web.php)

## Migrations (schema) — 5

- [2022_08_04_143146_create_cms_pages_table.php](../../../Modules/Cms/Database/Migrations/2022_08_04_143146_create_cms_pages_table.php)
- [2022_09_10_161849_add_layout_column_to_cms_pages_table.php](../../../Modules/Cms/Database/Migrations/2022_09_10_161849_add_layout_column_to_cms_pages_table.php)
- [2022_09_10_163209_create_cms_site_details_table.php](../../../Modules/Cms/Database/Migrations/2022_09_10_163209_create_cms_site_details_table.php)
- [2022_09_15_122547_create_cms_page_metas_table.php](../../../Modules/Cms/Database/Migrations/2022_09_15_122547_create_cms_page_metas_table.php)
- [2022_09_16_130337_create_default_data_for_cms.php](../../../Modules/Cms/Database/Migrations/2022_09_16_130337_create_default_data_for_cms.php)

## Seeders — 1

- [CmsDatabaseSeeder.php](../../../Modules/Cms/Database/Seeders/CmsDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Cms/Config/config.php)
- [retention.php](../../../Modules/Cms/Config/retention.php)

## Views (Blade) — 45

- 45 arquivos em [Modules/Cms/Resources/views/components/chat_widget/](../../../Modules/Cms/Resources/views/components/chat_widget) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 17

- 17 arquivos em [Modules/Cms/Tests/Feature/](../../../Modules/Cms/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 3

- [NewLeadGeneratedNotification.php](../../../Modules/Cms/Notifications/NewLeadGeneratedNotification.php)
- [CmsPageRepository.php](../../../Modules/Cms/Repositories/CmsPageRepository.php)
- [CmsUtil.php](../../../Modules/Cms/Utils/CmsUtil.php)
