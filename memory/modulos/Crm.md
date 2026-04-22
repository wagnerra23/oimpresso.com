# Módulo: Crm

> **Crm Module**

- **Alias:** `crm`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Crm`
- **Status:** 🟢 ativo
- **Providers:** Modules\Crm\Providers\CrmServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, user_permissions, addTaxonomies
- 🔴 +50 rotas — módulo grande, migrar em fases
- 🔴 +50 views — trabalho pesado
- 🔐 Registra 13 permissão(ões) Spatie

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 52 |
| Controllers | 21 |
| Entities (Models) | 11 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 2 |
| Views Blade | 68 |
| Migrations | 26 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `contact-profile` | `[Modules\Crm\Http\Controllers\ManageProfileController::class, 'getProfile']` |
| `POST` | `contact-password-update` | `[Modules\Crm\Http\Controllers\ManageProfileController::class, 'updatePassword']` |
| `POST` | `contact-profile-update` | `[Modules\Crm\Http\Controllers\ManageProfileController::class, 'updateProfile']` |
| `GET` | `contact-purchases` | `[Modules\Crm\Http\Controllers\PurchaseController::class, 'getPurchaseList']` |
| `GET` | `contact-sells` | `[Modules\Crm\Http\Controllers\SellController::class, 'getSellList']` |
| `GET` | `contact-ledger` | `[Modules\Crm\Http\Controllers\LedgerController::class, 'index']` |
| `GET` | `contact-get-ledger` | `[Modules\Crm\Http\Controllers\LedgerController::class, 'getLedger']` |
| `GET` | `products/list` | `[\App\Http\Controllers\ProductController::class, 'getProducts']` |
| `GET` | `order-request/get_product_row/{variation_id}/{location_id}` | `[Modules\Crm\Http\Controllers\OrderRequestController::class, 'getProductRow']` |
| `GET` | `commissions` | `[Modules\Crm\Http\Controllers\ContactLoginController::class, 'commissions']` |
| `GET` | `all-contacts-login` | `[Modules\Crm\Http\Controllers\ContactLoginController::class, 'allContactsLoginList']` |
| `GET` | `todays-follow-ups` | `[Modules\Crm\Http\Controllers\ScheduleController::class, 'getTodaysSchedule']` |
| `GET` | `lead-follow-ups` | `[Modules\Crm\Http\Controllers\ScheduleController::class, 'getLeadSchedule']` |
| `GET` | `get-invoices` | `[Modules\Crm\Http\Controllers\ScheduleController::class, 'getInvoicesForFollowUp']` |
| `GET` | `get-followup-groups` | `[Modules\Crm\Http\Controllers\ScheduleController::class, 'getFollowUpGroups']` |
| `GET` | `all-users-call-logs` | `[Modules\Crm\Http\Controllers\CallLogController::class, 'allUsersCallLog']` |
| `GET` | `install` | `[Modules\Crm\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `install` | `[Modules\Crm\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `install/uninstall` | `[Modules\Crm\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `install/update` | `[Modules\Crm\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `lead/{id}/convert` | `[Modules\Crm\Http\Controllers\LeadController::class, 'convertToCustomer']` |
| `GET` | `lead/{id}/post-life-stage` | `[Modules\Crm\Http\Controllers\LeadController::class, 'postLifeStage']` |
| `GET` | `{id}/send-campaign-notification` | `[Modules\Crm\Http\Controllers\CampaignController::class, 'sendNotification']` |
| `GET` | `dashboard` | `[Modules\Crm\Http\Controllers\CrmDashboardController::class, 'index']` |
| `GET` | `reports` | `[Modules\Crm\Http\Controllers\ReportController::class, 'index']` |
| `GET` | `follow-ups-by-user` | `[Modules\Crm\Http\Controllers\ReportController::class, 'followUpsByUser']` |
| `GET` | `follow-ups-by-contact` | `[Modules\Crm\Http\Controllers\ReportController::class, 'followUpsContact']` |
| `GET` | `lead-to-customer-report` | `[Modules\Crm\Http\Controllers\ReportController::class, 'leadToCustomerConversion']` |
| `GET` | `lead-to-customer-details/{user_id}` | `[Modules\Crm\Http\Controllers\ReportController::class, 'showLeadToCustomerConversionDetails']` |
| `GET` | `call-log` | `[Modules\Crm\Http\Controllers\CallLogController::class, 'index'], ['only' => ['index']]` |
| `POST` | `mass-delete-call-log` | `[Modules\Crm\Http\Controllers\CallLogController::class, 'massDestroy']` |
| `GET` | `edit-proposal-template` | `[Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getEdit']` |
| `POST` | `update-proposal-template` | `[Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'postEdit']` |
| `GET` | `view-proposal-template` | `[Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'getView']` |
| `GET` | `send-proposal` | `[Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'send']` |
| `DELETE` | `delete-proposal-media/{id}` | `[Modules\Crm\Http\Controllers\ProposalTemplateController::class, 'deleteProposalMedia']` |
| `GET` | `settings` | `[Modules\Crm\Http\Controllers\CrmSettingsController::class, 'index']` |
| `POST` | `update-settings` | `[Modules\Crm\Http\Controllers\CrmSettingsController::class, 'updateSettings']` |
| `GET` | `order-request` | `[Modules\Crm\Http\Controllers\OrderRequestController::class, 'listOrderRequests']` |
| `GET` | `b2b-marketplace` | `[Modules\Crm\Http\Controllers\CrmMarketplaceController::class, 'index']` |

_... +12 rotas_

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`CallLogController`** — 3 ação(ões): index, massDestroy, allUsersCallLog
- **`CampaignController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, sendNotification +1
- **`ContactBookingController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`ContactLoginController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, allContactsLoginList +1
- **`CrmDashboardController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`CrmMarketplaceController`** — 3 ação(ões): index, save, importLeads
- **`CrmSettingsController`** — 2 ação(ões): index, updateSettings
- **`DashboardController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`DataController`** — 12 ação(ões): after_payment_status_updated, deleteCommissionWithSale, parse_notification, modifyAdminMenu, get_contact_view_tabs, addTaxonomies, user_permissions, calendarEvents +4
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`LeadController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, convertToCustomer +1
- **`LedgerController`** — 2 ação(ões): index, getLedger
- **`ManageProfileController`** — 3 ação(ões): getProfile, updateProfile, updatePassword
- **`OrderRequestController`** — 5 ação(ões): index, create, store, getProductRow, listOrderRequests
- **`ProposalController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`ProposalTemplateController`** — 12 ação(ões): index, create, store, show, edit, update, destroy, getEdit +4
- **`PurchaseController`** — 1 ação(ões): getPurchaseList
- **`ReportController`** — 5 ação(ões): index, followUpsByUser, followUpsContact, leadToCustomerConversion, showLeadToCustomerConversionDetails
- **`ScheduleController`** — 12 ação(ões): index, create, store, show, edit, update, destroy, getTodaysSchedule +4
- **`ScheduleLogController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`SellController`** — 1 ação(ões): getSellList

## Entities (Models Eloquent)

- **`Campaign`** (tabela: `crm_campaigns`)
- **`CrmCallLog`** (tabela: `—`)
- **`CrmContact`** (tabela: `contacts`)
- **`CrmContactPersonCommission`** (tabela: `—`)
- **`CrmMarketplace`** (tabela: `—`)
- **`Leaduser`** (tabela: `crm_lead_users`)
- **`Proposal`** (tabela: `crm_proposals`)
- **`ProposalTemplate`** (tabela: `crm_proposal_templates`)
- **`Schedule`** (tabela: `crm_schedules`)
- **`ScheduleLog`** (tabela: `crm_schedule_logs`)
- **`ScheduleUser`** (tabela: `crm_schedule_users`)

## Migrations

- `2020_03_19_130231_add_contact_id_to_users_table.php`
- `2020_03_27_133605_create_schedules_table.php`
- `2020_03_27_133628_create_schedule_users_table.php`
- `2020_03_30_112834_create_schedule_logs_table.php`
- `2020_04_02_182331_add_crm_module_version_to_system_table.php`
- `2020_04_08_153231_modify_cloumn_in_contacts_table.php`
- `2020_04_09_101052_create_lead_users_table.php`
- `2020_04_16_114747_create_crm_campaigns_table.php`
- `2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table.php`
- `2021_02_02_140021_add_additional_info_to_crm_campaigns_table.php`
- `2021_02_02_173651_add_new_columns_to_contacts_table.php`
- `2021_02_04_120439_create_call_logs_table.php`
- `2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table.php`
- `2021_02_16_190038_add_crm_module_indexing.php`
- `2021_02_19_120846_create_crm_followup_invoices.php`
- `2021_02_22_132125_add_follow_up_by_to_crm_schedules_table.php`
- `2021_03_24_160736_add_department_and_designation_to_users_table.php`
- `2021_06_15_152924_create_proposal_templates_table.php`
- `2021_06_16_114448_add_recursive_fields_to_crm_schedules_table.php`
- `2021_06_16_125740_create_proposals_table.php`
- `2021_09_24_065738_add_crm_settings_column_to_business_table.php`
- `2022_02_09_055012_create_crm_marketplaces_table.php`
- `2022_02_17_113045_add_source_id_to_marketplace.php`
- `2022_03_02_180929_add_followup_category_id.php`
- `2022_05_26_061553_create_crm_contact_person_commissions_table.php`
- `2022_06_06_073006_add_cc_and_bcc_columns_to_crm_proposals_table.php`

## Views (Blade)

**Total:** 68 arquivos

**Pastas principais:**

- `schedule/` — 12 arquivo(s)
- `contact_login/` — 10 arquivo(s)
- `proposal_template/` — 7 arquivo(s)
- `layouts/` — 5 arquivo(s)
- `schedule_log/` — 5 arquivo(s)
- `campaign/` — 4 arquivo(s)
- `lead/` — 4 arquivo(s)
- `order_request/` — 4 arquivo(s)
- `reports/` — 3 arquivo(s)
- `booking/` — 2 arquivo(s)
- `proposal/` — 2 arquivo(s)
- `call_logs/` — 1 arquivo(s)
- `crm_dashboard/` — 1 arquivo(s)
- `dashboard/` — 1 arquivo(s)
- `D:/` — 1 arquivo(s)
- `ledger/` — 1 arquivo(s)
- `marketplace/` — 1 arquivo(s)
- `profile/` — 1 arquivo(s)
- `purchase/` — 1 arquivo(s)
- `sell/` — 1 arquivo(s)
- `settings/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — Registra taxonomias/categorias customizadas

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `crm.access_all_schedule`
- `crm.access_own_schedule`
- `crm.access_all_leads`
- `crm.access_own_leads`
- `crm.access_all_campaigns`
- `crm.access_own_campaigns`
- `crm.access_contact_login`
- `crm.access_sources`
- `crm.access_life_stage`
- `crm.access_proposal`
- `constants.enable_crm_call_log`
- `crm.view_all_call_log`
- `crm.view_own_call_log`

**Usadas nas views** (`@can`/`@cannot`):

- `crm.view_all_call_log`
- `crm.access_all_schedule`
- `crm.access_own_schedule`
- `crm.access_all_leads`
- `crm.access_own_leads`
- `crm.access_contact_login`
- `crm.view_reports`
- `crm.access_sources`
- `crm.access_life_stage`
- `crm.access_all_campaigns`
- `crm.access_own_campaigns`
- `crm.view_own_call_log`
- `crm.access_b2b_marketplace`
- `so.view_own`
- `so.view_all`
- `crm.access_proposal`
- `crm.add_proposal_template`

## Processamento / eventos

**Commands (artisan):** `CreateRecursiveFollowup`, `SendScheduleNotification`

## Peças adicionais

- **Notifications:** `ScheduleNotification`, `SendCampaignNotification`, `SendProposalNotification`
- **Seeders:** `CrmDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Crm` |
| `module_version` | `2.1` |
| `pid` | `7` |

## Integridade do banco

**Foreign Keys** (10):

- `crm_contact_id` → `contacts.id`
- `business_id` → `business.id`
- `contact_id` → `contacts.id`
- `schedule_id` → `crm_schedules.id`
- `schedule_id` → `crm_schedules.id`
- `contact_id` → `contacts.id`
- `business_id` → `business.id`
- `business_id` → `business.id`
- `business_id` → `business.id`
- `contact_id` → `contacts.id`

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

**Frameworks/libs detectados no JS:** jQuery, Bootstrap, DataTables, Select2, SweetAlert, Toastr, TinyMCE

**Arquivos JS** (primeiros 1):

- `js\crm.js` (39.5 KB)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\crm.css` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 176
- **Linhas +:** 19919 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/CreateRecursiveFollowup.php`
  - `Console/SendScheduleNotification.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_03_19_130231_add_contact_id_to_users_table.php`
  - `Database/Migrations/2020_03_27_133605_create_schedules_table.php`
  - `Database/Migrations/2020_03_27_133628_create_schedule_users_table.php`
  - `Database/Migrations/2020_03_30_112834_create_schedule_logs_table.php`
  - `Database/Migrations/2020_04_02_182331_add_crm_module_version_to_system_table.php`
  - `Database/Migrations/2020_04_08_153231_modify_cloumn_in_contacts_table.php`
  - `Database/Migrations/2020_04_09_101052_create_lead_users_table.php`
  - `Database/Migrations/2020_04_16_114747_create_crm_campaigns_table.php`
  - `Database/Migrations/2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table.php`
  - `Database/Migrations/2021_02_02_140021_add_additional_info_to_crm_campaigns_table.php`
  - `Database/Migrations/2021_02_02_173651_add_new_columns_to_contacts_table.php`
  - `Database/Migrations/2021_02_04_120439_create_call_logs_table.php`
  - `Database/Migrations/2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table.php`
  - `Database/Migrations/2021_02_16_190038_add_crm_module_indexing.php`
  - `Database/Migrations/2021_02_19_120846_create_crm_followup_invoices.php`
  - `Database/Migrations/2021_02_22_132125_add_follow_up_by_to_crm_schedules_table.php`
  - `Database/Migrations/2021_03_24_160736_add_department_and_designation_to_users_table.php`
  - `Database/Migrations/2021_06_15_152924_create_proposal_templates_table.php`
  - `Database/Migrations/2021_06_16_114448_add_recursive_fields_to_crm_schedules_table.php`
  - `Database/Migrations/2021_06_16_125740_create_proposals_table.php`
  - `Database/Migrations/2021_09_24_065738_add_crm_settings_column_to_business_table.php`
  - `Database/Migrations/2022_02_09_055012_create_crm_marketplaces_table.php`
  - `Database/Migrations/2022_02_17_113045_add_source_id_to_marketplace.php`
  - `Database/Migrations/2022_03_02_180929_add_followup_category_id.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 176
- **Linhas +:** 19919 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/CreateRecursiveFollowup.php`
  - `Console/SendScheduleNotification.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2020_03_19_130231_add_contact_id_to_users_table.php`
  - `Database/Migrations/2020_03_27_133605_create_schedules_table.php`
  - `Database/Migrations/2020_03_27_133628_create_schedule_users_table.php`
  - `Database/Migrations/2020_03_30_112834_create_schedule_logs_table.php`
  - `Database/Migrations/2020_04_02_182331_add_crm_module_version_to_system_table.php`
  - `Database/Migrations/2020_04_08_153231_modify_cloumn_in_contacts_table.php`
  - `Database/Migrations/2020_04_09_101052_create_lead_users_table.php`
  - `Database/Migrations/2020_04_16_114747_create_crm_campaigns_table.php`
  - `Database/Migrations/2021_01_07_155757_add_followup_additional_info_column_to_crm_schedules_table.php`
  - `Database/Migrations/2021_02_02_140021_add_additional_info_to_crm_campaigns_table.php`
  - `Database/Migrations/2021_02_02_173651_add_new_columns_to_contacts_table.php`
  - `Database/Migrations/2021_02_04_120439_create_call_logs_table.php`
  - `Database/Migrations/2021_02_08_172047_add_mobile_name_column_to_crm_call_logs_table.php`
  - `Database/Migrations/2021_02_16_190038_add_crm_module_indexing.php`
  - `Database/Migrations/2021_02_19_120846_create_crm_followup_invoices.php`
  - `Database/Migrations/2021_02_22_132125_add_follow_up_by_to_crm_schedules_table.php`
  - `Database/Migrations/2021_03_24_160736_add_department_and_designation_to_users_table.php`
  - `Database/Migrations/2021_06_15_152924_create_proposal_templates_table.php`
  - `Database/Migrations/2021_06_16_114448_add_recursive_fields_to_crm_schedules_table.php`
  - `Database/Migrations/2021_06_16_125740_create_proposals_table.php`
  - `Database/Migrations/2021_09_24_065738_add_crm_settings_column_to_business_table.php`
  - `Database/Migrations/2022_02_09_055012_create_crm_marketplaces_table.php`
  - `Database/Migrations/2022_02_17_113045_add_source_id_to_marketplace.php`
  - `Database/Migrations/2022_03_02_180929_add_followup_category_id.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Crm`
