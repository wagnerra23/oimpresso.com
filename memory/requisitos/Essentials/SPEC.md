# SPEC — Módulo Essentials

> Origem: `Modules/Essentials`. Stack atual: Laravel 5.8 + Blade + Yajra DataTables.
> Documento criado pelo lote de testes `claude/tests-batch-5-grow-bi-dash`.

## Objetivo
Funcionalidades essenciais para qualquer negócio + HRM (RH): documentos,
todos, lembretes, mensagens, knowledge base, folha de pagamento, turnos,
ponto, férias, holidays, pagamentos.

## Rotas públicas (autenticadas)
Definidas em `Modules/Essentials/Http/routes.php`. Middleware da rota
principal: `web`, `authh`, `auth`, `SetSessionData`, `language`, `timezone`,
`AdminSidebarMenu`.

| Prefixo  | Recurso               | Controller                                    |
| -------- | --------------------- | --------------------------------------------- |
| essentials/ | / (index)          | EssentialsController                          |
| essentials/ | /dashboard         | DashboardController@essentialsDashboard       |
| essentials/ | document (resource)| DocumentController                            |
| essentials/ | document-share     | DocumentShareController                       |
| essentials/ | todo (resource)    | ToDoController                                |
| essentials/ | reminder (resource)| ReminderController                            |
| essentials/ | messages (resource)| EssentialsMessageController                   |
| essentials/ | allowance-deduction| EssentialsAllowanceAndDeductionController     |
| essentials/ | knowledge-base     | KnowledgeBaseController                       |
| hrm/        | leave-type/leave   | EssentialsLeaveTypeController/LeaveController |
| hrm/        | attendance         | AttendanceController                          |
| hrm/        | holiday            | EssentialsHolidayController                   |
| hrm/        | shift              | ShiftController                               |
| hrm/        | payroll            | PayrollController                             |
| hrm/        | settings           | EssentialsSettingsController                  |

## Tenancy / Authorization
- **business_id**: lido em todos os controllers via
  `request()->session()->get('user.business_id')`. Sem esse valor o módulo
  não deve renderizar nada.
- **Permissão**: combinação de `auth()->user()->can('superadmin')` ou
  `ModuleUtil::hasThePermissionInSubscription($business_id, 'essentials_module')`.
- **Locations**: `auth()->user()->permitted_locations()` define o escopo
  geográfico e é aplicado nos `whereIn` dos selects.

## Cobertura de testes (lote 5)
- `EssentialsControllerTest` — index/auth + skip-quando-sem-business_id.
- `EssentialsHolidayControllerTest` — index/store/destroy auth.
- `ToDoControllerTest` — index/store/update/destroy auth.
- `DocumentControllerTest` — index/show/store/destroy/download auth.
- `AttendanceControllerTest` — index + clock-in/clock-out auth.

Todos os testes estendem `EssentialsTestCase` (novo, em
`Modules/Essentials/Tests/Feature/`), que estende `Tests\TestCase` e oferece
helpers `assertRedirectsToLogin()` + `skipIfAppNotBooted()`.

## TODO (não coberto por este lote)
- Testes "happy path" autenticados (necessitam factory/seeder de Business +
  User; bloqueado pela ausência de vendor — ver TODO global).
- ReminderController, EssentialsMessageController, KnowledgeBaseController,
  PayrollController, ShiftController, EssentialsLeaveController,
  EssentialsLeaveTypeController, EssentialsSettingsController,
  EssentialsAllowanceAndDeductionController, DocumentShareController.
- Validação de input via FormRequest (atualmente nenhum FormRequest existe).
- Bug de timezone Carbon (ver `memory/feedback/feedback_carbon_timezone_bug.md`).
