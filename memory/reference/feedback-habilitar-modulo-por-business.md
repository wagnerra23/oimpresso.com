---
name: feedback-habilitar-modulo-por-business
description: Como habilitar/desabilitar items do sidebar pra business específico (biz=N). Pattern guard `$business_id === N` no DataController OR AdminSidebarMenu — preserva multi-tenant Tier 0.
type: feedback
---

# Habilitar/desabilitar módulo (sidebar) por business

**Regra:** Pra customizar VISIBILIDADE de item no sidebar por business específico (ex: piloto cliente, plano pago, feature flag), usar guard `$business_id === N` (ou `!== N`) no DataController do módulo OU no AdminSidebarMenu core. **Não tocar** queries Eloquent — guard só esconde no menu, controllers continuam respeitando permission gates.

## Pattern canônico

### 1. **Esconder** módulo pra business específico (mais comum)

**Módulo nWidart:** `Modules/<Nome>/Http/Controllers/DataController.php` no `modifyAdminMenu()`:

```php
public function modifyAdminMenu()
{
    $business_id = (int) session('user.business_id');

    // Wagner YYYY-MM-DD: razão de negócio aqui.
    if ($business_id === N) {
        return;
    }

    // ... resto do menu render ...
}
```

**Item core UltimatePOS:** `app/Http/Middleware/AdminSidebarMenu.php`:

```php
// Reusa variável $current_biz já definida no escopo da função handle()
if (in_array('feature_module', $enabled_modules) && /* ... permissions ... */ && $current_biz !== N) {
    $menu->dropdown(/* ... */)->order(NN);
}
```

### 2. **Liberar** módulo pra business específico (exceção positiva)

Quando guard atual restringe (ex: SUPERADMIN-ONLY em dev) e queremos liberar pra piloto:

```php
$business_id = (int) session('user.business_id');
$piloto_biz = ($business_id === N);  // ex: ROTA LIVRE biz=4

if (
    ! auth()->user()->can('superadmin')
    && ! $piloto_biz
    && ! auth()->user()->can('feature.access')
) {
    return;
}
```

## Aplicações conhecidas — biz=4 ROTA LIVRE (Larissa)

Sessão 2026-05-18 — Wagner pediu sidebar customizado pra cliente piloto:

| Item | Action | Localização |
|---|---|---|
| **Financeiro** + sub-items (Fluxo/DRE/Gateway) | LIBERAR exceção biz=4 | `Modules/Financeiro/Http/Controllers/DataController.php` |
| **Tarefas** (shortcut top-bar) | ESCONDER pra biz=4 | `app/Http/Middleware/HandleInertiaRequests.php` (`sidebarShortcuts()`) |
| **Governança** dropdown | ESCONDER pra biz=4 | `Modules/Governance/Http/Controllers/DataController.php` |
| **Despesas** dropdown (Expense core) | ESCONDER pra biz=4 | `app/Http/Middleware/AdminSidebarMenu.php` |
| **Pedidos** (restaurant.orders) | ESCONDER pra biz=4 | `app/Http/Middleware/AdminSidebarMenu.php` (Service Staff menu) |
| **Woocommerce** | ESCONDER pra biz=4 | `Modules/Woocommerce/Http/Controllers/DataController.php` |

PRs: #1073 (liberação Financeiro + esconder 3) · #1074 (Pedidos + cache:clear) · #1075 (4 entradas top-level) · este PR (Woocommerce + memória canon).

## Multi-tenant Tier 0 (ADR 0093) preservado

**Crítico:** o guard SÓ esconde item no menu. **Não introduz `where('business_id', N)` hardcoded em queries** — isso seria vazamento Tier 0 (acoplaria comportamento de dados a biz específico).

Permission gates dos controllers permanecem intactos — usuário pode acessar URL diretamente se tem permission (caso superadmin). Apenas o link do menu fica oculto.

**Anti-pattern:**

```php
// ❌ NUNCA fazer
Transaction::where('business_id', 4)->where('status', '!=', 'cancelled');

// ✅ Correto (global scope cobre business_id automaticamente)
Transaction::where('status', '!=', 'cancelled');  // ScopeTenancy global filtra business_id
```

## Refator futuro: feature flag config

Hardcode `=== N` é OK pra speed mas não escala. Quando Wagner pedir liberar/esconder pra 3+ businesses, refatorar pra:

```php
$bizs_piloto = config('oimpresso.biz_piloto_financeiro', []);  // ex: [4, 12, 23]
if (! in_array($business_id, $bizs_piloto, true)) {
    return;
}
```

OU pivotar pra **subscription packages** (UltimatePOS pattern):

```php
$is_enabled = $module_util->hasThePermissionInSubscription(
    $business_id, 'financeiro_module', 'superadmin_package'
);
```

E configurar package via UI superadmin (Modules/Superadmin/PackageController) sem deploy code.

## Checklist pra criar guard novo

```
- [ ] 1. Identificar onde label aparece: DataController custom OU AdminSidebarMenu core
- [ ] 2. Adicionar guard `$business_id === N` (ESCONDER) OU exceção `! $piloto_biz` (LIBERAR)
- [ ] 3. Comentário Wagner YYYY-MM-DD + razão de negócio (rastreabilidade)
- [ ] 4. NÃO tocar queries Eloquent (Tier 0)
- [ ] 5. Pest test estrutural validando guard
- [ ] 6. Commit + push + PR + merge --admin
- [ ] 7. Quick Sync auto-deploy (com cache:clear desde PR #1074)
- [ ] 8. Wagner valida visualmente via Ctrl+Shift+R em biz alvo
```

## Anti-patterns

- ❌ Filtrar via JS no Sidebar.tsx (lookup `business_id`) — backend não envia menu já filtrado, frontend mostra TUDO
- ❌ `Auth::user()->business_id` em vez de `session('user.business_id')` — pode divergir se switch active business (UltimatePOS)
- ❌ Esquecer `(int)` cast no business_id (vem string da session)
- ❌ Hardcode literal sem comentário explicativo (próxima sessão não entende)
- ❌ Esconder no menu mas esquecer permission gate no controller (URL direta acessa)

## Histórico

- **2026-05-18** — instalado após pedidos Wagner pra customizar sidebar biz=4 (ROTA LIVRE Larissa, cliente piloto). Pattern aplicado em 6 items diferentes (Financeiro / Tarefas / Governança / Despesas / Pedidos / Woocommerce) com diferentes guard locations.
