# Módulo: Repair

> **Useful for all kind of repair shops**

- **Alias:** `repair`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Repair`
- **Status:** 🟢 ativo
- **Providers:** Modules\Repair\Providers\RepairServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 7 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions, get_pos_screen_view, after_sale_saved, after_product_saved, addTaxonomies
- 🟡 35 rotas — escopo médio
- 🔴 +50 views — trabalho pesado
- ✅ Tem testes (22)
- 🔐 Registra 12 permissão(ões) Spatie
- ⚙️ Processamento assíncrono: 1 peça(s) (jobs/events/listeners)
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)
- 🗃️ 13 foreign keys — alto acoplamento em dados

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 35 |
| Controllers | 11 |
| Entities (Models) | 3 |
| Services | 1 |
| FormRequests | 6 |
| Middleware | 0 |
| Views Blade | 52 |
| Migrations | 18 |
| Arquivos de lang | 16 |
| Testes | 22 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/repair-status` | `[Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'index']` |
| `POST` | `/post-repair-status` | `[Modules\Repair\Http\Controllers\CustomerRepairStatusController::class, 'postRepairStatus']` |
| `GET` | `edit-repair/{id}/status` | `[Modules\Repair\Http\Controllers\RepairController::class, 'editRepairStatus']` |
| `POST` | `update-repair-status` | `[Modules\Repair\Http\Controllers\RepairController::class, 'updateRepairStatus']` |
| `GET` | `delete-media/{id}` | `[Modules\Repair\Http\Controllers\RepairController::class, 'deleteMedia']` |
| `GET` | `print-label/{id}` | `[Modules\Repair\Http\Controllers\RepairController::class, 'printLabel']` |
| `GET` | `print-repair/{transaction_id}/customer-copy` | `[Modules\Repair\Http\Controllers\RepairController::class, 'printCustomerCopy']` |
| `GET` | `/install` | `[Modules\Repair\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `/install` | `[Modules\Repair\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `/install/uninstall` | `[Modules\Repair\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/install/update` | `[Modules\Repair\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `get-device-models` | `[Modules\Repair\Http\Controllers\DeviceModelController::class, 'getDeviceModels']` |
| `GET` | `models-repair-checklist` | `[Modules\Repair\Http\Controllers\DeviceModelController::class, 'getRepairChecklists']` |
| `POST` | `job-sheet-post-upload-docs` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'postUploadDocs']` |
| `GET` | `job-sheet/{id}/upload-docs` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'getUploadDocs']` |
| `GET` | `job-sheet/print/{id}` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'print']` |
| `GET` | `job-sheet/delete/{id}/image` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'deleteJobSheetImage']` |
| `GET` | `job-sheet/{id}/status` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'editStatus']` |
| `PUT` | `job-sheet-update/{id}/status` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'updateStatus']` |
| `GET` | `job-sheet/add-parts/{id}` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'addParts']` |
| `POST` | `job-sheet/save-parts/{id}` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'saveParts']` |
| `POST` | `job-sheet/get-part-row` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'jobsheetPartRow']` |
| `POST` | `update-repair-jobsheet-settings` | `[Modules\Repair\Http\Controllers\RepairSettingsController::class, 'updateJobsheetSettings']` |
| `GET` | `job-sheet/print-label/{id}` | `[Modules\Repair\Http\Controllers\JobSheetController::class, 'printLabel']` |
| `GET` | `producao-oficina` | `[Modules\Repair\Http\Controllers\ProducaoOficinaController::class, 'index']` |
| `POST` | `producao-oficina/{id}/move` | `[Modules\Repair\Http\Controllers\ProducaoOficinaController::class, 'move']` |
| `GET` | `/api/repair/job-sheets/{id}/fsm-actions` | `[Modules\Repair\Http\Controllers\RepairFsmActionController::class, 'actions']` |
| `POST` | `/repair/job-sheets/{id}/fsm-action` | `[Modules\Repair\Http\Controllers\RepairFsmActionController::class, 'execute']` |
| `POST` | `/repair/job-sheets/{id}/fsm-start-pipeline` | `[Modules\Repair\Http\Controllers\RepairFsmActionController::class, 'startPipeline']` |
| `RESOURCE` | `/repair` | `'Modules\Repair\Http\Controllers\RepairController'` |
| `RESOURCE` | `/status` | `'Modules\Repair\Http\Controllers\RepairStatusController'` |
| `RESOURCE` | `/repair-settings` | `'Modules\Repair\Http\Controllers\RepairSettingsController'` |
| `RESOURCE` | `device-models` | `'Modules\Repair\Http\Controllers\DeviceModelController'` |
| `RESOURCE` | `dashboard` | `'Modules\Repair\Http\Controllers\DashboardController'` |
| `RESOURCE` | `job-sheet` | `'Modules\Repair\Http\Controllers\JobSheetController'` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`CustomerRepairStatusController`** — 2 ação(ões): index, postRepairStatus
- **`DashboardController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`DataController`** — 9 ação(ões): after_sale_saved, user_permissions, superadmin_package, modifyAdminMenu, get_pos_screen_view, addTaxonomies, get_product_screen_top_view, after_product_saved +1
- **`DeviceModelController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, getDeviceModels +1
- **`InstallController`** — 0 ação(ões): 
- **`JobSheetController`** — 17 ação(ões): index, create, store, show, edit, update, destroy, editStatus +9
- **`ProducaoOficinaController`** — 2 ação(ões): index, move
- **`RepairController`** — 9 ação(ões): index, create, show, edit, editRepairStatus, updateRepairStatus, deleteMedia, printLabel +1
- **`RepairFsmActionController`** — 3 ação(ões): actions, execute, startPipeline
- **`RepairSettingsController`** — 3 ação(ões): index, store, updateJobsheetSettings
- **`RepairStatusController`** — 5 ação(ões): index, create, store, edit, update

## Entities (Models Eloquent)

- **`DeviceModel`** (tabela: `repair_device_models`)
- **`JobSheet`** (tabela: `repair_job_sheets`)
- **`RepairStatus`** (tabela: `—`)

## Migrations

- `2019_03_07_155813_make_repair_statuses_table.php`
- `2019_03_08_120634_add_repair_columns_to_transactions_table.php`
- `2019_03_14_182704_add_repair_permissions.php`
- `2019_03_29_110241_add_repair_version_column_to_system_table.php`
- `2019_04_12_113901_add_repair_settings_column_to_business_table.php`
- `2020_05_05_125008_create_device_models_table.php`
- `2020_05_06_103135_add_repair_model_id_column_to_products_table.php`
- `2020_07_11_120308_add_columns_to_repair_statuses_table.php`
- `2020_07_31_130737_create_job_sheets_table.php`
- `2020_08_07_124241_add_job_sheet_id_to_transactions_table.php`
- `2020_08_22_104640_add_email_template_field_to_repair_status_table.php`
- `2020_10_19_131934_add_job_sheet_custom_fields_to_repair_job_sheets_table.php`
- `2020_11_25_111050_add_parts_column_to_repair_job_sheets_table.php`
- `2020_12_30_101842_add_use_for_repair_column_to_brands_table.php`
- `2021_02_16_190423_add_repair_module_indexing.php`
- `2022_12_23_162847_add_repair_jobsheet_settings_column_to_business_table.php`
- `2026_05_06_180000_add_repair_listing_indexes.php`
- `2026_05_12_050001_add_current_stage_id_to_job_sheets.php`

## Views (Blade)

**Total:** 52 arquivos

**Pastas principais:**

- `job_sheet/` — 14 arquivo(s)
- `repair/` — 13 arquivo(s)
- `layouts/` — 8 arquivo(s)
- `device_model/` — 6 arquivo(s)
- `customer_repair/` — 3 arquivo(s)
- `settings/` — 3 arquivo(s)
- `status/` — 3 arquivo(s)
- `dashboard/` — 1 arquivo(s)
- `D:/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles
- **`get_pos_screen_view()`** — Adiciona view extra na tela POS
- **`after_sale_saved()`** — Callback após venda ser salva
- **`after_product_saved()`** — Callback após produto ser salvo
- **`addTaxonomies()`** — Registra taxonomias/categorias customizadas

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `repair.create`
- `repair.update`
- `repair.view`
- `repair.view_own`
- `repair.delete`
- `repair_status.update`
- `repair_status.access`
- `job_sheet.create`
- `job_sheet.edit`
- `job_sheet.delete`
- `job_sheet.view_assigned`
- `job_sheet.view_all`

**Usadas nas views** (`@can`/`@cannot`):

- `customer.create`
- `job_sheet.create`
- `job_sheet.edit`
- `repair.create`
- `job_sheet.view_assigned`
- `job_sheet.view_all`
- `repair.view`
- `repair.view_own`
- `brand.view`
- `brand.create`
- `edit_repair_settings`
- `repair_status.update`

## Processamento / eventos

**Events:** `RepairStatusChanged`

**Observers:** `JobSheetObserver`

## Peças adicionais

- **Notifications:** `RepairStatusUpdated`
- **Seeders:** `RepairDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Repair` |
| `module_version` | `2.0` |
| `pid` | `6` |
| `enable_repair_check_using_mobile_num` | `true` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `NfeBrasil` | 1 |

## Integridade do banco

**Foreign Keys** (13):

- `business_id` → `business.id`
- `brand_id` → `brands.id`
- `device_id` → `categories.id`
- `created_by` → `users.id`
- `repair_model_id` → `repair_device_models.id`
- `business_id` → `business.id`
- `contact_id` → `contacts.id`
- `brand_id` → `brands.id`
- `device_id` → `categories.id`
- `device_model_id` → `repair_device_models.id`
- `service_staff` → `users.id`
- `created_by` → `users.id`
- `repair_job_sheet_id` → `repair_job_sheets.id`

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
| atual (main) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ❌ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |

## Diferenças vs versões anteriores

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-05-29 08:06.**
**Reaxecutar com:** `php artisan module:spec Repair`
