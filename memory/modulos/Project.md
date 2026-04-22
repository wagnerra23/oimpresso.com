# Módulo: Project

> **Project Management Module**

- **Alias:** `project`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Project`
- **Status:** 🟢 ativo
- **Providers:** Modules\Project\Providers\ProjectServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 4 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions, addTaxonomies
- 🔐 Registra 3 permissão(ões) Spatie

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 20 |
| Controllers | 9 |
| Entities (Models) | 10 |
| Services | 0 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 43 |
| Migrations | 11 |
| Arquivos de lang | 16 |
| Testes | 0 |

## Rotas

### `web.php`

| Método | URI | Controller |
|---|---|---|
| `PUT` | `project/{id}/post-status` | `[Modules\Project\Http\Controllers\ProjectController::class, 'postProjectStatus']` |
| `PUT` | `project-settings` | `[Modules\Project\Http\Controllers\ProjectController::class, 'postSettings']` |
| `GET` | `project-task-get-status` | `[Modules\Project\Http\Controllers\TaskController::class, 'getTaskStatus']` |
| `PUT` | `project-task/{id}/post-status` | `[Modules\Project\Http\Controllers\TaskController::class, 'postTaskStatus']` |
| `PUT` | `project-task/{id}/post-description` | `[Modules\Project\Http\Controllers\TaskController::class, 'postTaskDescription']` |
| `POST` | `post-media-dropzone-upload` | `[Modules\Project\Http\Controllers\TaskCommentController::class, 'postMedia']` |
| `GET` | `project-invoice-tax-report` | `[Modules\Project\Http\Controllers\InvoiceController::class, 'getProjectInvoiceTaxReport']` |
| `GET` | `project-employee-timelog-reports` | `[Modules\Project\Http\Controllers\ReportController::class, 'getEmployeeTimeLogReport']` |
| `GET` | `project-timelog-reports` | `[Modules\Project\Http\Controllers\ReportController::class, 'getProjectTimeLogReport']` |
| `GET` | `project-reports` | `[Modules\Project\Http\Controllers\ReportController::class, 'index']` |
| `GET` | `/install` | `[Modules\Project\Http\Controllers\InstallController::class, 'index']` |
| `POST` | `/install` | `[Modules\Project\Http\Controllers\InstallController::class, 'install']` |
| `GET` | `/install/uninstall` | `[Modules\Project\Http\Controllers\InstallController::class, 'uninstall']` |
| `GET` | `/install/update` | `[Modules\Project\Http\Controllers\InstallController::class, 'update']` |
| `RESOURCE` | `project` | `'Modules\Project\Http\Controllers\ProjectController'` |
| `RESOURCE` | `project-task` | `'Modules\Project\Http\Controllers\TaskController'` |
| `RESOURCE` | `project-task-comment` | `'Modules\Project\Http\Controllers\TaskCommentController'` |
| `RESOURCE` | `project-task-time-logs` | `'Modules\Project\Http\Controllers\ProjectTimeLogController'` |
| `RESOURCE` | `activities` | `'Modules\Project\Http\Controllers\ActivityController'` |
| `RESOURCE` | `invoice` | `'Modules\Project\Http\Controllers\InvoiceController'` |

### `api.php`

_(arquivo existe mas parse não identificou rotas explícitas — pode ter grupos complexos)_

## Controllers

- **`ActivityController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`DataController`** — 9 ação(ões): parse_notification, modifyAdminMenu, grossProfit, user_permissions, superadmin_package, addDocumentAndNotes, addTaxonomies, getTaxReportViewTabs +1
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`InvoiceController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, getProjectInvoiceTaxReport
- **`ProjectController`** — 9 ação(ões): index, create, store, show, edit, update, destroy, postSettings +1
- **`ProjectTimeLogController`** — 7 ação(ões): index, create, store, show, edit, update, destroy
- **`ReportController`** — 3 ação(ões): index, getEmployeeTimeLogReport, getProjectTimeLogReport
- **`TaskCommentController`** — 8 ação(ões): index, create, store, show, edit, update, destroy, postMedia
- **`TaskController`** — 10 ação(ões): index, create, store, show, edit, update, destroy, getTaskStatus +2

## Entities (Models Eloquent)

- **`InvoiceLine`** (tabela: `pjt_invoice_lines`)
- **`Project`** (tabela: `pjt_projects`)
- **`ProjectCategory`** (tabela: `categorizables`)
- **`ProjectMember`** (tabela: `pjt_project_members`)
- **`ProjectTask`** (tabela: `pjt_project_tasks`)
- **`ProjectTaskComment`** (tabela: `pjt_project_task_comments`)
- **`ProjectTaskMember`** (tabela: `pjt_project_task_members`)
- **`ProjectTimeLog`** (tabela: `pjt_project_time_logs`)
- **`ProjectTransaction`** (tabela: `—`)
- **`ProjectUser`** (tabela: `users`)

## Migrations

- `2019_11_12_163135_create_projects_table.php`
- `2019_11_12_164431_create_project_members_table.php`
- `2019_11_14_112230_create_project_tasks_table.php`
- `2019_11_14_112258_create_project_task_members_table.php`
- `2019_11_18_154617_create_project_task_comments_table.php`
- `2019_11_19_134807_create_project_time_logs_table.php`
- `2019_12_11_102549_add_more_fields_in_transactions_table.php`
- `2019_12_11_102735_create_invoice_lines_table.php`
- `2020_01_07_172852_add_project_permissions.php`
- `2020_01_08_115422_add_project_module_version_to_system_table.php`
- `2020_07_10_114514_set_location_id_on_existing_invoice.php`

## Views (Blade)

**Total:** 43 arquivos

**Pastas principais:**

- `task/` — 9 arquivo(s)
- `invoice/` — 6 arquivo(s)
- `project/` — 6 arquivo(s)
- `reports/` — 5 arquivo(s)
- `activity/` — 4 arquivo(s)
- `layouts/` — 3 arquivo(s)
- `tax_report/` — 3 arquivo(s)
- `time_logs/` — 3 arquivo(s)
- `avatar/` — 1 arquivo(s)
- `D:/` — 1 arquivo(s)
- `my_task/` — 1 arquivo(s)
- `settings/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — Registra taxonomias/categorias customizadas

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `project.create_project`
- `project.edit_project`
- `project.delete_project`

**Usadas nas views** (`@can`/`@cannot`):

- `project.create_project`
- `project.edit_project`
- `project.delete_project`

## Peças adicionais

- **Notifications:** `NewCommentOnTaskNotification`, `NewProjectAssignedNotification`, `NewTaskAssignedNotification`
- **Seeders:** `ProjectDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Project` |
| `module_version` | `2.1` |
| `pid` | `5` |

## Integridade do banco

**Foreign Keys** (8):

- `project_id` → `pjt_projects.id`
- `project_id` → `pjt_projects.id`
- `project_task_id` → `pjt_project_tasks.id`
- `project_task_id` → `pjt_project_tasks.id`
- `project_id` → `pjt_projects.id`
- `project_task_id` → `pjt_project_tasks.id`
- `pjt_project_id` → `pjt_projects.id`
- `transaction_id` → `transactions.id`

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

- `js\project.js` (43.2 KB)

**Arquivos CSS/SCSS** (primeiros 1):

- `sass\project.css` (562 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ✅ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 119
- **Linhas +:** 11834 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2019_11_12_163135_create_projects_table.php`
  - `Database/Migrations/2019_11_12_164431_create_project_members_table.php`
  - `Database/Migrations/2019_11_14_112230_create_project_tasks_table.php`
  - `Database/Migrations/2019_11_14_112258_create_project_task_members_table.php`
  - `Database/Migrations/2019_11_18_154617_create_project_task_comments_table.php`
  - `Database/Migrations/2019_11_19_134807_create_project_time_logs_table.php`
  - `Database/Migrations/2019_12_11_102549_add_more_fields_in_transactions_table.php`
  - `Database/Migrations/2019_12_11_102735_create_invoice_lines_table.php`
  - `Database/Migrations/2020_01_07_172852_add_project_permissions.php`
  - `Database/Migrations/2020_01_08_115422_add_project_module_version_to_system_table.php`
  - `Database/Migrations/2020_07_10_114514_set_location_id_on_existing_invoice.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ProjectDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/InvoiceLine.php`
  - `Entities/Project.php`
  - `Entities/ProjectCategory.php`
  - `Entities/ProjectMember.php`
  - `Entities/ProjectTask.php`
  - `Entities/ProjectTaskComment.php`
  - `Entities/ProjectTaskMember.php`
  - `Entities/ProjectTimeLog.php`
  - `Entities/ProjectTransaction.php`
  - `Entities/ProjectUser.php`
  - `Http/Controllers/.gitkeep`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 119
- **Linhas +:** 11834 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2019_11_12_163135_create_projects_table.php`
  - `Database/Migrations/2019_11_12_164431_create_project_members_table.php`
  - `Database/Migrations/2019_11_14_112230_create_project_tasks_table.php`
  - `Database/Migrations/2019_11_14_112258_create_project_task_members_table.php`
  - `Database/Migrations/2019_11_18_154617_create_project_task_comments_table.php`
  - `Database/Migrations/2019_11_19_134807_create_project_time_logs_table.php`
  - `Database/Migrations/2019_12_11_102549_add_more_fields_in_transactions_table.php`
  - `Database/Migrations/2019_12_11_102735_create_invoice_lines_table.php`
  - `Database/Migrations/2020_01_07_172852_add_project_permissions.php`
  - `Database/Migrations/2020_01_08_115422_add_project_module_version_to_system_table.php`
  - `Database/Migrations/2020_07_10_114514_set_location_id_on_existing_invoice.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/ProjectDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/.gitkeep`
  - `Entities/InvoiceLine.php`
  - `Entities/Project.php`
  - `Entities/ProjectCategory.php`
  - `Entities/ProjectMember.php`
  - `Entities/ProjectTask.php`
  - `Entities/ProjectTaskComment.php`
  - `Entities/ProjectTaskMember.php`
  - `Entities/ProjectTimeLog.php`
  - `Entities/ProjectTransaction.php`
  - `Entities/ProjectUser.php`
  - `Http/Controllers/.gitkeep`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:14.**
**Reaxecutar com:** `php artisan module:spec Project`
