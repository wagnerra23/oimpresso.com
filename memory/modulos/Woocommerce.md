# Módulo: Woocommerce

> **Allows you to connect POS with WooCommerce website.**

- **Alias:** `woocommerce`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Woocommerce`
- **Status:** 🟢 ativo
- **Providers:** Modules\Woocommerce\Providers\WoocommerceServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔐 Registra 5 permissão(ões) Spatie

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 19 |
| Controllers | 4 |
| Entities (Models) | 1 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 13 |
| Migrations | 13 |
| Arquivos de lang | 10 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `POST` | `/webhook/order-created/{business_id}` | `[Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderCreated']` |
| `POST` | `/webhook/order-updated/{business_id}` | `[Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderUpdated']` |
| `POST` | `/webhook/order-deleted/{business_id}` | `[Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderDeleted']` |
| `POST` | `/webhook/order-restored/{business_id}` | `[Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController::class, 'orderRestored']` |
| `GET` | `/install` | `[Modules\Woocommerce\Http\Controllers\InstallController::class, 'index']` |
| `GET` | `/install/update` | `[Modules\Woocommerce\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `/install/uninstall` | `[Modules\Woocommerce\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'index']` |
| `GET` | `/api-settings` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'apiSettings']` |
| `POST` | `/update-api-settings` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'updateSettings']` |
| `GET` | `/sync-categories` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncCategories']` |
| `GET` | `/sync-products` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncProducts']` |
| `GET` | `/sync-log` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getSyncLog']` |
| `GET` | `/sync-orders` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'syncOrders']` |
| `POST` | `/map-taxrates` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'mapTaxRates']` |
| `GET` | `/view-sync-log` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'viewSyncLog']` |
| `GET` | `/get-log-details/{id}` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'getLogDetails']` |
| `GET` | `/reset-categories` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetCategories']` |
| `GET` | `/reset-products` | `[Modules\Woocommerce\Http\Controllers\WoocommerceController::class, 'resetProducts']` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 7 ação(ões): dummy_data, superadmin_package, user_permissions, parse_notification, product_form_part, product_form_fields, modifyAdminMenu
- **`InstallController`** — 3 ação(ões): index, update, uninstall
- **`WoocommerceController`** — 12 ação(ões): index, apiSettings, updateSettings, syncCategories, syncProducts, syncOrders, getSyncLog, mapTaxRates +4
- **`WoocommerceWebhookController`** — 4 ação(ões): orderCreated, orderUpdated, orderDeleted, orderRestored

## Entities (Models Eloquent)

- **`WoocommerceSyncLog`** (tabela: `—`)

## Migrations

- `2018_10_10_110400_add_module_version_to_system_table.php`
- `2018_10_10_122845_add_woocommerce_api_settings_to_business_table.php`
- `2018_10_10_162041_add_woocommerce_category_id_to_categories_table.php`
- `2018_10_11_173839_create_woocommerce_sync_logs_table.php`
- `2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table.php`
- `2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table.php`
- `2018_12_03_163945_add_woocommerce_permissions.php`
- `2019_02_18_154414_change_woocommerce_sync_logs_table.php`
- `2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table.php`
- `2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table.php`
- `2019_10_01_171828_add_woocommerce_media_id_columns.php`
- `2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table.php`
- `2021_02_16_190608_add_woocommerce_module_indexing.php`

## Views (Blade)

**Total:** 13 arquivos

**Pastas principais:**

- `woocommerce/` — 10 arquivo(s)
- `layouts/` — 3 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `woocommerce.syc_categories`
- `woocommerce.sync_products`
- `woocommerce.sync_orders`
- `woocommerce.map_tax_rates`
- `woocommerce.access_woocommerce_api_settings`

**Usadas nas views** (`@can`/`@cannot`):

- `woocommerce.access_woocommerce_api_settings`
- `superadmin`
- `woocommerce.syc_categories`
- `woocommerce.sync_products`
- `woocommerce.sync_orders`
- `woocommerce.map_tax_rates`

## Processamento / eventos

**Commands (artisan):** `WooCommerceSyncOrder`, `WoocommerceSyncProducts`

## Peças adicionais

- **Notifications:** `SyncOrdersNotification`
- **Seeders:** `AddDummySyncLogTableSeeder`, `WoocommerceDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Woocommerce` |
| `module_version` | `4.0` |

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

- **Arquivos alterados:** 73
- **Linhas +:** 5944 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/WooCommerceSyncOrder.php`
  - `Console/WoocommerceSyncProducts.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_10_10_110400_add_module_version_to_system_table.php`
  - `Database/Migrations/2018_10_10_122845_add_woocommerce_api_settings_to_business_table.php`
  - `Database/Migrations/2018_10_10_162041_add_woocommerce_category_id_to_categories_table.php`
  - `Database/Migrations/2018_10_11_173839_create_woocommerce_sync_logs_table.php`
  - `Database/Migrations/2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table.php`
  - `Database/Migrations/2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table.php`
  - `Database/Migrations/2018_12_03_163945_add_woocommerce_permissions.php`
  - `Database/Migrations/2019_02_18_154414_change_woocommerce_sync_logs_table.php`
  - `Database/Migrations/2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table.php`
  - `Database/Migrations/2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table.php`
  - `Database/Migrations/2019_10_01_171828_add_woocommerce_media_id_columns.php`
  - `Database/Migrations/2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table.php`
  - `Database/Migrations/2021_02_16_190608_add_woocommerce_module_indexing.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AddDummySyncLogTableSeeder.php`
  - `Database/Seeders/WoocommerceDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/WoocommerceSyncLog.php`
  - `Exceptions/WooCommerceError.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/WoocommerceController.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 73
- **Linhas +:** 5944 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/WooCommerceSyncOrder.php`
  - `Console/WoocommerceSyncProducts.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_10_10_110400_add_module_version_to_system_table.php`
  - `Database/Migrations/2018_10_10_122845_add_woocommerce_api_settings_to_business_table.php`
  - `Database/Migrations/2018_10_10_162041_add_woocommerce_category_id_to_categories_table.php`
  - `Database/Migrations/2018_10_11_173839_create_woocommerce_sync_logs_table.php`
  - `Database/Migrations/2018_10_16_123522_add_woocommerce_tax_rate_id_column_to_tax_rates_table.php`
  - `Database/Migrations/2018_10_23_111555_add_woocommerce_attr_id_column_to_variation_templates_table.php`
  - `Database/Migrations/2018_12_03_163945_add_woocommerce_permissions.php`
  - `Database/Migrations/2019_02_18_154414_change_woocommerce_sync_logs_table.php`
  - `Database/Migrations/2019_04_19_174129_add_disable_woocommerce_sync_column_to_products_table.php`
  - `Database/Migrations/2019_06_08_132440_add_woocommerce_wh_oc_secret_column_to_business_table.php`
  - `Database/Migrations/2019_10_01_171828_add_woocommerce_media_id_columns.php`
  - `Database/Migrations/2020_09_07_124952_add_woocommerce_skipped_orders_fields_to_business_table.php`
  - `Database/Migrations/2021_02_16_190608_add_woocommerce_module_indexing.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AddDummySyncLogTableSeeder.php`
  - `Database/Seeders/WoocommerceDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/WoocommerceSyncLog.php`
  - `Exceptions/WooCommerceError.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/WoocommerceController.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Woocommerce`
