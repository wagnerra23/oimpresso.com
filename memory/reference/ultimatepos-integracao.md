---
name: UltimatePOS — integração + DB schema + frontend patterns (consolidado)
description: Como módulos novos se plugam no core UltimatePOS — DataController hooks (user_permissions/modifyAdminMenu/superadmin_package), multi-tenant via session('user.business_id'), tabelas core (business/transactions/users/contacts/products/roles), FK pra business.id é unsignedInteger (legacy), DataTables locale pt-BR, payload SellPosController@store, events do core, armadilhas.
type: reference
---

# UltimatePOS — mapa de integração + schema + padrões frontend

Referência rápida pra módulos novos (PontoWr2/Officeimpresso/NfeBrasil/ComunicacaoVisual/qualquer futuro). **Consultar antes de criar tabela, controller, menu, permission, migration ou Page Inertia que toque venda.**

## 1. Arquivo-âncora

**`app/Utils/ModuleUtil.php`** — descobre módulos instalados, chama hooks, valida subscriptions. Método chave: `getModuleData($hook_name)` itera `DataController` de cada módulo ativo.

## 2. Estrutura de módulo (padrão nwidart/laravel-modules)

```
Modules/{Nome}/
├── module.json                       # metadata (active, order, providers, requires)
├── start.php                         # registro de rotas globais
├── Providers/{Nome}ServiceProvider   # boot: migrations, views, translations, schedule
├── Http/Controllers/DataController   # HOOKS OBRIGATÓRIOS (ver §3)
├── Http/routes.php                   # rotas do módulo
├── Database/Migrations/              # auto-loaded via ServiceProvider
├── Entities/                         # Models Eloquent
└── Resources/{views,lang,menus/topnav.php}
```

**Activation:** `modules_statuses.json` na raiz tem `{"NomeModulo": true/false}`. Comando `php artisan module:enable X`.

## 3. Hooks DataController (3 essenciais)

### `user_permissions()`
```php
public function user_permissions() {
    return [
        ['value' => 'pontowr2.view_attendance', 'label' => __('Ver ponto'), 'default' => false],
    ];
}
```
- Padrão `{module}.{ação}` em minúsculo
- Coletado em `RoleController@create/@edit` via `$this->moduleUtil->getModuleData('user_permissions')`
- Validação: `auth()->user()->can('pontowr2.view_attendance')`

### `modifyAdminMenu()` — sidebar AdminLTE legado
Chamado pelo middleware `AdminSidebarMenu` em toda req não-AJAX:
```php
public function modifyAdminMenu() {
    $businessId = session('user.business_id');
    if (! $this->moduleUtil->hasThePermissionInSubscription($businessId, 'pontowr2_module')) return;
    Menu::modify('admin-sidebar-menu', function ($menu) {
        $menu->url(route('ponto.dashboard'), __('Ponto WR2'), [
            'icon' => 'fa fa-clock', 'active' => request()->is('ponto/*'),
        ])->order(87);
    });
}
```

### `superadmin_package()` — subscription/licenciamento
Sem Superadmin instalado, `hasThePermissionInSubscription` retorna `true` sempre.

## 4. Multi-tenancy — session('user.business_id')

**Não há scope global automático no core** — cada query filtra manualmente. Populado pelo middleware `SetSessionData`:

| Session key | Conteúdo | Uso |
|---|---|---|
| `user` | array (id, surname, first_name, email, business_id, language) | `session('user.business_id')` |
| `business` | objeto Business (Eloquent Model) | `session('business')->id` — CUIDADO dot-notation falha |
| `business_timezone` | string IANA `America/Sao_Paulo` | chave dedicada (dot-notation em `business` falha) |
| `currency` | array (code/symbol/separators) | `session('currency')['symbol']` |
| `financial_year` | objeto FinancialYear ativo | relatórios |

Roles Spatie: `{NomeRole}#{business_id}` (`Admin#4`, `Vendas#4`, `Caixa#4`).

```php
$businessId = session('user.business_id');
MinhaTabela::where('business_id', $businessId)->get();
```

## 5. Tabelas core do schema

### `business` — tenant raiz
`id`, `name`, **`time_zone`** (IANA), `date_format`, `time_format` (12/24), `currency_id`, `default_sales_tax`, `fy_start_month`, `common_settings`/`custom_labels`/`pos_settings` (JSON), `enable_rp`, `enable_product_expiry`.

### `business_locations` — filiais
`id`, `business_id`, `location_id` (BL0001), `name`, `cnpj`, `razao_social`, `state`, `city`, `zip_code`, `is_active`, `default_payment_accounts` (JSON). **Não tem `time_zone`** — sempre vem de `business.time_zone`.

### `transactions` — core operacional
Tipos: `sell`, `purchase`, `sell_return`, `purchase_return`, `expense`, `opening_stock`, `opening_balance`, `production_sell`, `production_purchase`, `payroll`, `stock_adjustment`, `sales_order`, `purchase_order`, `transfer`.

Colunas: `business_id`, `location_id`, `contact_id`, `type`, `status`, `payment_status`, `invoice_no`, `ref_no`, `transaction_date` (datetime "horário de parede"), `final_total`, `total_before_tax`, `tax_amount`, `discount_amount`, `created_by`, `sub_type`.

`transaction_date` = horário escolhido pelo operador (pode ser retroativo). `created_at` = horário real do insert. Diff ≠ bug timezone.

### Outras tabelas
- `transaction_sell_lines` / `purchase_lines` — itens (FK `transaction_id`, `variation_id`)
- `transaction_payments` — `transaction_id`, `amount`, `method` (cash/card/cheque/bank_transfer/custom_pay_1..7), `paid_on`, `account_id`
- `contacts` — clientes+fornecedores juntos. `type` ∈ `customer`/`supplier`/`both`
- `products`, `variations`, `variation_location_details` — estoque por location em `variation_location_details.qty_available`
- `users` — `business_id`, `user_type` ∈ `admin`/`user`/`superadmin`, `essentials_department_id`, `essentials_designation_id`
- `activity_log` — Spatie auditoria (cresce rápido)
- `roles`/`permissions` — Spatie. **Role sem `location.{id}` nem `access_all_locations` deixa `permitted_locations()=[]`** — trava `/sells/create`

### Tabelas Essentials/HR
| Tabela | Uso |
|---|---|
| `essentials_attendances` | Ponto nativo Essentials (PontoWr2 NÃO usa — append-only próprio) |
| `essentials_leaves` / `_leave_types` | Licenças |
| `essentials_shifts` / `_user_shifts` | Turnos + atribuições |
| `essentials_holidays` | Feriados |
| `essentials_departments` / `_designations` | Departamentos + cargos (FKs em users) |

PontoWr2 tem schema próprio (`ponto_marcacoes`, `ponto_apuracao_dia`) por compliance Portaria 671 (append-only + NSR sequencial). **Sempre JOIN em `users` pra obter nome do colaborador.**

## 6. Convenções DB críticas

- **Sem FK declaradas em muitas tabelas legacy** — integridade controlada em app
- **Soft deletes** (`products`, `contacts`, `business_locations`, `users`) via `deleted_at`
- **JSON em várias colunas** — sempre decodificar/encodificar; nunca editar string
- **Datetime sem TZ info** — valor é "horário de parede" conforme `app.timezone` no momento da escrita

### FK pra business.id é `unsignedInteger` (NÃO BigInteger)

`business.id` é **`int(10) unsigned`** legacy UltimatePOS. Laravel default `unsignedBigInteger` → MySQL strict quebra:
```
SQLSTATE[HY000]: General error: 1005 Can't create table
errno: 150 "Foreign key constraint is incorrectly formed"
```

Local SQLite não enforce → bug só aparece em deploy Hostinger MySQL strict.

**Regra:**
```php
// CORRETO
$table->unsignedInteger('business_id');
$table->foreign('business_id', 'fk_xxx_business')
      ->references('id')->on('business')->onDelete('cascade');

// ERRADO — bigint(20) unsigned, FK falha
$table->unsignedBigInteger('business_id');
```

Outras FKs legacy `int(10) unsigned`: `users.id`, `contacts.id`, `products.id`. FKs entre tabelas modules novos (criadas com `bigIncrements`) usam `unsignedBigInteger` normalmente.

Histórico: PR #477 (2026-05-10) corrigiu 4 migrations Modules/ComunicacaoVisual; tabela `comvis_materiais` ficou órfã. Recovery em deploy-recovery-patterns.md.

## 7. Events do core

`app/Events/*.php` — módulos escutam via `Event::listen()`:

| Event | Quando | Dados |
|---|---|---|
| `UserCreatedOrModified` | User CRUD | `$user`, `$action` |
| `ContactCreatedOrModified` | Supplier/Customer CRUD | |
| `ProductsCreatedOrModified` | Product CRUD | |
| `SellCreatedOrModified` | Sale CRUD | |
| `PurchaseCreatedOrModified` | Purchase CRUD | |
| `TransactionPaymentAdded/Updated/Deleted` | Payment CRUD | |

Uso típico: PontoWr2 escuta `UserCreatedOrModified` pra auto-criar `Colaborador` com defaults.

## 8. Menu system (2 sistemas coexistem)

1. **Sidebar tradicional (`Menu::modify`)** — AdminLTE-style via `DataController::modifyAdminMenu()`
2. **Topnav por módulo (`Resources/menus/topnav.php`)** — Inertia/React declarativo. Backend filtra Spatie, expõe via `shell.topnavs[NomeModulo]`, auto-detectado pelo `AppShell` via `useAutoModuleNav()`

Regra: sidebar = navegação macro (entre módulos). Topnav = intra-módulo.

## 9. SellPosController@store — payload Inertia → Blade legacy

`POST /pos` → `SellPosController@store` (Route::resource pos). Mesma rota do Blade legacy. Inertia React precisa transformar payload pra bater com convenções legacy.

Mapeamento confirmado via smoke biz=1 2026-05-10 (PRs #421/#424/#426/#427/#448):

```typescript
// useForm.transform() em resources/js/Pages/Sells/Create.tsx
transform((d) => ({
  ...d,
  // FLAG CRÍTICO — sem isso cai em cashRegister check (linha 364) e nem entra no try
  is_direct_sale: 1,

  // Renames Inertia → Blade
  payment: d.payments,                      // singular array (d.payments → d.payment[])
  commission_agent: d.commission_agent_id,  // sem _id
  price_group: d.price_group_id,            // sem _id
  default_price_group: d.price_group_id,    // hidden Blade fallback
  sale_note: d.notes,
  additional_notes: d.notes,

  // Flatten shipping object → top-level
  shipping_details: d.shipping.details,
  shipping_address: d.shipping.address,
  shipping_charges: d.shipping.cost,        // 'cost' → 'charges'
  shipping_status: d.shipping.status,
  delivered_to: d.shipping.deliver_to,      // 'deliver_to' → 'delivered_to'

  // Total client-calculated (backend re-calcula mas evita 422)
  final_total: totalGeral,

  // Cada produto precisa CAMPOS DEFENSIVOS — backend acessa direto sem isset()
  products: d.products.map((p) => ({
    ...p,
    unit_price_inc_tax: p.unit_price,   // ProductUtil.php:650 acessa direto
    item_tax: 0,                         // TransactionUtil.php:357
    tax_id: null,
    line_discount_type: 'fixed',
    line_discount_amount: p.discount,
    enable_stock: 1,                     // SellPosController:581 (decreaseProductQuantity)
    product_type: 'single',              // linha 590 (combo check)
  })),
}))
```

**Backend response:** sucesso → `redirect()->action(SellController::class, 'index')` → `/sells`. Inertia AJAX precisa header `X-Inertia` guard em `SellController@index:91` (PR #426) pra cair no `Inertia::render('Sells/Index')` em vez do branch DataTables JSON.

**Pendente:** ProductSearchAutocomplete API (`/products/list`) deveria retornar `enable_stock` + `product_type` reais por produto pra evitar default ruim com services (`enable_stock=0`) e combos (`product_type='combo'`).

## 10. DataTables locale pt-BR

Arquivo compartilhado: **`public/locale/datatables/pt-BR.json`** (criado 2026-04-24). Traduções de `info`, `lengthMenu`, `paginate`, `buttons` (CSV/Excel/PDF/Imprimir/Visibilidade), `zeroRecords`, `search`.

```js
$('#meu_table').DataTable({
    language: { url: '{{ asset('locale/datatables/pt-BR.json') }}' },
});
```

Sem isso, tabela exibe inglês. Primeira aplicação: `resources/views/sell/index.blade.php` (commit `dcefd087`). Várias telas ainda sem URL — sweep global pendente.

## 11. Padrões que módulos seguem (modelo)

PontoWr2/Repair/Project/Jana já fazem:
- `module.json` + `start.php` + `ServiceProvider`
- `DataController` com `user_permissions()` + `modifyAdminMenu()`
- Migrations auto-load via ServiceProvider
- `business_id` em todas tabelas operacionais
- Roles `{Nome}#{biz_id}`
- Middleware próprio (`CheckPontoAccess` etc)
- ModuleTopNav declarativo

## 12. Onde ler quando retomar

| Precisa saber | Lê |
|---|---|
| Registrar módulo novo | `app/Utils/ModuleUtil.php` + `Modules/Essentials/Providers/*` |
| Injetar menu | `Modules/Essentials/Http/Controllers/DataController.php@modifyAdminMenu` |
| Filtrar por tenant | `app/Http/Middleware/SetSessionData.php` |
| Schema HR | `Modules/Essentials/Database/Migrations/` |
| Escutar event | `app/Events/UserCreatedOrModified.php` + `EventServiceProvider` |

## 13. Armadilhas conhecidas

- `session()->has('business.time_zone')` retorna **false** (business é Model, dot-notation falha) — usar `business_timezone` dedicada
- `Auth::user()->business` pode ser null em rotas públicas/API — usar `optional()` ou null coalesce
- Migrations Essentials adicionam colunas em `users` (`essentials_*_id`); se Essentials nunca instalado, colunas não existem. Módulos não devem assumir presença
- `modules_statuses.json` é fonte única; código pode checar `Module::find('PontoWr2')?->isEnabled()`
- Roles `{Nome}#{biz_id}` — sempre sufixar com `business_id`
- Permissão `location.X` (X = location_id) é mandatória em roles operacionais
- `essentials_settings` em `business` é JSON — decodificar/modificar/re-encodar; nunca editar string
- `transaction_date` ≠ `created_at`: diff é normal (operador digita retroativo), não é bug TZ

## 14. Restrições

- Nunca scope global Eloquent pra `business_id` no core — quebra queries superadmin cross-tenant
- Nunca mexer em `users` direto do módulo — sempre via Model `User` + events
- Nunca duplicar dados de colaborador (nome/email) em tabela do módulo — JOIN em `users`
- Antes de query cross-business: `business_id` no WHERE obrigatório, `type='sell'` se quer só vendas (muitos tipos coexistem em `transactions`), `deleted_at IS NULL` em soft delete
- Pra timezone: confiar em `business.time_zone`, não inferir por location

## 15. Detecção drift FK business_id

Regression guard pendente em `tests/Unit/Guards/`:
```php
it('guard: novas migrations modules usam unsignedInteger pra business_id', function () {
    // grep -E "unsignedBigInteger\(['\"]business_id" Modules/*/Database/Migrations/
    // expect violations to be empty
});
```
Não criado ainda — candidato se aparecer 2ª recidiva.
