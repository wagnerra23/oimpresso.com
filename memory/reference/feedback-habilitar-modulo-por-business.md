---
name: feedback-habilitar-modulo-por-business
description: Pra habilitar/desabilitar item do sidebar pra business específico, usar SEMPRE subscription package via Modules/Superadmin/PackagesController. NUNCA hardcode `if ($business_id === N) return` — Wagner regra 2026-05-18 IRREVOGÁVEL (Tier 0 lado a lado com business_id global scope).
type: feedback
---

# Habilitar/desabilitar módulo por business — SUBSCRIPTION PACKAGES, NÃO hardcode

> **Status:** Tier 0 IRREVOGÁVEL — catalogada em [`memory/proibicoes.md`](../proibicoes.md) §"Multi-tenant Tier 0" lado a lado com `business_id` global scope (ADR 0093). Wagner palavras textuais 2026-05-18: *"Regra basica junto com Business_id acho que não porderia ser diferente"*.

**Regra IRREVOGÁVEL Wagner 2026-05-18:** *"Nunca faça isso, habilitar e desabilitar é compra de pacote no modulo superadmin"*.

Pra controlar visibilidade de módulos/items do sidebar por business específico, o pattern UltimatePOS canônico é **subscription packages** — NUNCA hardcode `if ($business_id === N) return` em DataController ou Middleware.

## Pattern canônico

### Como funciona (UltimatePOS)

1. **Pacote** (`Modules\Superadmin\Entities\Package`) define quais módulos estão ativos via `package_details` (JSON map `module_name => bool/limit`)
2. **Business** assina **uma `Subscription`** ativa apontando pra um Pacote (`Modules\Superadmin\Entities\Subscription::active_subscription($business_id)`)
3. **DataController** de cada módulo checa via `ModuleUtil::hasThePermissionInSubscription($business_id, 'modulo_module', 'superadmin_package')`
4. Se package tem `package_details['modulo_module'] = true` → libera. Else → return (esconde item)
5. **Superadmin sempre retorna true** (acesso total a TODOS módulos, sem precisar pacote)

### Onde mexer pra esconder/liberar (sem código)

UI Superadmin → **`/superadmin/packages`** (`Modules/Superadmin/Http/Controllers/PackagesController`):

1. Login como superadmin
2. Menu Superadmin → Pacotes
3. **Editar o pacote** ativo da business alvo (descobrir via Modules/Superadmin business → subscription)
4. Marcar/desmarcar checkbox do módulo (`woocommerce_module`, `financeiro_module`, `essentials_module`, `governance_module`, `expenses_module`, `service_staff_module`, etc)
5. Salvar — quick-sync invalida cache, refresh do user mostra novo sidebar

### Code pattern correto em DataController

```php
public function modifyAdminMenu(): void
{
    $module_util = new ModuleUtil();

    if (auth()->user()->can('superadmin')) {
        $is_enabled = $module_util->isModuleInstalled('NomeModulo');
    } else {
        $business_id = session('user.business_id');
        $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
            $business_id,
            'nomemodulo_module',          // chave no package_details
            'superadmin_package'           // callback function
        );
    }

    if (! $is_enabled) {
        return;  // package não tem este módulo → esconde do sidebar
    }

    // Permission gate adicional (role/Spatie) pra controlar quem dentro
    // do business pode ver (admin vs operador, etc):
    if (! auth()->user()->can('superadmin') && ! auth()->user()->can('nomemodulo.access')) {
        return;
    }

    Menu::modify('admin-sidebar-menu', function ($menu) {
        // ... render menu ...
    });
}
```

### Code pattern correto em AdminSidebarMenu core (UltimatePOS)

```php
// $enabled_modules vem de session('business.enabled_modules') no boot do middleware
// — preenchido com array de keys ativas no package atual (ex: ['expenses', 'service_staff'])

if (in_array('expenses', $enabled_modules) && auth()->user()->can('all_expense.access')) {
    $menu->dropdown(/* ... */);
}
```

`$enabled_modules` é o source-of-truth — não há `if ($business_id === N)`.

## Anti-pattern (NUNCA fazer)

```php
// ❌ HARDCODE biz=N — viola Tier 0 conceitualmente + impossível Wagner gerenciar via UI
$business_id = (int) session('user.business_id');
if ($business_id === 4) {
    return;
}

// ❌ EXCEÇÃO POSITIVA hardcode (mesmo problema)
$piloto_rotalivre = ($business_id === 4);
if (! $piloto_rotalivre && ! auth()->user()->can('feature.access')) {
    return;
}
```

**Por que é errado:**
- Wagner precisa **subir PR + deploy** pra cada business novo que quer habilitar/desabilitar
- Acopla comportamento de visibilidade a **número mágico** sem contexto histórico
- Não escala (10+ clientes piloto = N if-elif-elif maluco)
- **Wagner não consegue ver/editar via UI** (precisa abrir código pra cada cliente)

## Histórico — episódio que motivou essa regra

**2026-05-18 — sessão biz=4 ROTA LIVRE:**

Wagner pediu: *"Libere para empresa 4 o Financeiro/DRE/Fluxo/Boletos. Remova Tarefas/Governança/Despesas/Pedidos/Woocommerce"*.

Claude (erro arquitetural) interpretou como **hardcode `=== 4`** em 6 lugares (PRs #1073/#1074/#1076):
- `Modules/Financeiro/Http/Controllers/DataController.php` — exceção `! $piloto_rotalivre`
- `Modules/Governance/Http/Controllers/DataController.php` — `if (=== 4) return`
- `Modules/Woocommerce/Http/Controllers/DataController.php` — `if (=== 4) return`
- `app/Http/Middleware/HandleInertiaRequests.php` — `&& $businessId !== 4`
- `app/Http/Middleware/AdminSidebarMenu.php` — `&& $current_biz !== 4` em Expenses E Service Staff

Wagner pegou e reagiu: *"Nuca faça isso, habilitar e desabilitar é compra de pacote no modulo superadmin"*.

PR #1077 (revert) — restaurou os 6 lugares pro pattern canônico (subscription package). Memória canon corrigida pra ENSINAR o pattern certo. Wagner configurará package biz=4 via UI Superadmin sem deploy code.

## Checklist correto pra habilitar/desabilitar módulo por business

```
- [ ] 1. Identificar a chave do módulo no package_details (ex: 'woocommerce_module')
- [ ] 2. Login superadmin → /superadmin/packages
- [ ] 3. Editar o pacote ativo da business alvo
- [ ] 4. Marcar/desmarcar o módulo
- [ ] 5. Salvar
- [ ] 6. Quick Sync NÃO necessário (mudança é em DB) — refresh do usuário ja mostra
- [ ] 7. Validar com Ctrl+Shift+R no business alvo
```

## Quando o pattern subscription NÃO existe pro módulo

Alguns módulos antigos ou customizados podem não ter o gate `hasThePermissionInSubscription` ainda. **Solução: adicionar o gate** — não fazer hardcode biz=N.

```php
// ✅ ADICIONAR gate canon em DataController do módulo
$is_enabled = $module_util->hasThePermissionInSubscription(
    $business_id, 'mymodule_module', 'superadmin_package'
);
if (! $is_enabled) return;

// E depois adicionar 'mymodule_module' como opção checkable em
// Modules/Superadmin/Resources/views/packages/edit.blade.php
// (lista permission_formatted definida em Superadmin)
```

## Lista de módulos com gate canônico (descoberta em prod 2026-05-18)

24+ chaves consultáveis via UI Superadmin/Packages:

| Módulo | Chave package_details | Permission Spatie principal |
|---|---|---|
| Accounting (Contabilidade) | `accounting_module` | `accounting.view_account_summary` |
| ADS | `ads_module` | `ads.access` |
| Asset Management (Gestão de Ativos) | `assetmanagement_module` | `asset_management.view_asset` |
| Auditoria | `auditoria_module` | `auditoria.view` |
| CRM | `crm_module` | `crm.access` |
| Comunicação Visual | `comunicacaovisual_module` | `comunicacaovisual.access` |
| Connector | `connector_module` | `connector.access` |
| Consulta OS (Portal) | `consultaos_module` | `consultaos.access` |
| Essentials (Tarefas/Mensagens/Docs) | `essentials_module` | `essentials.access` |
| Financeiro | `financeiro_module` | `financeiro.access` |
| **Governance** ★ novo PR #1079 | `governance_module` | `governance.dashboard.view` |
| Jana / Copiloto | `copiloto_module` | `copiloto.access` |
| Knowledge Base (KB) | `kb_module` | `kb.access` |
| Manufacturing (Fabricação) | `manufacturing_module` | `manufacturing.view` |
| NF-e Brasil | `nfebrasil_module` | `nfebrasil.access` |
| NFS-e | `nfse_module` | `nfse.access` |
| Oficina Auto | `oficinaauto_module` | `oficinaauto.access` |
| Ponto | `ponto_module` | `ponto.access` |
| Product Catalogue | `productcatalogue_module` | `productcatalogue.access` |
| Project Mgmt | `projectmgmt_module` | `projectmgmt.access` |
| Recurring Billing (Cobrança Recorrente) | `recurringbilling_module` | `recurringbilling.access` |
| Repair (Reparar) | `repair_module` | `repair.access` |
| WhatsApp | `whatsapp_module` | `whatsapp.access` |
| Woocommerce | `woocommerce_module` | `woocommerce.access_*` |

**Core UltimatePOS** (gate via `session('business.enabled_modules')` array, configurável também no Package):
- `expenses` (Despesas)
- `service_staff` (Pedidos / Restaurant Orders)
- `kitchen` (Cocina)
- `tables` (Mesas)
- `booking` (Reservas)
- `modifiers` (Modificadores)

## Caso o módulo NÃO tenha gate canônico — como criar (pattern PR #1079)

Se ao auditar um módulo você descobrir que ele **não tem** `superadmin_package()` registrando uma `_module` permission, **adicione** seguindo este pattern (caso Governance PR #1079):

```php
// Modules/<Nome>/Http/Controllers/DataController.php

use App\Utils\ModuleUtil;

class DataController extends Controller
{
    // 1. Registrar permission package — aparece como checkbox em /superadmin/packages
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'nomemodulo_module',
                'label'   => __('nomemodulo::nomemodulo.module_label'),
                'default' => false,  // false = pacotes novos não vêm com módulo ativo
            ],
        ];
    }

    // 2. (opcional) Permissions Spatie do usuário — role-based dentro do business
    public function user_permissions(): array
    {
        return [
            [ 'value' => 'nomemodulo.access', 'label' => '...', 'default' => false ],
        ];
    }

    // 3. modifyAdminMenu() com 3 gates em ordem
    public function modifyAdminMenu(): void
    {
        if (! auth()->check()) return;
        $module_util = new ModuleUtil();

        // Gate 1: pacote subscription
        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('NomeModulo');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
                $business_id, 'nomemodulo_module', 'superadmin_package'
            );
        }
        if (! $is_enabled) return;

        // Gate 2: permission Spatie do usuário
        if (! auth()->user()->can('superadmin') && ! auth()->user()->can('nomemodulo.access')) {
            return;
        }

        // Gate 3: render menu
        Menu::modify('admin-sidebar-menu', function ($menu) { /* ... */ });
    }
}
```

E **registrar Pest estrutural** garantindo que NÃO há hardcode (template em `Modules/Governance/Tests/Feature/GovernanceModuleSubscriptionGateTest.php`).

## Defesas técnicas que previnem reincidência

| Camada | Onde | O que faz |
|---|---|---|
| 1. Memória canônica | [`memory/proibicoes.md`](../proibicoes.md) §Multi-tenant Tier 0 IRREVOGÁVEL | Carrega em TODA sessão Claude via SessionStart hook |
| 2. Memória detalhada | Este arquivo + cross-link em `_INDEX.md` | Time MCP (Felipe/Maiara/Eliana/Luiz) consulta via tools MCP |
| 3. Pest anti-regressão geral | [`tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php`](../../tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php) | Varre TODOS DataControllers + Middlewares + Controllers em CI. Bloqueia merge se qualquer um introduzir `$business_id === N` hardcode |
| 4. Pest test específico biz=4 | [`tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php`](../../tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php) | Anti-regressão histórica dos 5 arquivos tocados na sessão 2026-05-18 |
| 5. Pest test específico Governance | `Modules/Governance/Tests/Feature/GovernanceModuleSubscriptionGateTest.php` | Garante que gate canônico permanece + sem hardcode |

## Refs

- `app/Utils/ModuleUtil.php:143` — `hasThePermissionInSubscription()` (canon)
- `Modules/Superadmin/Entities/Package.php` — model + relations
- `Modules/Superadmin/Entities/Subscription.php` — `active_subscription($business_id)`
- `Modules/Superadmin/Http/Controllers/PackagesController.php` — CRUD UI
- ADR 0093 (multi-tenant Tier 0)
- `memory/proibicoes.md` §"Multi-tenant Tier 0 IRREVOGÁVEL" (Tier 0)
- `Modules/Auditoria/Http/Controllers/DataController.php` — template canônico de referência

## Anti-patterns

- ❌ `if ($business_id === N) return` (motivo desta regra)
- ❌ Acoplar visibilidade de UI ao número da business em código
- ❌ Esconder menu mas esquecer gate no controller (URL direta acessa)
- ❌ Modificar `session('business.enabled_modules')` manualmente em código
- ❌ Filtrar via JS no frontend (SIDEBAR_GROUPS) — backend já entrega filtrado

## Histórico

- **2026-05-18** — instalado após revert do erro arquitetural (sessão biz=4 ROTA LIVRE). Conteúdo original deste arquivo (PR #1076) ensinava o anti-pattern hardcode e foi substituído.
