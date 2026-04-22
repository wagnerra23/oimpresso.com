# Módulo: Superadmin

> **Allows you to create packages & sell subscription to multiple businesses**

- **Alias:** `superadmin`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Superadmin`
- **Status:** 🟢 ativo
- **Providers:** Modules\Superadmin\Providers\SuperadminServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 2 hook(s) UltimatePOS: modifyAdminMenu, user_permissions
- 🟡 33 rotas — escopo médio
- 🔐 Registra 1 permissão(ões) Spatie

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 33 |
| Controllers | 13 |
| Entities (Models) | 4 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 45 |
| Migrations | 12 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/pricing` | `[Modules\Superadmin\Http\Controllers\PricingController::class, 'index']` |
| `GET` | `/install` | `[Modules\Superadmin\Http\Controllers\InstallController::class, 'index']` |
| `GET` | `/install/update` | `[Modules\Superadmin\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `/install/uninstall` | `[Modules\Superadmin\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/` | `[Modules\Superadmin\Http\Controllers\SuperadminController::class, 'index']` |
| `GET` | `/stats` | `[Modules\Superadmin\Http\Controllers\SuperadminController::class, 'stats']` |
| `GET` | `/{business_id}/toggle-active/{is_active}` | `[Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive']` |
| `GET` | `/users/{business_id}` | `[Modules\Superadmin\Http\Controllers\BusinessController::class, 'usersList']` |
| `POST` | `/update-password` | `[Modules\Superadmin\Http\Controllers\BusinessController::class, 'updatePassword']` |
| `GET` | `/business/{id}/destroy` | `[Modules\Superadmin\Http\Controllers\BusinessController::class, 'destroy']` |
| `GET` | `/packages/{id}/destroy` | `[Modules\Superadmin\Http\Controllers\PackagesController::class, 'destroy']` |
| `GET` | `/settings` | `[Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit']` |
| `PUT` | `/settings` | `[Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'update']` |
| `GET` | `/edit-subscription/{id}` | `[Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'editSubscription']` |
| `POST` | `/update-subscription` | `[Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'updateSubscription']` |
| `GET` | `/communicator` | `[Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'index']` |
| `POST` | `/communicator/send` | `[Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'send']` |
| `GET` | `/communicator/get-history` | `[Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'getHistory']` |
| `GET` | `/subscription/{package_id}/paypal-express-checkout` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'paypalExpressCheckout']` |
| `GET` | `/subscription/post-flutterwave-payment` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postFlutterwavePaymentCallback']` |
| `POST` | `/subscription/pay-stack` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'getRedirectToPaystack']` |
| `GET` | `/subscription/post-payment-pay-stack-callback` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postPaymentPaystackCallback']` |
| `GET` | `/subscription/{package_id}/pesapal-callback` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pesapalCallback']` |
| `GET` | `/subscription/{package_id}/pay` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay']` |
| `ANY` | `/subscription/{package_id}/confirm` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm']` |
| `GET` | `/all-subscriptions` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'allSubscriptions']` |
| `GET` | `/subscription/{package_id}/register-pay` | `[Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'registerPay']` |
| `GET` | `/page/{slug}` | `[Modules\Superadmin\Http\Controllers\PageController::class, 'showPage']` |
| `RESOURCE` | `/business` | `Modules\Superadmin\Http\Controllers\BusinessController::class` |
| `RESOURCE` | `/packages` | `'Modules\Superadmin\Http\Controllers\PackagesController'` |
| `RESOURCE` | `/superadmin-subscription` | `'Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController'` |
| `RESOURCE` | `/frontend-pages` | `'Modules\Superadmin\Http\Controllers\PageController'` |
| `RESOURCE` | `/subscription` | `'Modules\Superadmin\Http\Controllers\SubscriptionController'` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`BaseController`** — 2 ação(ões): _payment_gateways, _add_subscription
- **`BusinessController`** — 10 ação(ões): index, create, store, show, edit, update, destroy, toggleActive +2
- **`CommunicatorController`** — 3 ação(ões): index, send, getHistory
- **`DataController`** — 4 ação(ões): parse_notification, after_business_created, modifyAdminMenu, user_permissions
- **`InstallController`** — 3 ação(ões): index, update, uninstall
- **`PackagesController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`PageController`** — 7 ação(ões): index, create, store, showPage, edit, update, destroy
- **`PesaPalController`** — 1 ação(ões): pesaPalPaymentConfirmation
- **`PricingController`** — 1 ação(ões): index
- **`SubscriptionController`** — 10 ação(ões): index, pay, registerPay, confirm, paypalExpressCheckout, getRedirectToPaystack, postPaymentPaystackCallback, postFlutterwavePaymentCallback +2
- **`SuperadminController`** — 2 ação(ões): index, stats
- **`SuperadminSettingsController`** — 2 ação(ões): edit, update
- **`SuperadminSubscriptionsController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, editSubscription +1

## Entities (Models Eloquent)

- **`Package`** (tabela: `—`)
- **`Subscription`** (tabela: `—`)
- **`SuperadminCommunicatorLog`** (tabela: `—`)
- **`SuperadminFrontendPage`** (tabela: `—`)

## Migrations

- `2018_06_27_185405_create_packages_table.php`
- `2018_06_28_182803_create_subscriptions_table.php`
- `2018_07_17_182021_add_rows_to_system_table.php`
- `2018_07_19_131721_add_options_to_packages_table.php`
- `2018_08_17_155534_add_min_termination_alert_days.php`
- `2018_08_28_105945_add_business_based_username_settings_to_system_table.php`
- `2018_08_30_105906_add_superadmin_communicator_logs_table.php`
- `2018_11_02_130636_add_custom_permissions_to_packages_table.php`
- `2018_11_05_161848_add_more_fields_to_packages_table.php`
- `2018_12_10_124621_modify_system_table_values_null_default.php`
- `2019_05_10_135434_add_missing_database_column_indexes.php`
- `2019_08_16_115300_create_superadmin_frontend_pages_table.php`

## Views (Blade)

**Total:** 45 arquivos

**Pastas principais:**

- `subscription/` — 13 arquivo(s)
- `superadmin_settings/` — 9 arquivo(s)
- `layouts/` — 5 arquivo(s)
- `business/` — 4 arquivo(s)
- `pages/` — 4 arquivo(s)
- `superadmin_subscription/` — 4 arquivo(s)
- `packages/` — 3 arquivo(s)
- `communicator/` — 1 arquivo(s)
- `pricing/` — 1 arquivo(s)
- `superadmin/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `superadmin.access_package_subscriptions`

**Usadas nas views** (`@can`/`@cannot`):

- `superadmin`
- `user.view`
- `subscribe`

## Processamento / eventos

**Commands (artisan):** `SubscriptionExpiryAlert`

## Peças adicionais

- **Notifications:** `NewBusinessNotification`, `NewBusinessWelcomNotification`, `NewSubscriptionNotification`, `PasswordUpdateNotification`, `SendSubscriptionExpiryAlert`, `SubscriptionOfflinePaymentActivationConfirmation`, `SuperadminCommunicator`
- **Seeders:** `SuperadminDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Superadmin` |
| `module_version` | `4.0` |

## Integridade do banco

**Foreign Keys** (1):

- `business_id` → `business.id`

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 1 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 1 |
| Imagens | 0 |

- Build: **Laravel Mix** (webpack.mix.js presente)
- `package.json` presente
- **Deps JS:** `cross-env`, `laravel-mix`, `laravel-mix-merge-manifest`

**Arquivos JS** (primeiros 1):

- `js\app.js` (0 B)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\app.scss` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 124
- **Linhas +:** 11272 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/SubscriptionExpiryAlert.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_06_27_185405_create_packages_table.php`
  - `Database/Migrations/2018_06_28_182803_create_subscriptions_table.php`
  - `Database/Migrations/2018_07_17_182021_add_rows_to_system_table.php`
  - `Database/Migrations/2018_07_19_131721_add_options_to_packages_table.php`
  - `Database/Migrations/2018_08_17_155534_add_min_termination_alert_days.php`
  - `Database/Migrations/2018_08_28_105945_add_business_based_username_settings_to_system_table.php`
  - `Database/Migrations/2018_08_30_105906_add_superadmin_communicator_logs_table.php`
  - `Database/Migrations/2018_11_02_130636_add_custom_permissions_to_packages_table.php`
  - `Database/Migrations/2018_11_05_161848_add_more_fields_to_packages_table.php`
  - `Database/Migrations/2018_12_10_124621_modify_system_table_values_null_default.php`
  - `Database/Migrations/2019_05_10_135434_add_missing_database_column_indexes.php`
  - `Database/Migrations/2019_08_16_115300_create_superadmin_frontend_pages_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/SuperadminDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Package.php`
  - `Entities/Subscription.php`
  - `Entities/SuperadminCommunicatorLog.php`
  - `Entities/SuperadminFrontendPage.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/BaseController.php`
  - `Http/Controllers/BusinessController.php`
  - `Http/Controllers/CommunicatorController.php`
  - `Http/Controllers/DataController.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 124
- **Linhas +:** 11272 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/SubscriptionExpiryAlert.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_06_27_185405_create_packages_table.php`
  - `Database/Migrations/2018_06_28_182803_create_subscriptions_table.php`
  - `Database/Migrations/2018_07_17_182021_add_rows_to_system_table.php`
  - `Database/Migrations/2018_07_19_131721_add_options_to_packages_table.php`
  - `Database/Migrations/2018_08_17_155534_add_min_termination_alert_days.php`
  - `Database/Migrations/2018_08_28_105945_add_business_based_username_settings_to_system_table.php`
  - `Database/Migrations/2018_08_30_105906_add_superadmin_communicator_logs_table.php`
  - `Database/Migrations/2018_11_02_130636_add_custom_permissions_to_packages_table.php`
  - `Database/Migrations/2018_11_05_161848_add_more_fields_to_packages_table.php`
  - `Database/Migrations/2018_12_10_124621_modify_system_table_values_null_default.php`
  - `Database/Migrations/2019_05_10_135434_add_missing_database_column_indexes.php`
  - `Database/Migrations/2019_08_16_115300_create_superadmin_frontend_pages_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/SuperadminDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Package.php`
  - `Entities/Subscription.php`
  - `Entities/SuperadminCommunicatorLog.php`
  - `Entities/SuperadminFrontendPage.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/BaseController.php`
  - `Http/Controllers/BusinessController.php`
  - `Http/Controllers/CommunicatorController.php`
  - `Http/Controllers/DataController.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Superadmin`
