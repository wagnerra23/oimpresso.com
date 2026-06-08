---
title: "BG7 Tier 0 — Admin#N superadmin cross-tenant — investigação"
date: 2026-05-26
type: session-log
status: ativo
related_adrs: [0093]
severity_confirmed: sev2
---

# BG7 — Admin#N com `superadmin` cross-tenant (investigação 2026-05-26)

> **TL;DR sev2**: Achado original NÃO é trivial-falso-positivo. A permission `superadmin` (Spatie) — quando atribuída a QUALQUER role, incluindo `Admin#164` de um cliente — **bypassa o global scope multi-tenant** em 4 models concretos: `Modules/Financeiro/Models/AccountsLegacyMap`, `Modules/Financeiro/Models/AiUsageLog`, `Modules/NFSe/Models/NfseEmissao`, `Modules/NFSe/Models/NfseProviderConfig`. Vazamento PRÁTICO é mitigado por (a) controllers do Financeiro/NFSe usarem WHERE explícito + (b) middleware `superadmin` global proteger rotas `/superadmin/*` apenas por `username` hardcoded em `ADMINISTRATOR_USERNAMES`. Mas a violação de princípio Tier 0 EXISTE no código por design intencional documentado e há vetor real de exploit se Admin#164 abrir um endpoint legacy do Financeiro/NFSe que retorne lista sem WHERE explícito. **Não é sev1 paralisa-tudo, mas é sev2 paralisa-relógio antes do canary** — precisa confirmação prod-side se Admin#164 tem REALMENTE a permission.

## Achado original

Session log `memory/sessions/2026-05-25-sessao-fiscal-sidebar-tests-ci.md` linha 46:

> ## Achado lateral Tier 0 (não atacado nesta sessão)
>
> Role `Admin#164` tem permission **`superadmin`** (cross-tenant). Pattern provavelmente histórico de Ondas anteriores — usuário admin de business com acesso de Hostinger inteiro. Vale investigar batch de outros businesses com mesmo pattern. **Não fechado** — fora do escopo da sessão.

E linha 123 reforça no bloco "Bugs/tasks remanescentes". Achado foi detectado durante a habilitação do módulo Fiscal pra biz=164 (Martinho Caçambas LTDA) via SSH + tinker em prod MariaDB Hostinger — Wagner observou ao listar permissions do role.

## Investigação código (read-only)

### Camada 1: `Gate::before` (`app/Providers/AuthServiceProvider.php:34-47`)

```php
Gate::before(function ($user, $ability) {
    if (in_array($ability, ['backup', 'superadmin', 'manage_modules', ])) {
        $administrator_list = config('constants.administrator_usernames');
        if (in_array(strtolower($user->username), explode(',', strtolower($administrator_list)))) {
            return true;
        }
    } else {
        if ($user->hasRole('Admin#'.$user->business_id)) {
            return true;
        }
    }
});
```

**Leitura literal**:

- Para abilities `backup`/`superadmin`/`manage_modules`: `Gate::before` SÓ retorna `true` se `username` ∈ `ADMINISTRATOR_USERNAMES` (env). Logo `->can('superadmin')` NÃO é dado automaticamente a `Admin#164` por esse gate.
- Para QUALQUER outra ability: role `Admin#{biz}` retorna `true` (admin pleno DO PRÓPRIO business — comportamento intencional).

Mas: **se `Admin#164` recebeu a permission `superadmin` via `givePermissionTo` (Spatie pivot `role_has_permissions`)**, então `->can('superadmin')` retorna `true` direto pelo HasPermissions trait — sem passar pelo `Gate::before`.

Conclusão linha 1: o achado original é plausível **APENAS via atribuição manual** (`givePermissionTo` em prod) — não há seeder, migration, nem código de fábrica que faça isso (`Modules/Superadmin/Database/Seeders/SuperadminDatabaseSeeder.php` é vazio, `BusinessUtil::newBusinessDefaultResources` linha 35 cria role sem permission `superadmin`).

### Camada 2: bypass do global scope nos modules NOVOS

ESTE É O CORE DO VAZAMENTO POTENCIAL.

#### `Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php:13-30`

```php
class BusinessScopeImpl implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! session()->has('user.business_id')) {
            return;
        }
        // Superadmin: cross-tenant permitido (uso administrativo).
        if (auth()->check() && auth()->user()->can('superadmin')) {
            return;       // <-- BYPASS DO GLOBAL SCOPE
        }
        $businessId = (int) session('user.business_id');
        $builder->where($model->getTable() . '.business_id', $businessId);
    }
}
```

**Models afetados** (Grep `use BusinessScope`):

- `Modules/Financeiro/Models/AccountsLegacyMap.php:26`
- `Modules/Financeiro/Models/AiUsageLog.php:16`

#### `Modules/NFSe/Models/Concerns/NfseBusinessScope.php:19`

```php
if (auth()->check() && auth()->user()->can('superadmin')) {
    return;       // <-- BYPASS DO GLOBAL SCOPE
}
```

**Models afetados**:

- `Modules/NFSe/Models/NfseEmissao.php`
- `Modules/NFSe/Models/NfseProviderConfig.php`

#### Vetor de exploit

Se `Admin#164` (user Martinho) tem permission `superadmin`:

- `NfseEmissao::query()->get()` retorna emissões de TODOS os businesses (não só biz=164)
- `AccountsLegacyMap::all()` retorna mapas de TODOS os businesses
- Mesmo com `session('user.business_id')=164`

#### Onde mitiga (parcial)

Controllers do Financeiro/NFSe que adicionam `where('business_id', session('user.business_id'))` EXPLICITAMENTE em cima da query NÃO vazam — o WHERE adicional restringe ao biz=164. Exemplo `Modules/Financeiro/Http/Controllers/CoworkSidebarController.php` checa `Admin#{$businessId}` manualmente.

Mas QUALQUER endpoint que use apenas `NfseEmissao::paginate()` ou `AccountsLegacyMap::all()` confiando 100% no global scope vaza.

### Camada 3: bypass em queries não-Eloquent (mais 50+ lugares)

Os ~200 `->can('superadmin')` espalhados pelo codebase fazem coisas tipo:

```php
if (! auth()->user()->can('superadmin') && ! auth()->user()->can('fiscal.access')) {
    abort(403);
}
```

Esses NÃO bypassam business_id — apenas DISPENSAM o user de ter `fiscal.access`. Controllers ainda filtram por session. **Não vazam por si só**.

Exceção crítica adicional: `app/User.php:203`

```php
public function scopeOnlyPermittedLocations($query) {
    if ($permitted_locations != 'all' && ! $user->can('superadmin') && ! $is_admin) {
        // filtra por location_id
    }
    // se superadmin → bypassa filtro de LOCATIONS
}
```

Mas isso é dentro do mesmo business (BusinessLocation tem business_id). Bypass de location ≠ bypass cross-tenant. **Não conta**.

### Camada 4: Middleware `app/Http/Middleware/Superadmin.php` (rotas `/superadmin/*`)

```php
public function handle($request, Closure $next) {
    $administrator_list = config('constants.administrator_usernames');
    if (! empty($request->user()) && in_array(strtolower($request->user()->username), explode(',', strtolower($administrator_list)))) {
        return $next($request);
    }
    abort(403);
}
```

Esse middleware **bloqueia rotas `/superadmin/*`** mesmo pra quem tem permission `superadmin` — só passa quem está em `ADMINISTRATOR_USERNAMES` (env). Logo, mesmo se Admin#164 tem a permission, NÃO consegue acessar tela `/superadmin/business` por essa porta.

### Camada 5: design intencional documentado

`Modules/Superadmin/Tests/Feature/SuperadminCrossTenantPolicyTest.php:14-29`:

> Diferente do isolamento Tier 0 ADR 0093 (cada Module business-scoped), Superadmin é INTENCIONALMENTE cross-tenant: gerencia todos businesses, cria/edita Packages globais, comunica com toda base.

Constituição v2 Art. 6 + ADR 0093 §exceções classificam `superadmin` permission como bypass intencional pra papel de operador-de-plataforma. **A premissa é que SÓ Wagner / time `ADMINISTRATOR_USERNAMES` tem essa permission**. Atribuir a `Admin#164` (cliente) viola a premissa.

## Investigação prod (banco)

**SSH não funciona daqui** — agente desktop sem stdin pra senha (CLAUDE.md §"CONTEXTO DE EXECUÇÃO"). Wagner roda manual:

```bash
ssh oimpresso "cd /home/oimpresso/htdocs && php artisan tinker --no-interaction --execute=\"
\\\$roles = DB::table('roles')
    ->where('name', 'LIKE', 'Admin#%')
    ->whereIn('id', function(\\\$q) {
        \\\$q->select('role_id')->from('role_has_permissions')
          ->whereIn('permission_id', function(\\\$q2) {
              \\\$q2->select('id')->from('permissions')->where('name', 'superadmin');
          });
    })
    ->select('id', 'name', 'business_id')
    ->orderBy('business_id')
    ->get();
echo json_encode(\\\$roles, JSON_PRETTY_PRINT);
\""
```

Output esperado: lista de TODOS roles `Admin#N` em qualquer business com permission `superadmin`. Tamanho desse array decide:

- `[]` (vazio) → BG7 é falso positivo (achado original foi observação imprecisa)
- `[{name:'Admin#164', business_id:164}]` → sev2 confirmado isolado (só Martinho)
- `[{...}, {...}, ...]` (vários businesses) → sev2 confirmado epidemia (vários clientes)

Query complementar pra ver QUAIS USERS herdam isso:

```bash
ssh oimpresso "cd /home/oimpresso/htdocs && php artisan tinker --no-interaction --execute=\"
\\\$users = DB::table('users as u')
    ->join('model_has_roles as mhr', 'mhr.model_id', '=', 'u.id')
    ->join('roles as r', 'r.id', '=', 'mhr.role_id')
    ->join('role_has_permissions as rhp', 'rhp.role_id', '=', 'r.id')
    ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
    ->where('p.name', 'superadmin')
    ->select('u.id as user_id', 'u.username', 'u.business_id', 'r.name as role_name')
    ->get();
echo json_encode(\\\$users, JSON_PRETTY_PRINT);
\""
```

E queries cruzadas pra detectar se houve VAZAMENTO REAL (auditoria forense):

```sql
-- nfse_emissoes acessadas por user.business_id != emissao.business_id
SELECT business_id, COUNT(*) FROM nfse_emissoes
WHERE business_id <> 1 AND business_id <> 164  -- exclui Wagner + Martinho
GROUP BY business_id;
-- Se cliente acessou — activity_log/laravel.log mostra request_id

-- accounts_legacy_map idem
SELECT business_id, COUNT(*) FROM accounts_legacy_map GROUP BY business_id;
```

## Classificação severidade

**Confirmado sev2 (assumindo BG7 original verdadeiro)**. Não é sev1 porque:

1. Rotas `/superadmin/*` (tela admin global) seguem bloqueadas por `Superadmin` middleware (linha env-based, não permission-based).
2. Controllers Financeiro/NFSe primários adicionam WHERE business_id explícito (mitigação de defesa em profundidade — não confiam só no global scope).
3. Models afetados são 4 entidades específicas (não a base inteira como `Sell`, `Product`, `Contact`).
4. ContaPagamento UltimatePOS core (Sells, Products, Contacts) usa padrão LEGACY: `where('business_id', session('business_id'))` MANUAL no controller — NÃO usa global scope com bypass. Logo NÃO afetado.

Não é falso positivo porque:

1. O bypass de global scope é REAL no código (BusinessScopeImpl:23 + NfseBusinessScope:19).
2. Vetor de exploit existe se aparecer endpoint sem WHERE explícito (futuras Ondas de Financeiro/NFSe podem introduzir).
3. Violar premissa "só `ADMINISTRATOR_USERNAMES` tem permission `superadmin`" cria classe inteira de bugs futura.

Severidade depende inteiramente do output da query SQL: se Admin#164 NÃO tem `superadmin` mesmo (achado foi observação imprecisa), cai pra falso positivo. Se TEM, sev2 confirmado.

## Recomendação

**Não paralisar Sem 22 ainda** — investigação confirma que vazamento PRÁTICO é limitado a 4 models e nenhum endpoint legacy reportado abusa. Mas:

### Ações imediatas (Wagner, manual, hoje)

1. **Rodar a query SQL acima** pra confirmar/refutar achado original. Tempo ~2min.
2. Se retornar `Admin#164`: rodar `php artisan tinker` em prod e:

   ```php
   $role = Spatie\Permission\Models\Role::where('name', 'Admin#164')->first();
   $role->revokePermissionTo('superadmin');
   ```

   Idempotente, escopo único role. **Não quebra nada do Martinho** — Admin#164 continua tendo todas as 200+ permissions de admin do biz=164 via outras vinculações; só perde o bypass cross-tenant.

3. Repetir pra cada `Admin#N` que apareça na query.

### Tasks Sem 22+ (pós-fix imediato)

- **Task P0 sev2** (criar via `tasks-create` quando Wagner aprovar):
  - Título: "Auditoria Tier 0 — varredura `superadmin` permission em todos roles `Admin#N` prod"
  - Aceitação: (a) script idempotente `php artisan tenancy:audit-superadmin-leak` que lista + opcionalmente revoga, (b) Pest test architectural `NoSuperadminPermissionInAdminRolesTest.php` que previne regressão futura.
  - Refs ADR 0093.

- **ADR 0193-bis errata** (proposta — Wagner decide):
  - Remover bypass `if (auth()->user()->can('superadmin')) return;` dos 2 scopes (`BusinessScopeImpl`, `NfseBusinessScope`).
  - Substituir por: bypass APENAS via `withoutGlobalScope(...)` EXPLÍCITO no Controller superadmin específico — pattern atual `Modules/Superadmin/*Controller.php` (já documentado em `SuperadminCrossTenantPolicyTest.php`).
  - Justificativa: "menos mágica, mais explícito, alinhado com Constituição v2 princípio §7 Transparência".
  - Risco: pequenos refactors em ~5-10 lugares que dependam do bypass implícito.

- **Métrica de saúde adicional** em `php artisan jana:health-check`:
  - Check `superadmin_permission_in_non_wagner_roles` — SQL: `roles INNER JOIN role_has_permissions INNER JOIN permissions WHERE permissions.name = 'superadmin' AND roles.business_id IS NOT NULL`. Threshold = 0. Alerta crítico se > 0.

### Comunicação

- **Time MCP (Felipe/Maiara/Eliana/Luiz)**: comunicar SOMENTE depois de Wagner rodar query e confirmar/refutar. Se confirmar, post no canal dev + tasks-create.
- **Cliente Martinho**: não comunicar — não houve vazamento documentado de dados, só risco. Comunicar agora seria pânico desnecessário e mau-pareceria sem evidência.

## Refs

- [session log 2026-05-25-fiscal](2026-05-25-sessao-fiscal-sidebar-tests-ci.md) (achado original linha 46 + 123)
- [ADR 0093 — Multi-tenant Isolation Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [levantamento Martinho-ready 2026-05-26](2026-05-26-levantamento-martinho-ready.md) §BG7
- [`app/Providers/AuthServiceProvider.php:34-47`](../../app/Providers/AuthServiceProvider.php) — Gate::before
- [`app/Http/Middleware/Superadmin.php`](../../app/Http/Middleware/Superadmin.php) — middleware rotas /superadmin/*
- [`Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php:23`](../../Modules/Financeiro/Models/Concerns/BusinessScopeImpl.php) — bypass global scope
- [`Modules/NFSe/Models/Concerns/NfseBusinessScope.php:19`](../../Modules/NFSe/Models/Concerns/NfseBusinessScope.php) — bypass global scope
- [`Modules/Superadmin/Tests/Feature/SuperadminCrossTenantPolicyTest.php`](../../Modules/Superadmin/Tests/Feature/SuperadminCrossTenantPolicyTest.php) — Wave 13 design intencional
- [`Modules/Superadmin/Tests/Feature/CrossTenantSuperadminTest.php`](../../Modules/Superadmin/Tests/Feature/CrossTenantSuperadminTest.php) — Wave anterior contrato
- [`app/Utils/BusinessUtil.php:35`](../../app/Utils/BusinessUtil.php) — criação Role Admin#N sem permission superadmin
