---
name: "SUPERFÍCIE — Woocommerce"
description: "Índice GERADO dos artefatos do módulo Woocommerce reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Woocommerce
---

# 🗺️ Superfície de código — Woocommerce

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Woocommerce --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Woocommerce/**` + `resources/js/Pages/Woocommerce/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 59 arquivos em 13 papéis.

## Controllers — 4

- [DataController.php](../../../Modules/Woocommerce/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Woocommerce/Http/Controllers/InstallController.php)
- [WoocommerceController.php](../../../Modules/Woocommerce/Http/Controllers/WoocommerceController.php)
- [WoocommerceWebhookController.php](../../../Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php)

## Requests (validação) — 2

- [MapTaxRatesRequest.php](../../../Modules/Woocommerce/Http/Requests/MapTaxRatesRequest.php)
- [UpdateApiSettingsRequest.php](../../../Modules/Woocommerce/Http/Requests/UpdateApiSettingsRequest.php)

## Services — 3

- [WoocommerceAuthorizationService.php](../../../Modules/Woocommerce/Services/WoocommerceAuthorizationService.php)
- [WoocommerceResetService.php](../../../Modules/Woocommerce/Services/WoocommerceResetService.php)
- [WoocommerceSyncService.php](../../../Modules/Woocommerce/Services/WoocommerceSyncService.php)

## Models / Entities — 1

- [WoocommerceSyncLog.php](../../../Modules/Woocommerce/Entities/WoocommerceSyncLog.php)

## Console / Commands — 3

- [WoocommerceHealthCommand.php](../../../Modules/Woocommerce/Console/Commands/WoocommerceHealthCommand.php)
- [WooCommerceSyncOrder.php](../../../Modules/Woocommerce/Console/WooCommerceSyncOrder.php)
- [WoocommerceSyncProducts.php](../../../Modules/Woocommerce/Console/WoocommerceSyncProducts.php)

## Providers — 2

- [RouteServiceProvider.php](../../../Modules/Woocommerce/Providers/RouteServiceProvider.php)
- [WoocommerceServiceProvider.php](../../../Modules/Woocommerce/Providers/WoocommerceServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Woocommerce/Routes/api.php)
- [web.php](../../../Modules/Woocommerce/Routes/web.php)

## Migrations (schema) — 13

- [2018_10_10_110400_add_module_version_to_system_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_10_110400_add_module_version_to_system_table.php)
- [2018_10_10_122845_add_woocommerce_api_settings_to_business_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_10_122845_add_woocommerce_api_settings_to_business_table.php)
- [2018_10_10_162041_add_woocommerce_category_id_to_categories_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_10_162041_add_woocommerce_category_id_to_categories_table.php)
- [2018_10_11_173839_create_woocommerce_sync_logs_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_11_173839_create_woocommerce_sync_logs_table.php)
- [2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table.php)
- [2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table.php](../../../Modules/Woocommerce/Database/Migrations/2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table.php)
- [2018_12_03_163945_add_woocommerce_permissions.php](../../../Modules/Woocommerce/Database/Migrations/2018_12_03_163945_add_woocommerce_permissions.php)
- [2019_02_18_154414_change_woocommerce_sync_logs_table.php](../../../Modules/Woocommerce/Database/Migrations/2019_02_18_154414_change_woocommerce_sync_logs_table.php)
- [2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table.php](../../../Modules/Woocommerce/Database/Migrations/2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table.php)
- [2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table.php](../../../Modules/Woocommerce/Database/Migrations/2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table.php)
- [2019_10_01_171828_add_woocommerce_media_id_columns.php](../../../Modules/Woocommerce/Database/Migrations/2019_10_01_171828_add_woocommerce_media_id_columns.php)
- [2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table.php](../../../Modules/Woocommerce/Database/Migrations/2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table.php)
- [2021_02_16_190608_add_woocommerce_module_indexing.php](../../../Modules/Woocommerce/Database/Migrations/2021_02_16_190608_add_woocommerce_module_indexing.php)

## Seeders — 2

- [AddDummySyncLogTableSeeder.php](../../../Modules/Woocommerce/Database/Seeders/AddDummySyncLogTableSeeder.php)
- [WoocommerceDatabaseSeeder.php](../../../Modules/Woocommerce/Database/Seeders/WoocommerceDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Woocommerce/Config/config.php)
- [retention.php](../../../Modules/Woocommerce/Config/retention.php)

## Views (Blade) — 13

- 13 arquivos em [Modules/Woocommerce/Resources/views/layouts/](../../../Modules/Woocommerce/Resources/views/layouts) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Testes (Pest) — 8

- 8 arquivos em [Modules/Woocommerce/Tests/Feature/](../../../Modules/Woocommerce/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 4

- [WooCommerceError.php](../../../Modules/Woocommerce/Exceptions/WooCommerceError.php)
- [SyncOrdersNotification.php](../../../Modules/Woocommerce/Notifications/SyncOrdersNotification.php)
- [WoocommerceSyncLogRepository.php](../../../Modules/Woocommerce/Repositories/WoocommerceSyncLogRepository.php)
- [WoocommerceUtil.php](../../../Modules/Woocommerce/Utils/WoocommerceUtil.php)
