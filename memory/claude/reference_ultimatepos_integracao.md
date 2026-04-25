---
name: UltimatePOS — pontos de integração e schema HR
description: Referência consultável sobre como módulos PontoWr2/Officeimpresso/etc se plugam no core UltimatePOS. Tabelas HR relevantes, hooks obrigatórios (DataController), multi-tenancy session('user.business_id'), permissions Spatie {Nome}#{biz_id}, eventos emitidos pelo core.
type: reference
originSessionId: 6cbda521-1ac7-4ff2-9419-9acdb42822ac
---
# UltimatePOS — mapa de integração

Referência rápida pra conectar módulos novos (PontoWr2, Officeimpresso, qualquer futuro) com o core UltimatePOS. Consultar **antes de criar** tabela, controller, menu ou permission.

## 1. Arquivo-âncora

**`app/Utils/ModuleUtil.php`** — classe que descobre módulos instalados, chama hooks, valida subscriptions. Se precisar entender algo no core de módulos, começar por ela. Especialmente o método `getModuleData($hook_name)` que itera `DataController` de cada módulo ativo.

## 2. Estrutura de módulo UltimatePOS (padrão nwidart/laravel-modules)

```
Modules/{Nome}/
├── module.json                       # metadata (active, order, providers, requires)
├── start.php                         # registro de rotas globais
├── Providers/{Nome}ServiceProvider   # boot: migrations, views, translations, schedule
├── Http/Controllers/DataController   # HOOKS OBRIGATÓRIOS (ver seção 3)
├── Http/routes.php                   # rotas do módulo
├── Database/
│   ├── Migrations/                   # auto-loaded via ServiceProvider
│   └── Seeders/
├── Entities/                         # Models Eloquent
└── Resources/
    ├── views/
    ├── lang/
    └── menus/topnav.php              # ModuleTopNav declarativo
```

**Activation:** `modules_statuses.json` na raiz do repo tem `{"NomeModulo": true/false}`. Comando `php artisan module:enable X`.

## 3. Hooks do DataController (3 essenciais)

Qualquer módulo que queira aparecer/autorizar/limitar precisa implementar:

### `user_permissions()` — definir permissões do módulo

```php
public function user_permissions() {
    return [
        ['value' => 'pontowr2.view_attendance',  'label' => __('Ver ponto'), 'default' => false],
        ['value' => 'pontowr2.approve_leave',    'label' => __('Aprovar'),   'default' => false],
    ];
}
```

- Padrão de nome: `{module}.{ação}` em minúsculo
- Coletado em `RoleController@create/@edit` via `$this->moduleUtil->getModuleData('user_permissions')`
- Permissão é criada dinamicamente em DB quando role usa (Spatie auto)
- Validação: `auth()->user()->can('pontowr2.view_attendance')`

### `modifyAdminMenu()` — injetar items na sidebar

Chamado pelo middleware `AdminSidebarMenu` em toda requisição não-AJAX.

```php
public function modifyAdminMenu() {
    $businessId = session('user.business_id');
    if (! $this->moduleUtil->hasThePermissionInSubscription($businessId, 'pontowr2_module')) return;

    Menu::modify('admin-sidebar-menu', function ($menu) {
        $menu->url(route('ponto.dashboard'), __('Ponto WR2'), [
            'icon'   => 'fa fa-clock',
            'active' => request()->is('ponto/*'),
        ])->order(87);
    });
}
```

### `superadmin_package()` — permissões de subscription

Só relevante se cliente usa Superadmin (licenciamento):

```php
public function superadmin_package() {
    return [
        ['name' => 'pontowr2_module', 'label' => __('Módulo Ponto WR2'), 'default' => false],
    ];
}
```

Sem Superadmin instalado: `hasThePermissionInSubscription` retorna `true` sempre (livre).

## 4. Multi-tenancy — session('user.business_id')

**Não há scope global automático.** Cada query filtra manualmente.

Populado uma vez por sessão no middleware `SetSessionData`:

| Session key | Conteúdo | Formato | Uso típico |
|---|---|---|---|
| `user` | array id, surname, first_name, email, business_id, language | array | `session('user.business_id')` |
| `business` | objeto Business (Eloquent Model, ver `project_session_business_model.md`) | objeto | `session('business')->id` — CUIDADO dot-notation falha |
| `business_timezone` | string IANA `America/Sao_Paulo` | string | chave dedicada pra timezone, ver `feedback_carbon_timezone_bug.md` |
| `currency` | array code/symbol/thousand_separator/decimal_separator | array | `session('currency')['symbol']` |
| `financial_year` | objeto FinancialYear ativo | objeto | relatórios |

Roles Spatie: `{NomeRole}#{business_id}` (ex: `Admin#4`, `Vendas#4`, `Caixa#4`).

**Padrão em query:**
```php
$businessId = session('user.business_id');
MinhaTabela::where('business_id', $businessId)->get();
```

## 5. Tabelas core relevantes pra HR/Ponto

| Tabela | Business? | Colunas-chave | Uso |
|---|---|---|---|
| `users` | FK business_id | id, surname, first_name, email, username, essentials_department_id, essentials_designation_id | **Colaborador = User.** PontoWr2 JOINa aqui pra pegar nome |
| `business_locations` | FK business_id | id, location_id (BL0001), name, cnpj, zip_code | Filiais. Ponto pode ter REPs por location |
| `business` | — | id, name, time_zone, date_format, time_format, pos_settings (JSON), common_settings (JSON), essentials_settings (JSON) | Tenant root. `time_zone` é a TZ do cliente |
| `roles` | FK business_id | id, name (`{Nome}#{biz_id}`), guard_name, is_default | Spatie |
| `permissions` | — (global) | id, name (`{module}.{ação}`), guard_name | Spatie |
| `model_has_roles` | — | model_id, model_type, role_id | M2M users ↔ roles |
| `essentials_attendances` | idx business_id | user_id, business_id, clock_in_time, clock_out_time | **Registro de ponto nativo do Essentials** (usado se Essentials ativo) |
| `essentials_leaves` | idx business_id | user_id, leave_type_id, start_date, end_date, status | Licenças |
| `essentials_leave_types` | — | name, description | Tipos de licença |
| `essentials_shifts` | FK business_id | name, type (fixed/flexible), start_time, end_time | Turnos |
| `essentials_user_shifts` | — | user_id, shift_id, start_date, end_date | Atribuição de turno |
| `essentials_holidays` | FK business_id | date, name | Feriados |
| `essentials_departments` | FK business_id | name, description | Departamentos (FK em users.essentials_department_id) |
| `essentials_designations` | FK business_id | name, description | Cargos (FK em users.essentials_designation_id) |

**PontoWr2 tem schema próprio** (`ponto_marcacoes`, `ponto_apuracao_dia`, etc.) — NÃO usa `essentials_attendances` porque precisa de append-only + NSR sequencial + compliance Portaria 671. Mas **deve JOINar em `users`** pra obter nome do colaborador.

## 6. Events emitidos pelo core

`app/Events/*.php`. Módulos podem escutar via `Event::listen()` no ServiceProvider:

| Event | Quando dispara | Dados |
|---|---|---|
| `UserCreatedOrModified` | User CRUD | `$user`, `$action` ('created'/'updated') |
| `ContactCreatedOrModified` | Supplier/Customer CRUD | |
| `ProductsCreatedOrModified` | Product CRUD | |
| `SellCreatedOrModified` | Sale CRUD | |
| `PurchaseCreatedOrModified` | Purchase CRUD | |
| `TransactionPaymentAdded/Updated/Deleted` | Payment CRUD | |

**Uso pra PontoWr2:** escutar `UserCreatedOrModified` pra auto-criar registro `Colaborador` com defaults (escala padrão, controla_ponto=true). Sem listener atual — é hook aberto.

## 7. Menu system (AdminLTE legado + Inertia ModuleTopNav)

Dois sistemas coexistem:

1. **Sidebar tradicional (`Menu::modify`)** — AdminLTE-style, alimentado via `DataController::modifyAdminMenu()`. Cada módulo injeta items na `admin-sidebar-menu`.

2. **Topnav por módulo (`Resources/menus/topnav.php`)** — Inertia/React, declarativo. Arquivo retorna array de items. Lido por backend, filtrado por Spatie, exposto via `shell.topnavs[NomeModulo]`, auto-detectado pelo `AppShell` via `useAutoModuleNav()`. Ver `project_shell_nav_architecture.md`.

**Regra:** sidebar = navegação macro (entre módulos). Topnav = navegação intra-módulo (entre telas).

## 8. Padrões que PontoWr2 já segue (modelo pra outros módulos)

- ✅ `module.json` + `start.php` + `ServiceProvider`
- ✅ `DataController` com `user_permissions()` + `modifyAdminMenu()`
- ✅ Migrations auto-load via ServiceProvider
- ✅ `business_id` em todas tabelas operacionais
- ✅ Roles `{Nome}#{biz_id}` (ex: `Vendas#4`)
- ✅ Middleware próprio `CheckPontoAccess`
- ✅ ModuleTopNav declarativo em `Resources/menus/topnav.php`

## 9. Onde ler quando retomar

| Precisa saber | Lê |
|---|---|
| Como registrar módulo novo | `app/Utils/ModuleUtil.php` + `Modules/Essentials/Providers/*` |
| Como injetar menu | `Modules/Essentials/Http/Controllers/DataController.php@modifyAdminMenu` |
| Como filtrar por tenant | `app/Http/Middleware/SetSessionData.php` |
| Como usar session business | `project_session_business_model.md` na auto-memória |
| Como escutar event | `app/Events/UserCreatedOrModified.php` + `EventServiceProvider` |
| Schema HR | `Modules/Essentials/Database/Migrations/` |

## 10. Armadilhas conhecidas

- `session()->has('business.time_zone')` **retorna false** (business é Model, dot-notation não funciona). Usar chave `business_timezone` dedicada. Ver `project_session_business_model.md`.
- `Auth::user()->business` pode ser null em rotas públicas / rotas API. Sempre usar `optional()` ou null coalesce.
- Migrations Essentials **adicionam colunas** em `users` (essentials_department_id, essentials_designation_id). Se Essentials nunca foi instalado, colunas não existem. PontoWr2 não deve assumir presença.
- `modules_statuses.json` é fonte única de verdade pra saber se módulo está ativo. Código pode checar `Module::find('PontoWr2')?->isEnabled()`.
- Roles `{Nome}#{biz_id}` — sempre sufixar com business_id ao criar/consultar. Permissão `location.X` (X = location_id) é mandatória em roles operacionais (caso contrário `permitted_locations() = []`, ver `cliente_rotalivre.md`).

## 11. Restrições

- Nunca usar scope global de Eloquent pra `business_id` — quebra queries de superadmin que precisam cross-tenant
- Nunca mexer em `users` diretamente do módulo — sempre ir via Model `User` e events
- Nunca duplicar dados de colaborador em tabela do módulo (nome, email, etc) — JOIN em `users`
- `essentials_settings` em `business` é JSON — decodificar, modificar, re-encodar; nunca editar string
