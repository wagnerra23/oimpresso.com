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
> **O que isto é:** o inventário completo das raízes `Modules/Superadmin/**` + `resources/js/Pages/superadmin/**` (namespace Inertia `superadmin`, declarado em `module-surface.mjs::PAGES_NS` porque difere do nome do módulo `Superadmin`), separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 162 arquivos em 17 papéis.

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

- [create.blade.php](../../../Modules/Superadmin/Resources/views/business/create.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/business/index.blade.php)
- [show.blade.php](../../../Modules/Superadmin/Resources/views/business/show.blade.php)
- [update_password_modal.blade.php](../../../Modules/Superadmin/Resources/views/business/update_password_modal.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/communicator/index.blade.php)
- [nav.blade.php](../../../Modules/Superadmin/Resources/views/layouts/nav.blade.php)
- [active_subscription.blade.php](../../../Modules/Superadmin/Resources/views/layouts/partials/active_subscription.blade.php)
- [currency.blade.php](../../../Modules/Superadmin/Resources/views/layouts/partials/currency.blade.php)
- [sidebar.blade.php](../../../Modules/Superadmin/Resources/views/layouts/partials/sidebar.blade.php)
- [subscription.blade.php](../../../Modules/Superadmin/Resources/views/layouts/partials/subscription.blade.php)
- [create.blade.php](../../../Modules/Superadmin/Resources/views/packages/create.blade.php)
- [edit.blade.php](../../../Modules/Superadmin/Resources/views/packages/edit.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/packages/index.blade.php)
- [create.blade.php](../../../Modules/Superadmin/Resources/views/pages/create.blade.php)
- [edit.blade.php](../../../Modules/Superadmin/Resources/views/pages/edit.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/pages/index.blade.php)
- [show.blade.php](../../../Modules/Superadmin/Resources/views/pages/show.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/pricing/index.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/subscription/index.blade.php)
- [max_location_modal.blade.php](../../../Modules/Superadmin/Resources/views/subscription/max_location_modal.blade.php)
- [packages.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/packages.blade.php)
- [pay_flutterwave.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_flutterwave.blade.php)
- [pay_offline.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_offline.blade.php)
- [pay_paymentgateway_pix_automatico.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_paymentgateway_pix_automatico.blade.php)
- [pay_paypal.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_paypal.blade.php)
- [pay_paystack.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_paystack.blade.php)
- [pay_pesapal.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_pesapal.blade.php)
- [pay_razorpay.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_razorpay.blade.php)
- [pay_stripe.blade.php](../../../Modules/Superadmin/Resources/views/subscription/partials/pay_stripe.blade.php)
- [pay.blade.php](../../../Modules/Superadmin/Resources/views/subscription/pay.blade.php)
- [show_subscription_modal.blade.php](../../../Modules/Superadmin/Resources/views/subscription/show_subscription_modal.blade.php)
- [subscription_expired_modal.blade.php](../../../Modules/Superadmin/Resources/views/subscription/subscription_expired_modal.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/superadmin/index.blade.php)
- [edit.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/edit.blade.php)
- [additional_js_css.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/additional_js_css.blade.php)
- [application_settings.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/application_settings.blade.php)
- [backup.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/backup.blade.php)
- [cron.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/cron.blade.php)
- [email_smtp_settings.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/email_smtp_settings.blade.php)
- [payment_gateways.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/payment_gateways.blade.php)
- [pusher_setting.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/pusher_setting.blade.php)
- [super_admin_settings.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_settings/partials/super_admin_settings.blade.php)
- [add_subscription.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_subscription/add_subscription.blade.php)
- [edit.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_subscription/edit.blade.php)
- [edit_date_modal.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_subscription/edit_date_modal.blade.php)
- [index.blade.php](../../../Modules/Superadmin/Resources/views/superadmin_subscription/index.blade.php)

## Telas (Inertia/React) — 2

- [Index.tsx](../../../resources/js/Pages/superadmin/Usuario360/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/superadmin/Usuario360/Show.tsx)

## Charters (lei da tela) — 2

- [Index.charter.md](../../../resources/js/Pages/superadmin/Usuario360/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/superadmin/Usuario360/Show.charter.md)

## Testes (Pest) — 15

- 14 em [Modules/Superadmin/Tests/Feature/](../../../Modules/Superadmin/Tests/Feature)
- 1 em [Modules/Superadmin/Tests/Feature/Lgpd/](../../../Modules/Superadmin/Tests/Feature/Lgpd)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 45

- [.gitkeep](../../../Modules/Superadmin/Config/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Console/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Http/Requests/.gitkeep)
- [NewBusinessNotification.php](../../../Modules/Superadmin/Notifications/NewBusinessNotification.php)
- [NewBusinessWelcomNotification.php](../../../Modules/Superadmin/Notifications/NewBusinessWelcomNotification.php)
- [NewSubscriptionNotification.php](../../../Modules/Superadmin/Notifications/NewSubscriptionNotification.php)
- [PasswordUpdateNotification.php](../../../Modules/Superadmin/Notifications/PasswordUpdateNotification.php)
- [SendSubscriptionExpiryAlert.php](../../../Modules/Superadmin/Notifications/SendSubscriptionExpiryAlert.php)
- [SubscriptionOfflinePaymentActivationConfirmation.php](../../../Modules/Superadmin/Notifications/SubscriptionOfflinePaymentActivationConfirmation.php)
- [SuperadminCommunicator.php](../../../Modules/Superadmin/Notifications/SuperadminCommunicator.php)
- [PackagePolicy.php](../../../Modules/Superadmin/Policies/PackagePolicy.php)
- [.gitkeep](../../../Modules/Superadmin/Providers/.gitkeep)
- [.gitkeep](../../../Modules/Superadmin/Resources/assets/.gitkeep)
- [app.js](../../../Modules/Superadmin/Resources/assets/js/app.js)
- [app.scss](../../../Modules/Superadmin/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Superadmin/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Superadmin/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Superadmin/Resources/lang/vi/lang.php)
- [topnav.php](../../../Modules/Superadmin/Resources/menus/topnav.php)
- [.gitkeep](../../../Modules/Superadmin/Resources/views/.gitkeep)
- [RedactsPiiInLogs.php](../../../Modules/Superadmin/Support/RedactsPiiInLogs.php)
- [.gitkeep](../../../Modules/Superadmin/Tests/.gitkeep)
- [composer.json](../../../Modules/Superadmin/composer.json)
- [module.json](../../../Modules/Superadmin/module.json)
- [SCOPE.md](../../../memory/requisitos/Superadmin/SCOPE.md)
