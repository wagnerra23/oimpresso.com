# Módulo: AssetManagement

> **Useful for managing all kinds of assets.**

- **Alias:** `assetmanagement`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/AssetManagement`
- **Status:** 🟢 ativo
- **Providers:** Modules\AssetManagement\Providers\AssetManagementServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 4 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions, addTaxonomies
- 🔐 Registra 6 permissão(ões) Spatie

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 10 |
| Controllers | 7 |
| Entities (Models) | 4 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 17 |
| Migrations | 7 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `install` | `[Modules\AssetManagement\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[Modules\AssetManagement\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[Modules\AssetManagement\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[Modules\AssetManagement\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `dashboard` | `[Modules\AssetManagement\Http\Controllers\AssetController::class, 'dashboard']` |
| `RESOURCE` | `assets` | `Modules\AssetManagement\Http\Controllers\AssetController::class` |
| `RESOURCE` | `allocation` | `Modules\AssetManagement\Http\Controllers\AssetAllocationController::class` |
| `RESOURCE` | `revocation` | `Modules\AssetManagement\Http\Controllers\RevokeAllocatedAssetController::class` |
| `RESOURCE` | `settings` | `Modules\AssetManagement\Http\Controllers\AssetSettingsController::class` |
| `RESOURCE` | `asset-maintenance` | `'Modules\AssetManagement\Http\Controllers\AssetMaitenanceController'` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`AssetAllocationController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`AssetController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, dashboard
- **`AssetMaitenanceController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`AssetSettingsController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`DataController`** — 5 ação(ões): superadmin_package, user_permissions, addTaxonomies, modifyAdminMenu, parse_notification
- **`InstallController`** — 3 ação(ões): index, uninstall, update
- **`RevokeAllocatedAssetController`** — 7 ação(ões): index, create, store, show, edit, update, destroy

## Entities (Models Eloquent)

- **`Asset`** (tabela: `—`)
- **`AssetMaintenance`** (tabela: `—`)
- **`AssetTransaction`** (tabela: `—`)
- **`AssetWarranty`** (tabela: `—`)

## Migrations

- `2020_08_19_175842_add_asset_management_module_version_to_system_table.php`
- `2020_08_20_114339_create_assets_table.php`
- `2020_08_20_173031_create_asset_transactions_table.php`
- `2020_08_21_180138_add_asset_settings_column_to_business_table.php`
- `2021_10_29_110841_create_asset_warranties_table.php`
- `2022_03_26_062215_create_asset_maintenances_table.php`
- `2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table.php`

## Views (Blade)

**Total:** 17 arquivos

**Pastas principais:**

- `asset/` — 4 arquivo(s)
- `asset_allocation/` — 3 arquivo(s)
- `asset_maintenance/` — 3 arquivo(s)
- `settings/` — 3 arquivo(s)
- `asset_revocation/` — 2 arquivo(s)
- `D:/` — 1 arquivo(s)
- `layouts/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — Registra taxonomias/categorias customizadas

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `asset.view`
- `asset.create`
- `asset.update`
- `asset.delete`
- `asset.view_all_maintenance`
- `asset.view_own_maintenance`

**Usadas nas views** (`@can`/`@cannot`):

- `asset.create`
- `asset.view`
- `only_admin`
- `asset.view_all_maintenance`
- `asset.view_own_maintenance`

## Peças adicionais

- **Notifications:** `AssetAssignedForMaintenance`, `AssetSentForMaintenance`
- **Seeders:** `AssetManagementDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `AssetManagement` |
| `module_version` | `2.0` |
| `pid` | `14` |

## Integridade do banco

**Foreign Keys** (8):

- `business_id` → `business.id`
- `category_id` → `categories.id`
- `created_by` → `users.id`
- `business_id` → `business.id`
- `asset_id` → `assets.id`
- `receiver` → `users.id`
- `parent_id` → `asset_transactions.id`
- `created_by` → `users.id`

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

- `js\assetmanagement.js` (0 B)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\assetmanagement.css` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 80
- **Linhas +:** 6389 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_08_19_175842_add_asset_management_module_version_to_system_table.php`
  - `Database/Migrations/2020_08_20_114339_create_assets_table.php`
  - `Database/Migrations/2020_08_20_173031_create_asset_transactions_table.php`
  - `Database/Migrations/2020_08_21_180138_add_asset_settings_column_to_business_table.php`
  - `Database/Migrations/2021_10_29_110841_create_asset_warranties_table.php`
  - `Database/Migrations/2022_03_26_062215_create_asset_maintenances_table.php`
  - `Database/Migrations/2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AssetManagementDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Asset.php`
  - `Entities/AssetMaintenance.php`
  - `Entities/AssetTransaction.php`
  - `Entities/AssetWarranty.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/AssetAllocationController.php`
  - `Http/Controllers/AssetController.php`
  - `Http/Controllers/AssetMaitenanceController.php`
  - `Http/Controllers/AssetSettingsController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/RevokeAllocatedAssetController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/AssetAssignedForMaintenance.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 80
- **Linhas +:** 6389 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_08_19_175842_add_asset_management_module_version_to_system_table.php`
  - `Database/Migrations/2020_08_20_114339_create_assets_table.php`
  - `Database/Migrations/2020_08_20_173031_create_asset_transactions_table.php`
  - `Database/Migrations/2020_08_21_180138_add_asset_settings_column_to_business_table.php`
  - `Database/Migrations/2021_10_29_110841_create_asset_warranties_table.php`
  - `Database/Migrations/2022_03_26_062215_create_asset_maintenances_table.php`
  - `Database/Migrations/2022_05_11_070711_add_maintenance_note_column_to_asset_maintenances_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AssetManagementDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Asset.php`
  - `Entities/AssetMaintenance.php`
  - `Entities/AssetTransaction.php`
  - `Entities/AssetWarranty.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/AssetAllocationController.php`
  - `Http/Controllers/AssetController.php`
  - `Http/Controllers/AssetMaitenanceController.php`
  - `Http/Controllers/AssetSettingsController.php`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/RevokeAllocatedAssetController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/AssetAssignedForMaintenance.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:13.**
**Reaxecutar com:** `php artisan module:spec AssetManagement`
