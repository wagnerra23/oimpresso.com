---
name: "SUPERFÍCIE — Essentials"
description: "Índice GERADO dos artefatos do módulo Essentials reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Essentials
---

# 🗺️ Superfície de código — Essentials

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Essentials --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** o inventário completo das raízes `Modules/Essentials/**` + `resources/js/Pages/Essentials/**`, separado por papel — inclusive manifestos, documentação local, telas e componentes. **O que NÃO é:** cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`), nem qual endpoint ainda entrega Blade em vez de Inertia (dono: `blade-migration-census.mjs` — este índice lista o arquivo, não a camada que a rota serve; a fila por módulo sai em `npm run migracao:report`), nem âncoras cross-cutting fora dessas raízes (bridge em `app/`, FSM) — essas são relações estruturadas do [SCOPE](../../../Modules/Essentials/SCOPE.md) e fatos do [BRIEFING](BRIEFING.md).

**Total mapeado:** 275 arquivos em 15 papéis.

## Controllers — 19

- [AttendanceController.php](../../../Modules/Essentials/Http/Controllers/AttendanceController.php)
- [DashboardController.php](../../../Modules/Essentials/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/Essentials/Http/Controllers/DataController.php)
- [DocumentController.php](../../../Modules/Essentials/Http/Controllers/DocumentController.php)
- [DocumentShareController.php](../../../Modules/Essentials/Http/Controllers/DocumentShareController.php)
- [EssentialsAllowanceAndDeductionController.php](../../../Modules/Essentials/Http/Controllers/EssentialsAllowanceAndDeductionController.php)
- [EssentialsController.php](../../../Modules/Essentials/Http/Controllers/EssentialsController.php)
- [EssentialsHolidayController.php](../../../Modules/Essentials/Http/Controllers/EssentialsHolidayController.php)
- [EssentialsLeaveController.php](../../../Modules/Essentials/Http/Controllers/EssentialsLeaveController.php)
- [EssentialsLeaveTypeController.php](../../../Modules/Essentials/Http/Controllers/EssentialsLeaveTypeController.php)
- [EssentialsMessageController.php](../../../Modules/Essentials/Http/Controllers/EssentialsMessageController.php)
- [EssentialsSettingsController.php](../../../Modules/Essentials/Http/Controllers/EssentialsSettingsController.php)
- [InstallController.php](../../../Modules/Essentials/Http/Controllers/InstallController.php)
- [KnowledgeBaseController.php](../../../Modules/Essentials/Http/Controllers/KnowledgeBaseController.php)
- [PayrollController.php](../../../Modules/Essentials/Http/Controllers/PayrollController.php)
- [ReminderController.php](../../../Modules/Essentials/Http/Controllers/ReminderController.php)
- [SalesTargetController.php](../../../Modules/Essentials/Http/Controllers/SalesTargetController.php)
- [ShiftController.php](../../../Modules/Essentials/Http/Controllers/ShiftController.php)
- [ToDoController.php](../../../Modules/Essentials/Http/Controllers/ToDoController.php)

## Requests (validação) — 10

- [StoreDocumentRequest.php](../../../Modules/Essentials/Http/Requests/StoreDocumentRequest.php)
- [StoreHolidayRequest.php](../../../Modules/Essentials/Http/Requests/StoreHolidayRequest.php)
- [StoreKnowledgeBaseRequest.php](../../../Modules/Essentials/Http/Requests/StoreKnowledgeBaseRequest.php)
- [StoreMessageRequest.php](../../../Modules/Essentials/Http/Requests/StoreMessageRequest.php)
- [StoreReminderRequest.php](../../../Modules/Essentials/Http/Requests/StoreReminderRequest.php)
- [ToDoCommentRequest.php](../../../Modules/Essentials/Http/Requests/ToDoCommentRequest.php)
- [ToDoStoreRequest.php](../../../Modules/Essentials/Http/Requests/ToDoStoreRequest.php)
- [ToDoUpdateRequest.php](../../../Modules/Essentials/Http/Requests/ToDoUpdateRequest.php)
- [ToDoUploadDocumentRequest.php](../../../Modules/Essentials/Http/Requests/ToDoUploadDocumentRequest.php)
- [UpdateReminderRequest.php](../../../Modules/Essentials/Http/Requests/UpdateReminderRequest.php)

## Services — 4

- [LeaveAuditService.php](../../../Modules/Essentials/Services/LeaveAuditService.php)
- [LeaveRequestService.php](../../../Modules/Essentials/Services/LeaveRequestService.php)
- [ReminderAuditService.php](../../../Modules/Essentials/Services/ReminderAuditService.php)
- [TodoService.php](../../../Modules/Essentials/Services/TodoService.php)

## Models / Entities — 18

- [Document.php](../../../Modules/Essentials/Entities/Document.php)
- [DocumentShare.php](../../../Modules/Essentials/Entities/DocumentShare.php)
- [EssentialsAllowanceAndDeduction.php](../../../Modules/Essentials/Entities/EssentialsAllowanceAndDeduction.php)
- [EssentialsAttendance.php](../../../Modules/Essentials/Entities/EssentialsAttendance.php)
- [EssentialsHoliday.php](../../../Modules/Essentials/Entities/EssentialsHoliday.php)
- [EssentialsLeave.php](../../../Modules/Essentials/Entities/EssentialsLeave.php)
- [EssentialsLeaveType.php](../../../Modules/Essentials/Entities/EssentialsLeaveType.php)
- [EssentialsMessage.php](../../../Modules/Essentials/Entities/EssentialsMessage.php)
- [EssentialsTodoComment.php](../../../Modules/Essentials/Entities/EssentialsTodoComment.php)
- [EssentialsUserAllowancesAndDeduction.php](../../../Modules/Essentials/Entities/EssentialsUserAllowancesAndDeduction.php)
- [EssentialsUserSalesTarget.php](../../../Modules/Essentials/Entities/EssentialsUserSalesTarget.php)
- [EssentialsUserShift.php](../../../Modules/Essentials/Entities/EssentialsUserShift.php)
- [KnowledgeBase.php](../../../Modules/Essentials/Entities/KnowledgeBase.php)
- [PayrollGroup.php](../../../Modules/Essentials/Entities/PayrollGroup.php)
- [PayrollGroupTransaction.php](../../../Modules/Essentials/Entities/PayrollGroupTransaction.php)
- [Reminder.php](../../../Modules/Essentials/Entities/Reminder.php)
- [Shift.php](../../../Modules/Essentials/Entities/Shift.php)
- [ToDo.php](../../../Modules/Essentials/Entities/ToDo.php)

## Console / Commands — 1

- [AutoClockOutUser.php](../../../Modules/Essentials/Console/AutoClockOutUser.php)

## Providers — 2

- [EssentialsServiceProvider.php](../../../Modules/Essentials/Providers/EssentialsServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Essentials/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Essentials/Routes/api.php)
- [web.php](../../../Modules/Essentials/Routes/web.php)

## Migrations (schema) — 36

- [2018_10_01_151252_create_documents_table.php](../../../Modules/Essentials/Database/Migrations/2018_10_01_151252_create_documents_table.php)
- [2018_10_02_151803_create_document_shares_table.php](../../../Modules/Essentials/Database/Migrations/2018_10_02_151803_create_document_shares_table.php)
- [2018_10_09_134558_create_reminders_table.php](../../../Modules/Essentials/Database/Migrations/2018_10_09_134558_create_reminders_table.php)
- [2018_11_16_170756_create_to_dos_table.php](../../../Modules/Essentials/Database/Migrations/2018_11_16_170756_create_to_dos_table.php)
- [2019_02_22_120329_essentials_messages.php](../../../Modules/Essentials/Database/Migrations/2019_02_22_120329_essentials_messages.php)
- [2019_02_22_161513_add_message_permissions.php](../../../Modules/Essentials/Database/Migrations/2019_02_22_161513_add_message_permissions.php)
- [2019_03_29_164339_add_essentials_version_to_system_table.php](../../../Modules/Essentials/Database/Migrations/2019_03_29_164339_add_essentials_version_to_system_table.php)
- [2019_05_17_153306_create_essentials_leave_types_table.php](../../../Modules/Essentials/Database/Migrations/2019_05_17_153306_create_essentials_leave_types_table.php)
- [2019_05_17_175921_create_essentials_leaves_table.php](../../../Modules/Essentials/Database/Migrations/2019_05_17_175921_create_essentials_leaves_table.php)
- [2019_05_21_154517_add_essentials_settings_columns_to_business_table.php](../../../Modules/Essentials/Database/Migrations/2019_05_21_154517_add_essentials_settings_columns_to_business_table.php)
- [2019_05_21_181653_create_table_essentials_attendance.php](../../../Modules/Essentials/Database/Migrations/2019_05_21_181653_create_table_essentials_attendance.php)
- [2019_05_30_110049_create_essentials_payrolls_table.php](../../../Modules/Essentials/Database/Migrations/2019_05_30_110049_create_essentials_payrolls_table.php)
- [2019_06_04_105723_create_essentials_holidays_table.php](../../../Modules/Essentials/Database/Migrations/2019_06_04_105723_create_essentials_holidays_table.php)
- [2019_06_28_134217_add_payroll_columns_to_transactions_table.php](../../../Modules/Essentials/Database/Migrations/2019_06_28_134217_add_payroll_columns_to_transactions_table.php)
- [2019_08_26_103520_add_approve_leave_permission.php](../../../Modules/Essentials/Database/Migrations/2019_08_26_103520_add_approve_leave_permission.php)
- [2019_08_27_103724_create_essentials_allowance_and_deduction_table.php](../../../Modules/Essentials/Database/Migrations/2019_08_27_103724_create_essentials_allowance_and_deduction_table.php)
- [2019_08_27_105236_create_essentials_user_allowances_and_deductions.php](../../../Modules/Essentials/Database/Migrations/2019_08_27_105236_create_essentials_user_allowances_and_deductions.php)
- [2019_09_20_115906_add_more_columns_to_essentials_to_dos_table.php](../../../Modules/Essentials/Database/Migrations/2019_09_20_115906_add_more_columns_to_essentials_to_dos_table.php)
- [2019_09_23_120439_create_essentials_todo_comments_table.php](../../../Modules/Essentials/Database/Migrations/2019_09_23_120439_create_essentials_todo_comments_table.php)
- [2019_12_05_170724_add_hrm_columns_to_users_table.php](../../../Modules/Essentials/Database/Migrations/2019_12_05_170724_add_hrm_columns_to_users_table.php)
- [2019_12_09_105809_add_allowance_and_deductions_permission.php](../../../Modules/Essentials/Database/Migrations/2019_12_09_105809_add_allowance_and_deductions_permission.php)
- [2020_03_28_152838_create_essentials_shift_table.php](../../../Modules/Essentials/Database/Migrations/2020_03_28_152838_create_essentials_shift_table.php)
- [2020_03_30_162029_create_user_shifts_table.php](../../../Modules/Essentials/Database/Migrations/2020_03_30_162029_create_user_shifts_table.php)
- [2020_03_31_134558_add_shift_id_to_attendance_table.php](../../../Modules/Essentials/Database/Migrations/2020_03_31_134558_add_shift_id_to_attendance_table.php)
- [2020_11_05_105157_modify_todos_date_column_type.php](../../../Modules/Essentials/Database/Migrations/2020_11_05_105157_modify_todos_date_column_type.php)
- [2020_11_11_174852_add_end_time_column_to_essentials_reminders_table.php](../../../Modules/Essentials/Database/Migrations/2020_11_11_174852_add_end_time_column_to_essentials_reminders_table.php)
- [2020_11_26_170527_create_essentials_kb_table.php](../../../Modules/Essentials/Database/Migrations/2020_11_26_170527_create_essentials_kb_table.php)
- [2020_11_30_112615_create_essentials_kb_users_table.php](../../../Modules/Essentials/Database/Migrations/2020_11_30_112615_create_essentials_kb_users_table.php)
- [2021_02_12_185514_add_clock_in_location_to_essentials_attendances_table.php](../../../Modules/Essentials/Database/Migrations/2021_02_12_185514_add_clock_in_location_to_essentials_attendances_table.php)
- [2021_02_16_190203_add_essentials_module_indexing.php](../../../Modules/Essentials/Database/Migrations/2021_02_16_190203_add_essentials_module_indexing.php)
- [2021_02_27_133448_add_columns_to_users_table.php](../../../Modules/Essentials/Database/Migrations/2021_02_27_133448_add_columns_to_users_table.php)
- [2021_03_04_174857_create_payroll_groups_table.php](../../../Modules/Essentials/Database/Migrations/2021_03_04_174857_create_payroll_groups_table.php)
- [2021_03_04_175025_create_payroll_group_transactions_table.php](../../../Modules/Essentials/Database/Migrations/2021_03_04_175025_create_payroll_group_transactions_table.php)
- [2021_03_09_123914_add_auto_clockout_to_essentials_shifts.php](../../../Modules/Essentials/Database/Migrations/2021_03_09_123914_add_auto_clockout_to_essentials_shifts.php)
- [2021_06_17_121451_add_location_id_to_table.php](../../../Modules/Essentials/Database/Migrations/2021_06_17_121451_add_location_id_to_table.php)
- [2021_09_28_091541_create_essentials_user_sales_targets_table.php](../../../Modules/Essentials/Database/Migrations/2021_09_28_091541_create_essentials_user_sales_targets_table.php)

## Seeders — 1

- [EssentialsDatabaseSeeder.php](../../../Modules/Essentials/Database/Seeders/EssentialsDatabaseSeeder.php)

## Config — 2

- [config.php](../../../Modules/Essentials/Config/config.php)
- [retention.php](../../../Modules/Essentials/Config/retention.php)

## Views (Blade) — 87

- 1 em [Modules/Essentials/Resources/views/](../../../Modules/Essentials/Resources/views)
- 2 em [Modules/Essentials/Resources/views/allowance_deduction/](../../../Modules/Essentials/Resources/views/allowance_deduction)
- 14 em [Modules/Essentials/Resources/views/attendance/](../../../Modules/Essentials/Resources/views/attendance)
- 3 em [Modules/Essentials/Resources/views/dashboard/](../../../Modules/Essentials/Resources/views/dashboard)
- 2 em [Modules/Essentials/Resources/views/document/](../../../Modules/Essentials/Resources/views/document)
- 1 em [Modules/Essentials/Resources/views/document_share/](../../../Modules/Essentials/Resources/views/document_share)
- 3 em [Modules/Essentials/Resources/views/holiday/](../../../Modules/Essentials/Resources/views/holiday)
- 5 em [Modules/Essentials/Resources/views/knowledge_base/](../../../Modules/Essentials/Resources/views/knowledge_base)
- 3 em [Modules/Essentials/Resources/views/layouts/](../../../Modules/Essentials/Resources/views/layouts)
- 4 em [Modules/Essentials/Resources/views/layouts/partials/](../../../Modules/Essentials/Resources/views/layouts/partials)
- 5 em [Modules/Essentials/Resources/views/leave/](../../../Modules/Essentials/Resources/views/leave)
- 3 em [Modules/Essentials/Resources/views/leave_type/](../../../Modules/Essentials/Resources/views/leave_type)
- 1 em [Modules/Essentials/Resources/views/memos/](../../../Modules/Essentials/Resources/views/memos)
- 3 em [Modules/Essentials/Resources/views/messages/](../../../Modules/Essentials/Resources/views/messages)
- 2 em [Modules/Essentials/Resources/views/partials/](../../../Modules/Essentials/Resources/views/partials)
- 12 em [Modules/Essentials/Resources/views/payroll/](../../../Modules/Essentials/Resources/views/payroll)
- 2 em [Modules/Essentials/Resources/views/payroll/partials/](../../../Modules/Essentials/Resources/views/payroll/partials)
- 3 em [Modules/Essentials/Resources/views/reminder/](../../../Modules/Essentials/Resources/views/reminder)
- 2 em [Modules/Essentials/Resources/views/sales_targets/](../../../Modules/Essentials/Resources/views/sales_targets)
- 1 em [Modules/Essentials/Resources/views/settings/](../../../Modules/Essentials/Resources/views/settings)
- 6 em [Modules/Essentials/Resources/views/settings/partials/](../../../Modules/Essentials/Resources/views/settings/partials)
- 9 em [Modules/Essentials/Resources/views/todo/](../../../Modules/Essentials/Resources/views/todo)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Telas (Inertia/React) — 13

- [Index.tsx](../../../resources/js/Pages/Essentials/Documents/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Holidays/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/Essentials/Knowledge/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Essentials/Knowledge/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Knowledge/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Essentials/Knowledge/Show.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Messages/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Reminders/Index.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Settings/Index.tsx)
- [Create.tsx](../../../resources/js/Pages/Essentials/Todo/Create.tsx)
- [Edit.tsx](../../../resources/js/Pages/Essentials/Todo/Edit.tsx)
- [Index.tsx](../../../resources/js/Pages/Essentials/Todo/Index.tsx)
- [Show.tsx](../../../resources/js/Pages/Essentials/Todo/Show.tsx)

## Charters (lei da tela) — 13

- [Index.charter.md](../../../resources/js/Pages/Essentials/Documents/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Holidays/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Essentials/Knowledge/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Essentials/Knowledge/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Knowledge/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Essentials/Knowledge/Show.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Messages/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Reminders/Index.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Settings/Index.charter.md)
- [Create.charter.md](../../../resources/js/Pages/Essentials/Todo/Create.charter.md)
- [Edit.charter.md](../../../resources/js/Pages/Essentials/Todo/Edit.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Essentials/Todo/Index.charter.md)
- [Show.charter.md](../../../resources/js/Pages/Essentials/Todo/Show.charter.md)

## Testes (Pest) — 15

- 15 em [Modules/Essentials/Tests/Feature/](../../../Modules/Essentials/Tests/Feature)
- _Cobertura destes arquivos é do `casos-gate`/`screen-coverage`, não deste índice._

## Demais arquivos (manifestos, docs, assets e misc) — 52

- [CHANGELOG.md](../../../Modules/Essentials/CHANGELOG.md)
- [.gitkeep](../../../Modules/Essentials/Config/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Console/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Database/Migrations/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Database/Seeders/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Database/factories/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Entities/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Http/Controllers/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Http/Middleware/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Http/Requests/.gitkeep)
- [DocumentShareNotification.php](../../../Modules/Essentials/Notifications/DocumentShareNotification.php)
- [LeaveStatusNotification.php](../../../Modules/Essentials/Notifications/LeaveStatusNotification.php)
- [NewLeaveNotification.php](../../../Modules/Essentials/Notifications/NewLeaveNotification.php)
- [NewMessageNotification.php](../../../Modules/Essentials/Notifications/NewMessageNotification.php)
- [NewTaskCommentNotification.php](../../../Modules/Essentials/Notifications/NewTaskCommentNotification.php)
- [NewTaskDocumentNotification.php](../../../Modules/Essentials/Notifications/NewTaskDocumentNotification.php)
- [NewTaskNotification.php](../../../Modules/Essentials/Notifications/NewTaskNotification.php)
- [PayrollNotification.php](../../../Modules/Essentials/Notifications/PayrollNotification.php)
- [DocumentPolicy.php](../../../Modules/Essentials/Policies/DocumentPolicy.php)
- [KnowledgeBasePolicy.php](../../../Modules/Essentials/Policies/KnowledgeBasePolicy.php)
- [ToDoPolicy.php](../../../Modules/Essentials/Policies/ToDoPolicy.php)
- [.gitkeep](../../../Modules/Essentials/Providers/.gitkeep)
- [.gitkeep](../../../Modules/Essentials/Resources/assets/.gitkeep)
- [import_attendance_template.xls](../../../Modules/Essentials/Resources/assets/files/import_attendance_template.xls)
- [app.js](../../../Modules/Essentials/Resources/assets/js/app.js)
- [app.scss](../../../Modules/Essentials/Resources/assets/sass/app.scss)
- [.gitkeep](../../../Modules/Essentials/Resources/lang/.gitkeep)
- [lang.php](../../../Modules/Essentials/Resources/lang/ar/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/ce/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/de/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/en/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/es/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/fr/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/hi/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/id/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/lo/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/nl/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/ps/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/pt/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/ro/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/sq/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/tr/lang.php)
- [lang.php](../../../Modules/Essentials/Resources/lang/vi/lang.php)
- [topnav.php](../../../Modules/Essentials/Resources/menus/topnav.php)
- [.gitkeep](../../../Modules/Essentials/Resources/views/.gitkeep)
- [SCOPE.md](../../../Modules/Essentials/SCOPE.md)
- [.gitkeep](../../../Modules/Essentials/Tests/.gitkeep)
- [EssentialsUtil.php](../../../Modules/Essentials/Utils/EssentialsUtil.php)
- [composer.json](../../../Modules/Essentials/composer.json)
- [module.json](../../../Modules/Essentials/module.json)
- [package.json](../../../Modules/Essentials/package.json)
- [webpack.mix.js](../../../Modules/Essentials/webpack.mix.js)
