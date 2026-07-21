---
name: "SUPERFÍCIE — Superadmin"
description: "Índice GERADO dos artefatos do módulo Superadmin reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Superadmin
---

# 🗺️ Superfície de código — Superadmin

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Superadmin --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Superadmin/**` + `resources/js/Pages/superadmin/**` (namespace Inertia `superadmin`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `Superadmin`), separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 126 arquivos em 17 papéis.

## Controllers — 14

- [BaseController.php](../../../Modules/Superadmin/Http/Controllers/BaseController.php)
- [BusinessController.php](../../../Modules/Superadmin/Http/Controllers/BusinessController.php)
- [CommunicatorController.php](../../../Modules/Superadmin/Http/Controllers/CommunicatorController.php)
- [DataController.php](../../../Modules/Superadmin/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Superadmin/Http/Controllers/InstallController.php)
- [PackagesController.php](../../../Modules/Superadmin/Http/Controllers/PackagesController.php)
- [PageController.php](../../../Modules/Superadmin/Http/Controllers/PageController.php)
- [PesaPalController.php](../../../Modules/Superadmin/Http/Controllers/PesaPalController.php)
- [PricingController.php](../../../Modules/Superadmin/Http/Controllers/PricingController.php)
- [SubscriptionController.php](../../../Modules/Superadmin/Http/Controllers/SubscriptionController.php)
- [SuperadminController.php](../../../Modules/Superadmin/Http/Controllers/SuperadminController.php)
- [SuperadminSettingsController.php](../../../Modules/Superadmin/Http/Controllers/SuperadminSettingsController.php)
- [SuperadminSubscriptionsController.php](../../../Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController.php)
- [Usuario360Controller.php](../../../Modules/Superadmin/Http/Controllers/Usuario360Controller.php)

## Requests (validação) — 6

- [StoreBusinessRequest.php](../../../Modules/Superadmin/Http/Requests/StoreBusinessRequest.php)
- [StoreFrontendPageRequest.php](../../../Modules/Superadmin/Http/Requests/StoreFrontendPageRequest.php)
- [StorePackageRequest.php](../../../Modules/Superadmin/Http/Requests/StorePackageRequest.php)
- [UpdateBusinessPasswordRequest.php](../../../Modules/Superadmin/Http/Requests/UpdateBusinessPasswordRequest.php)
- [UpdateFrontendPageRequest.php](../../../Modules/Superadmin/Http/Requests/UpdateFrontendPageRequest.php)
- [UpdatePackageRequest.php](../../../Modules/Superadmin/Http/Requests/UpdatePackageRequest.php)

## Services — 4

- [BusinessAuditService.php](../../../Modules/Superadmin/Services/BusinessAuditService.php)
- [PackageManagerService.php](../../../Modules/Superadmin/Services/PackageManagerService.php)
- [SubscriptionLifecycleService.php](../../../Modules/Superadmin/Services/SubscriptionLifecycleService.php)
- [SuperadminDashboardService.php](../../../Modules/Superadmin/Services/SuperadminDashboardService.php)

## Models / Entities — 4

- [Package.php](../../../Modules/Superadmin/Entities/Package.php)
- [Subscription.php](../../../Modules/Superadmin/Entities/Subscription.php)
- [SuperadminCommunicatorLog.php](../../../Modules/Superadmin/Entities/SuperadminCommunicatorLog.php)
- [SuperadminFrontendPage.php](../../../Modules/Superadmin/Entities/SuperadminFrontendPage.php)

## Observers — 1

- [BusinessAutoSubscriptionObserver.php](../../../Modules/Superadmin/Observers/BusinessAutoSubscriptionObserver.php)

## Events / Listeners — 2

- [OnCobrancaPagaUpdateSubscription.php](../../../Modules/Superadmin/Listeners/OnCobrancaPagaUpdateSubscription.php)
- [OnCobrancaVencidaBloqueaSubscription.php](../../../Modules/Superadmin/Listeners/OnCobrancaVencidaBloqueaSubscription.php)

## Console / Commands — 2

- [SubscriptionExpiryAlert.php](../../../Modules/Superadmin/Console/SubscriptionExpiryAlert.php)
- [SuperadminHealthCommand.php](../../../Modules/Superadmin/Console/SuperadminHealthCommand.php)

## Providers — 2

- [RouteServiceProvider.php](../../../Modules/Superadmin/Providers/RouteServiceProvider.php)
- [SuperadminServiceProvider.php](../../../Modules/Superadmin/Providers/SuperadminServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Superadmin/Routes/api.php)
- [web.php](../../../Modules/Superadmin/Routes/web.php)

## Migrations (schema) — 12

- [2018_06_27_185405_create_packages_table.php](../../../Modules/Superadmin/Database/Migrations/2018_06_27_185405_create_packages_table.php)
- [2018_06_28_182803_create_subscriptions_table.php](../../../Modules/Superadmin/Database/Migrations/2018_06_28_182803_create_subscriptions_table.php)
- [2018_07_17_182021_add_rows_to_system_table.php](../../../Modules/Superadmin/Database/Migrations/2018_07_17_182021_add_rows_to_system_table.php)
- [2018_07_19_131721_add_options_to_packages_table.php](../../../Modules/Superadmin/Database/Migrations/2018_07_19_131721_add_options_to_packages_table.php)
- [2018_08_17_155534_add_min_termination_alert_days.php](../../../Modules/Superadmin/Database/Migrations/2018_08_17_155534_add_min_termination_alert_days.php)
- [2018_08_28_105945_add_business_based_username_settings_to_system_table.php](../../../Modules/Superadmin/Database/Migrations/2018_08_28_105945_add_business_based_username_settings_to_system_table.php)
- [2018_08_30_105906_add_superadmin_communicator_logs_table.php](../../../Modules/Superadmin/Database/Migrations/2018_08_30_105906_add_superadmin_communicator_logs_table.php)
- [2018_11_02_130636_add_custom_permissions_to_packages_table.php](../../../Modules/Superadmin/Database/Migrations/2018_11_02_130636_add_custom_permissions_to_packages_table.php)
- [2018_11_05_161848_add_more_fields_to_packages_table.php](../../../Modules/Superadmin/Database/Migrations/2018_11_05_161848_add_more_fields_to_packages_table.php)
- [2018_12_10_124621_modify_system_table_values_null_default.php](../../../Modules/Superadmin/Database/Migrations/2018_12_10_124621_modify_system_table_values_null_default.php)
- [2019_05_10_135434_add_missing_database_column_indexes.php](../../../Modules/Superadmin/Database/Migrations/2019_05_10_135434_add_missing_database_column_indexes.php)
- [2019_08_16_115300_create_superadmin_frontend_pages_table.php](../../../Modules/Superadmin/Database/Migrations/2019_08_16_115300_create_superadmin_frontend_pages_table.php)

## Seeders — 1

- [SuperadminDatabaseSeeder.php](../../../Modules/Superadmin/Database/Seeders/SuperadminDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Superadmin/Config/config.php)
- [retention.php](../../../Modules/Superadmin/Config/retention.php)

## Views (Blade) — 46

- 46 arquivos em [Modules/Superadmin/Resources/views/business/](../../../Modules/Superadmin/Resources/views/business) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Telas (Inertia/React) — 2

- [Index.tsx](../../../resources/js/Pages/superadmin/Usuario360/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/superadmin/Usuario360/Show.tsx)

## Charters (lei da tela) — 2

- [Index.charter.md](../../../resources/js/Pages/superadmin/Usuario360/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/superadmin/Usuario360/Show.charter.md)

## Testes (Pest) — 15

- 15 arquivos em [Modules/Superadmin/Tests/Feature/](../../../Modules/Superadmin/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 9

- [NewBusinessNotification.php](../../../Modules/Superadmin/Notifications/NewBusinessNotification.php)
- [NewBusinessWelcomNotification.php](../../../Modules/Superadmin/Notifications/NewBusinessWelcomNotification.php)
- [NewSubscriptionNotification.php](../../../Modules/Superadmin/Notifications/NewSubscriptionNotification.php)
- [PasswordUpdateNotification.php](../../../Modules/Superadmin/Notifications/PasswordUpdateNotification.php)
- [SendSubscriptionExpiryAlert.php](../../../Modules/Superadmin/Notifications/SendSubscriptionExpiryAlert.php)
- [SubscriptionOfflinePaymentActivationConfirmation.php](../../../Modules/Superadmin/Notifications/SubscriptionOfflinePaymentActivationConfirmation.php)
- [SuperadminCommunicator.php](../../../Modules/Superadmin/Notifications/SuperadminCommunicator.php)
- [PackagePolicy.php](../../../Modules/Superadmin/Policies/PackagePolicy.php)
- [RedactsPiiInLogs.php](../../../Modules/Superadmin/Support/RedactsPiiInLogs.php)
