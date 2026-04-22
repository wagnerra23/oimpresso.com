# Módulo: Spreadsheet

> **Allows you to create spreadsheet and share with employees, roles & todos**

- **Alias:** `spreadsheet`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Spreadsheet`
- **Status:** 🟢 ativo
- **Providers:** Modules\Spreadsheet\Providers\SpreadsheetServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔐 Registra 2 permissão(ões) Spatie

- **Prioridade sugerida de migração:** média
- **Risco estimado:** médio

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 9 |
| Controllers | 3 |
| Entities (Models) | 2 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 7 |
| Migrations | 4 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `get-sheet/{id}/share` | `[\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'getShareSpreadsheet']` |
| `POST` | `post-share-sheet` | `[\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'postShareSpreadsheet']` |
| `GET` | `install` | `[\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[\Modules\Spreadsheet\Http\Controllers\InstallController::class, 'update']` |
| `POST` | `add-folder` | `[\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'addFolder']` |
| `POST` | `move-to-folder` | `[\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class, 'moveToFolder']` |
| `RESOURCE` | `sheets` | `\Modules\Spreadsheet\Http\Controllers\SpreadsheetController::class` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`DataController`** — 5 ação(ões): superadmin_package, user_permissions, modifyAdminMenu, parse_notification, getSharedSpreadsheetForGivenData
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`SpreadsheetController`** — 12 ação(ões): index, create, store, show, edit, update, destroy, getShareSpreadsheet +4

## Entities (Models Eloquent)

- **`Spreadsheet`** (tabela: `sheet_spreadsheets`)
- **`SpreadsheetShare`** (tabela: `sheet_spreadsheet_shares`)

## Migrations

- `2020_12_23_125610_add_spreadsheet_version_to_system_table.php`
- `2020_12_23_153255_create_spreadsheets_table.php`
- `2021_03_12_175416_create_spreadsheet_shares_table.php`
- `2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table.php`

## Views (Blade)

**Total:** 7 arquivos

**Pastas principais:**

- `sheet/` — 4 arquivo(s)
- `layouts/` — 2 arquivo(s)
- `D:/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `access.spreadsheet`
- `create.spreadsheet`

**Usadas nas views** (`@can`/`@cannot`):

- `create.spreadsheet`
- `superadmin`

## Peças adicionais

- **Notifications:** `SpreadsheetShared`
- **Seeders:** `SpreadsheetDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Spreadsheet` |
| `module_version` | `1.0` |
| `pid` | `13` |

## Integridade do banco

**Foreign Keys** (2):

- `business_id` → `business.id`
- `sheet_spreadsheet_id` → `sheet_spreadsheets.id`

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
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 59
- **Linhas +:** 2545 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_12_23_125610_add_spreadsheet_version_to_system_table.php`
  - `Database/Migrations/2020_12_23_153255_create_spreadsheets_table.php`
  - `Database/Migrations/2021_03_12_175416_create_spreadsheet_shares_table.php`
  - `Database/Migrations/2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/SpreadsheetDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Spreadsheet.php`
  - `Entities/SpreadsheetShare.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/SpreadsheetController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/SpreadsheetShared.php`
  - `Providers/.gitkeep`
  - `Providers/RouteServiceProvider.php`
  - `Providers/SpreadsheetServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 59
- **Linhas +:** 2545 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_12_23_125610_add_spreadsheet_version_to_system_table.php`
  - `Database/Migrations/2020_12_23_153255_create_spreadsheets_table.php`
  - `Database/Migrations/2021_03_12_175416_create_spreadsheet_shares_table.php`
  - `Database/Migrations/2023_01_16_124948_add_folder_id_column_to_sheet_spreadsheets_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/SpreadsheetDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/Spreadsheet.php`
  - `Entities/SpreadsheetShare.php`
  - `Http/Controllers/.gitkeep`
  - `Http/Controllers/DataController.php`
  - `Http/Controllers/InstallController.php`
  - `Http/Controllers/SpreadsheetController.php`
  - `Http/Middleware/.gitkeep`
  - `Http/Requests/.gitkeep`
  - `Notifications/SpreadsheetShared.php`
  - `Providers/.gitkeep`
  - `Providers/RouteServiceProvider.php`
  - `Providers/SpreadsheetServiceProvider.php`
  - `Resources/assets/.gitkeep`
  - `Resources/assets/js/app.js`
  - `Resources/assets/sass/app.scss`
  - `Resources/lang/.gitkeep`
  - `Resources/lang/ar/lang.php`
  - `Resources/lang/ce/lang.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Spreadsheet`
