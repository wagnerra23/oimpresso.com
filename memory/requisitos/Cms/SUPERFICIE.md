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
> **O que isto é:** o inventário completo das raízes `Modules/Cms/**` + `resources/js/Pages/Cms/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Cms/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 139 arquivos em 13 papéis.

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

## Demais arquivos (manifestos, docs, assets e misc) — 42

- [CHANGELOG.md](../../../Modules/Cms/CHANGELOG.md)
- [.gitkeep](../../../Modules/Cms/Config/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Console/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Cms/Http/Requests/.gitkeep)
- [NewLeadGeneratedNotification.php](../../../Modules/Cms/Notifications/NewLeadGeneratedNotification.php)
- [.gitkeep](../../../Modules/Cms/Providers/.gitkeep)
- [CmsPageRepository.php](../../../Modules/Cms/Repositories/CmsPageRepository.php)
- [.gitkeep](../../../Modules/Cms/Resources/assets/.gitkeep)
- [cms.css](../../../Modules/Cms/Resources/assets/css/cms.css)
- [contact.jpg](../../../Modules/Cms/Resources/assets/img/contact.jpg)
- [default.png](../../../Modules/Cms/Resources/assets/img/default.png)
- [home.png](../../../Modules/Cms/Resources/assets/img/home.png)
- [cms.js](../../../Modules/Cms/Resources/assets/js/cms.js)
- [.gitkeep](../../../Modules/Cms/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Cms/Resources/lang/en/lang.php)
- [.gitkeep](../../../Modules/Cms/Resources/views/.gitkeep)
- [cp-chaticon.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-chaticon.png)
- [cp-close.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-close.png)
- [cp-email.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-email.png)
- [cp-instagram.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-instagram.png)
- [cp-linkedin.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-linkedin.png)
- [cp-messenger.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-messenger.png)
- [cp-skype.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-skype.png)
- [cp-telegram.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-telegram.png)
- [cp-telephone.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-telephone.png)
- [cp-twitter.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-twitter.png)
- [cp-viber.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-viber.png)
- [cp-whatsapp.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/cp-whatsapp.png)
- [main-logo.png](../../../Modules/Cms/Resources/views/components/chat_widget/img/cp-icon/main-logo.png)
- [SCOPE.md](../../../Modules/Cms/SCOPE.md)
- [.gitkeep](../../../Modules/Cms/Tests/.gitkeep)
- [CmsUtil.php](../../../Modules/Cms/Utils/CmsUtil.php)
- [composer.json](../../../Modules/Cms/composer.json)
- [module.json](../../../Modules/Cms/module.json)
- [package.json](../../../Modules/Cms/package.json)
- [webpack.mix.js](../../../Modules/Cms/webpack.mix.js)
