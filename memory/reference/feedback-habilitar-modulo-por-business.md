---
name: feedback-habilitar-modulo-por-business
description: Habilitar/desabilitar módulo por business. 2 CAMADAS — (1) Package nWidart via UI Superadmin/Packages; (2) business.enabled_modules core UltimatePOS via /business/settings. NUNCA hardcode `if ($business_id === N) return`. Wagner regra Tier 0 IRREVOGÁVEL 2026-05-18.
type: feedback
---

# Habilitar/desabilitar módulo por business — 2 CAMADAS canônicas

> **Status:** Tier 0 IRREVOGÁVEL — catalogada em [`memory/proibicoes.md`](../proibicoes.md) §"Multi-tenant Tier 0" lado a lado com `business_id` global scope (ADR 0093). Wagner palavras textuais 2026-05-18: *"Regra basica junto com Business_id acho que não porderia ser diferente"*.
>
> **Use com recorrência** — Wagner vai pedir isso o tempo todo (cada novo cliente piloto, cada feature em desenvolvimento, cada SaaS package tier). Caminho canônico é UI, não código. Lê este doc ANTES de implementar qualquer guard novo.

**Regra IRREVOGÁVEL Wagner 2026-05-18:** *"Nunca faça isso, habilitar e desabilitar é compra de pacote no modulo superadmin"*.

---

## TL;DR — 30 segundos

Habilitar/desabilitar visibilidade de módulo pra um business **sempre** via UI canônica (zero deploy code):

| O que esconder | Onde habilita/desabilita | Quem edita |
|---|---|---|
| **Módulo nWidart** (`Modules/X/`) — Financeiro, CRM, Woocommerce, Governance, Repair, OficinaAuto, etc | `/superadmin/packages/{id}/edit` → checkbox `X_module` | **Superadmin** (Wagner) — vê todos businesses |
| **Módulo core UltimatePOS** — Despesas, Pedidos (`service_staff`), Cocina, Mesas, Reservas, etc | `/business/settings` → checkboxes "Habilitar Módulos" | **User do próprio business** (Larissa pra biz=4) — NÃO tem switch business |

**NUNCA** hardcode `if ($business_id === N) return` em DataController ou Middleware. Pest global `tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php` bloqueia no CI.

---

## CAMADA 1 — Package nWidart (Subscription)

### Como funciona

1. **`Modules\Superadmin\Entities\Package`** define quais módulos estão ativos via JSON `package_details` (mapa `X_module => bool/limit`)
2. **`Modules\Superadmin\Entities\Subscription`** liga business → package ativo (`active_subscription($business_id)`)
3. **DataController** do módulo nWidart checa via `ModuleUtil::hasThePermissionInSubscription($business_id, 'X_module', 'superadmin_package')`
4. **Superadmin** (`auth()->user()->can('superadmin')`) sempre retorna `true` (bypass — acesso total sem package)

### Como editar via UI (Wagner)

```
Login superadmin → /superadmin/packages
  → Editar o pacote ativo da business alvo (ex: "Contrato Rota Livre Semestral" = biz=4)
  → Aba "Permissões personalizadas" — marcar/desmarcar checkbox X_module
  → IMPORTANTE: marcar tb checkbox "Atualizar inscrições existentes"
    (sem isso, só pacote muda, NÃO subscription ativa)
  → Salvar
  → User da business faz Ctrl+Shift+R → sidebar reflete
```

**Pacotes ativos descobertos em prod (2026-05-18):**
- Package #1 = "Base"
- Package #4 = "Contrato Rota Livre Semestral" → **biz=4 (ROTA LIVRE Larissa)**
- Package #11 = "Contrato JAIR UMBELINA VARGAS ME"
- Package #12 = "Contrato ROBSON CESAR GONCALVES RIBEIRO ME"
- Package #13 = "Contrato SAYURI AHAGON BAEZ EPP"
- Package #268 = "Contrato BRASARTE DIVULGAÇÕES LTDA"

### Code pattern correto em DataController (módulo nWidart)

```php
use App\Utils\ModuleUtil;

public function modifyAdminMenu(): void
{
    $module_util = new ModuleUtil();

    // Gate 1: Subscription package
    if (auth()->user()->can('superadmin')) {
        $is_enabled = $module_util->isModuleInstalled('NomeModulo');
    } else {
        $business_id = session('user.business_id');
        $is_enabled = (bool) $module_util->hasThePermissionInSubscription(
            $business_id,
            'nomemodulo_module',
            'superadmin_package'
        );
    }
    if (! $is_enabled) return;

    // Gate 2: Permission Spatie (role-based dentro do business)
    if (! auth()->user()->can('superadmin') && ! auth()->user()->can('nomemodulo.access')) {
        return;
    }

    // Gate 3: Render menu
    Menu::modify('admin-sidebar-menu', function ($menu) { /* ... */ });
}

public function superadmin_package(): array
{
    return [[
        'name'    => 'nomemodulo_module',
        'label'   => 'Módulo NomeModulo',
        'default' => false,  // pacotes novos NÃO vêm com módulo ativo por default
    ]];
}
```

### Chaves `*_module` canônicas descobertas em prod (24+)

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
| Governance ★ (PR #1079) | `governance_module` | `governance.dashboard.view` |
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

---

## CAMADA 2 — `business.enabled_modules` (Core UltimatePOS)

### Como funciona

1. Coluna **`business.enabled_modules`** (JSON array) lista keys core ativas pra cada business
2. Middleware `AdminSidebarMenu` lê `session('business.enabled_modules')` na linha 30 do boot
3. Cada item core no menu checa `in_array('expenses', $enabled_modules)` antes de renderizar

### Como editar via UI

```
User da business loga (NÃO superadmin) → /business/settings
  → Aba "Recursos do negócio" → checkboxes "Habilitar Módulos"
  → Marcar/desmarcar
  → Salvar
  → Refresh → sidebar reflete
```

### Pegadinha CRÍTICA — sem switch business

**`BusinessController::getBusinessSettings()` lê `session('user.business_id')` — só edita business DO USER LOGADO.** Não tem feature "switch business" implementada no oimpresso.

**Consequência:** Superadmin (Wagner@biz=1) NÃO consegue editar `enabled_modules` de biz=4 via `/business/settings`. A rota `/superadmin/business/{id}/edit` existe mas `BusinessController@edit` retorna view inexistente (`superadmin::edit`) → **erro 500**.

### 3 caminhos pra editar `enabled_modules` de business diferente do user logado

| Opção | Esforço | Como | Quando usar |
|---|---|---|---|
| **A. User da business edita** | 30s | Larissa loga em biz=4 → Configurações → Recursos → desmarca → salva | Default canônico. WhatsApp/email pra cliente |
| **B. Implementar tela admin** | 1-2h | Implementar `/superadmin/business/{id}/settings` (custom feature) com gate `business_settings.access` | Quando Wagner gerencia 10+ clientes piloto e A vira gargalo |
| **C. Tinker SSH update direto** | 30s | `Business::find(4)->update(['enabled_modules' => json_encode([...])])` | Emergência admin OR quando A está bloqueada (cliente offline) |

### Chaves `enabled_modules` core canônicas (13 descobertas em prod)

| Key | Label PT-BR | Aparece em sidebar como |
|---|---|---|
| `purchases` | Compras | Compras (dropdown ESTOQUE) |
| `add_sale` | Adicionar venda | Vendas |
| `pos_sale` | POS | POS (botão topo) |
| `stock_transfers` | Transferências de ações | Transferências (ESTOQUE) |
| `stock_adjustment` | Ajuste de estoque | Ajuste de estoque (ESTOQUE) |
| `expenses` | Despesas | Despesas (dropdown FINANCEIRO) |
| `account` | Conta | (interno) |
| `tables` | Mesas | Mesas (Restaurant) |
| `modifiers` | Modificadores | Modificadores (Restaurant) |
| `service_staff` | Responsável pela venda | **Pedidos** (Restaurant Order) ⚠ label menu ≠ label setting |
| `booking` | Reservas | Reservas |
| `kitchen` | Cocina | Cocina (Restaurant) |
| `subscription` | Assinatura | (toggles em outras telas) |
| `types_of_service` | Tipos de serviço | (config) |

**⚠ Pegadinha de label:** `service_staff` no `/business/settings` aparece como "**Responsável pela venda**", mas no sidebar `AdminSidebarMenu` renderiza como **"Pedidos"** via `__('restaurant.orders')`. Mesma key.

---

## Anti-pattern banido (HARDCODE)

```php
// ❌ NUNCA fazer — Wagner regra Tier 0 IRREVOGÁVEL
$business_id = (int) session('user.business_id');
if ($business_id === 4) {
    return;
}

// ❌ Exceção positiva hardcode (mesmo problema)
$piloto_rotalivre = ($business_id === 4);
if (! $piloto_rotalivre && ! auth()->user()->can('feature.access')) {
    return;
}
```

**Por que é errado:**
- Acopla comportamento ao número mágico (`4`) sem contexto histórico
- Não escala (10+ clientes piloto = N if-elif-elif maluco)
- **Wagner não consegue gerenciar via UI** (precisa abrir código + PR + deploy)
- Viola separation of concerns (configuração ≠ código)

---

## Defesas técnicas que previnem reincidência

| # | Camada | Arquivo | O que faz |
|---|---|---|---|
| 1 | Memória canônica | [`memory/proibicoes.md`](../proibicoes.md) §"Multi-tenant Tier 0" | Carrega em TODA sessão Claude via `@memory/proibicoes.md` no CLAUDE.md raiz |
| 2 | Memória detalhada | Este arquivo + entrada em `_INDEX.md` | Time MCP consulta via tools MCP (Felipe/Maiara/Eliana/Luiz) |
| 3 | **Pest GLOBAL anti-regressão** | [`tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php`](../../tests/Feature/Architecture/NoHardcodeBusinessIdInModulesTest.php) | Varre 30+ DataControllers + Middlewares em CI. 7 patterns regex banidos. Bloqueia merge |
| 4 | Pest test específico biz=4 | [`tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php`](../../tests/Feature/Sidebar/Biz4RotaLivreSidebarTest.php) | Anti-regressão histórica 5 arquivos sessão 2026-05-18 |
| 5 | Pest test caso Governance | [`Modules/Governance/Tests/Feature/GovernanceModuleSubscriptionGateTest.php`](../../Modules/Governance/Tests/Feature/GovernanceModuleSubscriptionGateTest.php) | Garante gate canônico permanece |

---

## Checklist Wagner — habilitar/desabilitar módulo pra um business (RECORRENTE)

### Caso 1: Módulo nWidart (Camada 1) — Financeiro/CRM/WooCommerce/etc

```
[ ] 1. Identificar a chave 'X_module' (ver tabela acima)
[ ] 2. /superadmin/packages → editar o pacote ativo da business alvo
[ ] 3. Marcar/desmarcar checkbox correspondente
[ ] 4. MARCAR também "Atualizar inscrições existentes" (crítico — sem isso só pacote muda)
[ ] 5. Salvar
[ ] 6. Validar: user da business faz Ctrl+Shift+R em /home
```

### Caso 2: Módulo core UltimatePOS (Camada 2) — Despesas/Pedidos/Cocina/etc

```
[ ] 1. Identificar a chave 'X' core (ver tabela acima)
[ ] 2. User da business loga (NÃO superadmin)
[ ] 3. /business/settings → aba "Recursos do negócio"
[ ] 4. Marcar/desmarcar checkbox
[ ] 5. Salvar
[ ] 6. Validar: refresh → sidebar reflete
```

### Caso 3: Wagner quer editar Camada 2 sem logar como user da business

Escolher:
- **A** (default): pedir user da business (Larissa) fazer
- **B** (longo prazo): pedir Claude implementar `/superadmin/business/{id}/settings`
- **C** (emergência): tinker SSH

### Caso 4: Módulo nWidart NÃO tem gate canônico ainda

```
[ ] 1. Auditar Modules/<Nome>/Http/Controllers/DataController.php
[ ] 2. Se não tem `hasThePermissionInSubscription`, ADICIONAR (pattern Governance PR #1079)
[ ] 3. Adicionar `superadmin_package()` retornando 'nomemodulo_module'
[ ] 4. Adicionar Pest estrutural (template: GovernanceModuleSubscriptionGateTest)
[ ] 5. PR + merge → daí Wagner consegue marcar/desmarcar via UI
```

---

## Histórico do erro arquitetural (rastreabilidade)

**2026-05-18 — sessão biz=4 ROTA LIVRE:**

Wagner pediu: *"Libere para empresa 4 o Financeiro/DRE/Fluxo/Boletos. Remova Tarefas/Governança/Despesas/Pedidos/Woocommerce"*.

Claude (erro arquitetural) interpretou como **hardcode `=== 4`** em 6 lugares (PRs #1073/#1074/#1076):
- `Modules/Financeiro/Http/Controllers/DataController.php` — exceção `! $piloto_rotalivre`
- `Modules/Governance/Http/Controllers/DataController.php` — `if (=== 4) return`
- `Modules/Woocommerce/Http/Controllers/DataController.php` — `if (=== 4) return`
- `app/Http/Middleware/HandleInertiaRequests.php` — `&& $businessId !== 4`
- `app/Http/Middleware/AdminSidebarMenu.php` — `&& $current_biz !== 4` em Expenses E Service Staff

Wagner reagiu: *"Nuca faça isso, habilitar e desabilitar é compra de pacote no modulo superadmin"*.

**PR #1077** — restaurou os 6 lugares pro pattern canônico.
**PR #1078** — elevou regra pra Tier 0 IRREVOGÁVEL em `proibicoes.md`.
**PR #1079** — adicionou gate canônico `governance_module` no Governance + Pest global anti-regressão.

**2026-05-18 — configuração executada via UI Superadmin:**

- Package #4 "Contrato Rota Livre Semestral" editado via `/superadmin/packages/4/edit`
- Marcado `financeiro_module = true` (Wagner pediu liberar)
- Desmarcado `woocommerce_module = false` (Wagner pediu esconder)
- Marcado `update_subscriptions = true` (subscription ativa de biz=4 também atualizada)
- Zero deploy code, zero PR, zero build.

---

## Refs

- `app/Utils/ModuleUtil.php:143` — `hasThePermissionInSubscription()` (canon)
- `Modules/Superadmin/Entities/Package.php` — model + relations
- `Modules/Superadmin/Entities/Subscription.php` — `active_subscription($business_id)`
- `Modules/Superadmin/Http/Controllers/PackagesController.php` — CRUD UI Package (Camada 1)
- `app/Http/Controllers/BusinessController.php:275` — `getBusinessSettings()` (Camada 2)
- `app/Http/Middleware/AdminSidebarMenu.php:30` — `session('business.enabled_modules')`
- `resources/views/business/partials/settings_modules.blade.php` — view checkbox
- ADR 0093 (multi-tenant Tier 0)
- `memory/proibicoes.md` §"Multi-tenant Tier 0 IRREVOGÁVEL" (Tier 0)
- `Modules/Auditoria/Http/Controllers/DataController.php` — template canônico Camada 1
- `Modules/Governance/Http/Controllers/DataController.php` — template canônico (PR #1079 — gate adicionado pós-revert)

## Anti-patterns

- ❌ `if ($business_id === N) return` (motivo desta regra Tier 0)
- ❌ Acoplar visibilidade de UI ao número da business em código
- ❌ Filtrar via JS no frontend (SIDEBAR_GROUPS) — backend já entrega filtrado
- ❌ Modificar `session('business.enabled_modules')` manualmente em código
- ❌ Esquecer "Atualizar inscrições existentes" no Package edit (só pacote muda, NÃO subscription ativa)
- ❌ Confundir Camada 1 (Package) com Camada 2 (enabled_modules) — chaves DIFERENTES (`X_module` vs `X`)
- ❌ Tentar `/superadmin/business/{id}/edit` via UI — método `edit()` retorna view inexistente, 500 error
