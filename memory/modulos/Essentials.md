# Módulo: Essentials

> **Some essentials functionality for every businesses & Human resource management (HRM) feature.**

- **Alias:** `essentials`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Essentials`
- **Status:** 🟢 ativo
- **Providers:** Modules\Essentials\Providers\EssentialsServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 5 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions, addTaxonomies, moduleViewPartials
- 🔴 +50 rotas — módulo grande, migrar em fases
- 🔴 +50 views — trabalho pesado
- ✅ Tem testes (2)
- 🔐 Registra 23 permissão(ões) Spatie

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 53 |
| Controllers | 19 |
| Entities (Models) | 18 |
| Services | 0 |
| FormRequests | 4 |
| Middleware | 0 |
| Views Blade | 87 |
| Migrations | 36 |
| Arquivos de lang | 16 |
| Testes | 2 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/dashboard` | `[Modules\Essentials\Http\Controllers\DashboardController::class, 'essentialsDashboard']` |
| `GET` | `/install` | `[Modules\Essentials\Http\Controllers\InstallController::class, 'index']` |
| `GET` | `/install/update` | `[Modules\Essentials\Http\Controllers\InstallController::class, 'update']` |
| `GET` | `/install/uninstall` | `[Modules\Essentials\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/` | `[Modules\Essentials\Http\Controllers\EssentialsController::class, 'index']` |
| `GET` | `document/download/{id}` | `[Modules\Essentials\Http\Controllers\DocumentController::class, 'download']` |
| `POST` | `todo/add-comment` | `[Modules\Essentials\Http\Controllers\ToDoController::class, 'addComment']` |
| `GET` | `todo/delete-comment/{id}` | `[Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteComment']` |
| `GET` | `todo/delete-document/{id}` | `[Modules\Essentials\Http\Controllers\ToDoController::class, 'deleteDocument']` |
| `POST` | `todo/upload-document` | `[Modules\Essentials\Http\Controllers\ToDoController::class, 'uploadDocument']` |
| `GET` | `view-todo-{id}-share-docs` | `[Modules\Essentials\Http\Controllers\ToDoController::class, 'viewSharedDocs']` |
| `GET` | `get-new-messages` | `[Modules\Essentials\Http\Controllers\EssentialsMessageController::class, 'getNewMessages']` |
| `GET` | `user-sales-targets` | `[Modules\Essentials\Http\Controllers\DashboardController::class, 'getUserSalesTargets']` |
| `GET` | `/dashboard` | `[Modules\Essentials\Http\Controllers\DashboardController::class, 'hrmDashboard']` |
| `POST` | `/change-status` | `[Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'changeStatus']` |
| `GET` | `/leave/activity/{id}` | `[Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'activity']` |
| `GET` | `/user-leave-summary` | `[Modules\Essentials\Http\Controllers\EssentialsLeaveController::class, 'getUserLeaveSummary']` |
| `GET` | `/settings` | `[Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'edit']` |
| `POST` | `/settings` | `[Modules\Essentials\Http\Controllers\EssentialsSettingsController::class, 'update']` |
| `POST` | `/import-attendance` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'importAttendance']` |
| `POST` | `/clock-in-clock-out` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'clockInClockOut']` |
| `POST` | `/validate-clock-in-clock-out` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'validateClockInClockOut']` |
| `GET` | `/get-attendance-by-shift` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceByShift']` |
| `GET` | `/get-attendance-by-date` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceByDate']` |
| `GET` | `/get-attendance-row/{user_id}` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'getAttendanceRow']` |
| `GET` | `/user-attendance-summary` | `[Modules\Essentials\Http\Controllers\AttendanceController::class, 'getUserAttendanceSummary']` |
| `GET` | `/location-employees` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'getEmployeesBasedOnLocation']` |
| `GET` | `/my-payrolls` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'getMyPayrolls']` |
| `GET` | `/get-allowance-deduction-row` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'getAllowanceAndDeductionRow']` |
| `GET` | `/payroll-group-datatable` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'payrollGroupDatatable']` |
| `GET` | `/view/{id}/payroll-group` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'viewPayrollGroup']` |
| `GET` | `/edit/{id}/payroll-group` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'getEditPayrollGroup']` |
| `POST` | `/update-payroll-group` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'getUpdatePayrollGroup']` |
| `GET` | `/payroll-group/{id}/add-payment` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'addPayment']` |
| `POST` | `/post-payment-payroll-group` | `[Modules\Essentials\Http\Controllers\PayrollController::class, 'postAddPayment']` |
| `GET` | `/shift/assign-users/{shift_id}` | `[Modules\Essentials\Http\Controllers\ShiftController::class, 'getAssignUsers']` |
| `POST` | `/shift/assign-users` | `[Modules\Essentials\Http\Controllers\ShiftController::class, 'postAssignUsers']` |
| `GET` | `/sales-target` | `[Modules\Essentials\Http\Controllers\SalesTargetController::class, 'index']` |
| `GET` | `/set-sales-target/{id}` | `[Modules\Essentials\Http\Controllers\SalesTargetController::class, 'setSalesTarget']` |
| `POST` | `/save-sales-target` | `[Modules\Essentials\Http\Controllers\SalesTargetController::class, 'saveSalesTarget']` |

_... +13 rotas_

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`AttendanceController`** — 13 ação(ões): index, create, store, edit, update, destroy, clockInClockOut, getUserAttendanceSummary +5
- **`DashboardController`** — 9 ação(ões): hrmDashboard, getUserSalesTargets, essentialsDashboard, create, store, show, edit, update +1
- **`DataController`** — 14 ação(ões): parse_notification, user_permissions, superadmin_package, modifyAdminMenu, addTaxonomies, moduleViewPartials, afterModelSaved, profitLossReportData +6
- **`DocumentController`** — 7 ação(ões): index, store, show, edit, update, destroy, download
- **`DocumentShareController`** — 2 ação(ões): edit, update
- **`EssentialsAllowanceAndDeductionController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`EssentialsController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`EssentialsHolidayController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`EssentialsLeaveController`** — 10 ação(ões): index, create, store, show, edit, update, destroy, changeStatus +2
- **`EssentialsLeaveTypeController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`EssentialsMessageController`** — 4 ação(ões): index, store, destroy, getNewMessages
- **`EssentialsSettingsController`** — 2 ação(ões): edit, update
- **`InstallController`** — 3 ação(ões): index, update, uninstall
- **`KnowledgeBaseController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`PayrollController`** — 16 ação(ões): index, create, store, show, edit, update, destroy, getAllowanceAndDeductionRow +8
- **`ReminderController`** — 5 ação(ões): index, store, show, update, destroy
- **`SalesTargetController`** — 3 ação(ões): index, setSalesTarget, saveSalesTarget
- **`ShiftController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, getAssignUsers +1
- **`ToDoController`** — 12 ação(ões): index, create, store, show, edit, update, destroy, addComment +4

## Entities (Models Eloquent)

- **`Document`** (tabela: `essentials_documents`)
- **`DocumentShare`** (tabela: `essentials_document_shares`)
- **`EssentialsAllowanceAndDeduction`** (tabela: `essentials_allowances_and_deductions`)
- **`EssentialsAttendance`** (tabela: `—`)
- **`EssentialsHoliday`** (tabela: `—`)
- **`EssentialsLeave`** (tabela: `—`)
- **`EssentialsLeaveType`** (tabela: `—`)
- **`EssentialsMessage`** (tabela: `—`)
- **`EssentialsTodoComment`** (tabela: `—`)
- **`EssentialsUserAllowancesAndDeduction`** (tabela: `essentials_user_allowance_and_deductions`)
- **`EssentialsUserSalesTarget`** (tabela: `essentials_user_sales_targets`)
- **`EssentialsUserShift`** (tabela: `—`)
- **`KnowledgeBase`** (tabela: `essentials_kb`)
- **`PayrollGroup`** (tabela: `essentials_payroll_groups`)
- **`PayrollGroupTransaction`** (tabela: `essentials_payroll_group_transactions`)
- **`Reminder`** (tabela: `essentials_reminders`)
- **`Shift`** (tabela: `essentials_shifts`)
- **`ToDo`** (tabela: `essentials_to_dos`)

## Migrations

- `2018_10_01_151252_create_documents_table.php`
- `2018_10_02_151803_create_document_shares_table.php`
- `2018_10_09_134558_create_reminders_table.php`
- `2018_11_16_170756_create_to_dos_table.php`
- `2019_02_22_120329_essentials_messages.php`
- `2019_02_22_161513_add_message_permissions.php`
- `2019_03_29_164339_add_essentials_version_to_system_table.php`
- `2019_05_17_153306_create_essentials_leave_types_table.php`
- `2019_05_17_175921_create_essentials_leaves_table.php`
- `2019_05_21_154517_add_essentials_settings_columns_to_business_table.php`
- `2019_05_21_181653_create_table_essentials_attendance.php`
- `2019_05_30_110049_create_essentials_payrolls_table.php`
- `2019_06_04_105723_create_essentials_holidays_table.php`
- `2019_06_28_134217_add_payroll_columns_to_transactions_table.php`
- `2019_08_26_103520_add_approve_leave_permission.php`
- `2019_08_27_103724_create_essentials_allowance_and_deduction_table.php`
- `2019_08_27_105236_create_essentials_user_allowances_and_deductions.php`
- `2019_09_20_115906_add_more_columns_to_essentials_to_dos_table.php`
- `2019_09_23_120439_create_essentials_todo_comments_table.php`
- `2019_12_05_170724_add_hrm_columns_to_users_table.php`
- `2019_12_09_105809_add_allowance_and_deductions_permission.php`
- `2020_03_28_152838_create_essentials_shift_table.php`
- `2020_03_30_162029_create_user_shifts_table.php`
- `2020_03_31_134558_add_shift_id_to_attendance_table.php`
- `2020_11_05_105157_modify_todos_date_column_type.php`
- `2020_11_11_174852_add_end_time_column_to_essentials_reminders_table.php`
- `2020_11_26_170527_create_essentials_kb_table.php`
- `2020_11_30_112615_create_essentials_kb_users_table.php`
- `2021_02_12_185514_add_clock_in_location_to_essentials_attendances_table.php`
- `2021_02_16_190203_add_essentials_module_indexing.php`
- `2021_02_27_133448_add_columns_to_users_table.php`
- `2021_03_04_174857_create_payroll_groups_table.php`
- `2021_03_04_175025_create_payroll_group_transactions_table.php`
- `2021_03_09_123914_add_auto_clockout_to_essentials_shifts.php`
- `2021_06_17_121451_add_location_id_to_table.php`
- `2021_09_28_091541_create_essentials_user_sales_targets_table.php`

## Views (Blade)

**Total:** 87 arquivos

**Pastas principais:**

- `attendance/` — 14 arquivo(s)
- `payroll/` — 14 arquivo(s)
- `todo/` — 9 arquivo(s)
- `layouts/` — 7 arquivo(s)
- `settings/` — 7 arquivo(s)
- `knowledge_base/` — 5 arquivo(s)
- `leave/` — 5 arquivo(s)
- `dashboard/` — 3 arquivo(s)
- `holiday/` — 3 arquivo(s)
- `leave_type/` — 3 arquivo(s)
- `messages/` — 3 arquivo(s)
- `reminder/` — 3 arquivo(s)
- `allowance_deduction/` — 2 arquivo(s)
- `document/` — 2 arquivo(s)
- `partials/` — 2 arquivo(s)
- `sales_targets/` — 2 arquivo(s)
- `document_share/` — 1 arquivo(s)
- `D:/` — 1 arquivo(s)
- `memos/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — Registra taxonomias/categorias customizadas
- **`moduleViewPartials()`** — Partials injetáveis em views do core

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `essentials.crud_leave_type`
- `essentials.crud_all_leave`
- `essentials.crud_own_leave`
- `essentials.approve_leave`
- `essentials.crud_all_attendance`
- `essentials.view_own_attendance`
- `essentials.allow_users_for_attendance_from_web`
- `essentials.allow_users_for_attendance_from_api`
- `essentials.view_allowance_and_deduction`
- `essentials.add_allowance_and_deduction`
- `essentials.crud_department`
- `essentials.crud_designation`
- `essentials.view_all_payroll`
- `essentials.create_payroll`
- `essentials.update_payroll`
- `essentials.delete_payroll`
- `essentials.assign_todos`
- `essentials.add_todos`
- `essentials.edit_todos`
- `essentials.delete_todos`
- `essentials.create_message`
- `essentials.view_message`
- `essentials.access_sales_target`

**Usadas nas views** (`@can`/`@cannot`):

- `essentials.crud_all_attendance`
- `user.view`
- `essentials.approve_leave`
- `essentials.view_message`
- `essentials.create_message`
- `edit_essentials_settings`
- `essentials.crud_leave_type`
- `essentials.crud_department`
- `essentials.crud_designation`
- `essentials.crud_all_leave`
- `essentials.crud_own_leave`
- `essentials.view_own_attendance`
- `essentials.access_sales_target`
- `add_essentials_leave_type`
- `essentials.add_allowance_and_deduction`
- `essentials.view_all_payroll`
- `essentials.create_payroll`
- `essentials.delete_payroll`
- `essentials.view_allowance_and_deduction`
- `essentials.assign_todos`
- _... +1 permissões_

## Processamento / eventos

**Commands (artisan):** `AutoClockOutUser`

## Peças adicionais

- **Notifications:** `DocumentShareNotification`, `LeaveStatusNotification`, `NewLeaveNotification`, `NewMessageNotification`, `NewTaskCommentNotification`, `NewTaskDocumentNotification`, `NewTaskNotification`, `PayrollNotification`
- **Seeders:** `EssentialsDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Essentials` |
| `module_version` | `4.0` |
| `chat_refresh_interval` | `20` |

## Integridade do banco

**Foreign Keys** (2):

- `parent_id` → `essentials_kb.id`
- `payroll_group_id` → `essentials_payroll_groups.id`

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

- **Arquivos alterados:** 213
- **Linhas +:** 22752 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/AutoClockOutUser.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_10_01_151252_create_documents_table.php`
  - `Database/Migrations/2018_10_02_151803_create_document_shares_table.php`
  - `Database/Migrations/2018_10_09_134558_create_reminders_table.php`
  - `Database/Migrations/2018_11_16_170756_create_to_dos_table.php`
  - `Database/Migrations/2019_02_22_120329_essentials_messages.php`
  - `Database/Migrations/2019_02_22_161513_add_message_permissions.php`
  - `Database/Migrations/2019_03_29_164339_add_essentials_version_to_system_table.php`
  - `Database/Migrations/2019_05_17_153306_create_essentials_leave_types_table.php`
  - `Database/Migrations/2019_05_17_175921_create_essentials_leaves_table.php`
  - `Database/Migrations/2019_05_21_154517_add_essentials_settings_columns_to_business_table.php`
  - `Database/Migrations/2019_05_21_181653_create_table_essentials_attendance.php`
  - `Database/Migrations/2019_05_30_110049_create_essentials_payrolls_table.php`
  - `Database/Migrations/2019_06_04_105723_create_essentials_holidays_table.php`
  - `Database/Migrations/2019_06_28_134217_add_payroll_columns_to_transactions_table.php`
  - `Database/Migrations/2019_08_26_103520_add_approve_leave_permission.php`
  - `Database/Migrations/2019_08_27_103724_create_essentials_allowance_and_deduction_table.php`
  - `Database/Migrations/2019_08_27_105236_create_essentials_user_allowances_and_deductions.php`
  - `Database/Migrations/2019_09_20_115906_add_more_columns_to_essentials_to_dos_table.php`
  - `Database/Migrations/2019_09_23_120439_create_essentials_todo_comments_table.php`
  - `Database/Migrations/2019_12_05_170724_add_hrm_columns_to_users_table.php`
  - `Database/Migrations/2019_12_09_105809_add_allowance_and_deductions_permission.php`
  - `Database/Migrations/2020_03_28_152838_create_essentials_shift_table.php`
  - `Database/Migrations/2020_03_30_162029_create_user_shifts_table.php`
  - `Database/Migrations/2020_03_31_134558_add_shift_id_to_attendance_table.php`
  - `Database/Migrations/2020_11_05_105157_modify_todos_date_column_type.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 213
- **Linhas +:** 22752 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Console/AutoClockOutUser.php`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2018_10_01_151252_create_documents_table.php`
  - `Database/Migrations/2018_10_02_151803_create_document_shares_table.php`
  - `Database/Migrations/2018_10_09_134558_create_reminders_table.php`
  - `Database/Migrations/2018_11_16_170756_create_to_dos_table.php`
  - `Database/Migrations/2019_02_22_120329_essentials_messages.php`
  - `Database/Migrations/2019_02_22_161513_add_message_permissions.php`
  - `Database/Migrations/2019_03_29_164339_add_essentials_version_to_system_table.php`
  - `Database/Migrations/2019_05_17_153306_create_essentials_leave_types_table.php`
  - `Database/Migrations/2019_05_17_175921_create_essentials_leaves_table.php`
  - `Database/Migrations/2019_05_21_154517_add_essentials_settings_columns_to_business_table.php`
  - `Database/Migrations/2019_05_21_181653_create_table_essentials_attendance.php`
  - `Database/Migrations/2019_05_30_110049_create_essentials_payrolls_table.php`
  - `Database/Migrations/2019_06_04_105723_create_essentials_holidays_table.php`
  - `Database/Migrations/2019_06_28_134217_add_payroll_columns_to_transactions_table.php`
  - `Database/Migrations/2019_08_26_103520_add_approve_leave_permission.php`
  - `Database/Migrations/2019_08_27_103724_create_essentials_allowance_and_deduction_table.php`
  - `Database/Migrations/2019_08_27_105236_create_essentials_user_allowances_and_deductions.php`
  - `Database/Migrations/2019_09_20_115906_add_more_columns_to_essentials_to_dos_table.php`
  - `Database/Migrations/2019_09_23_120439_create_essentials_todo_comments_table.php`
  - `Database/Migrations/2019_12_05_170724_add_hrm_columns_to_users_table.php`
  - `Database/Migrations/2019_12_09_105809_add_allowance_and_deductions_permission.php`
  - `Database/Migrations/2020_03_28_152838_create_essentials_shift_table.php`
  - `Database/Migrations/2020_03_30_162029_create_user_shifts_table.php`
  - `Database/Migrations/2020_03_31_134558_add_shift_id_to_attendance_table.php`
  - `Database/Migrations/2020_11_05_105157_modify_todos_date_column_type.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 15:50.**
**Reaxecutar com:** `php artisan module:spec Essentials`
