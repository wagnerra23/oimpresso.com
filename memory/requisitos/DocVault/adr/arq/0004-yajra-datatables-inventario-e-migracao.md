# ADR ARQ-0004 (DocVault) · `yajra/laravel-datatables-oracle` — inventário e migração

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Desbloqueia**: ADR arq/0002 Fase 2 (P0 blocker)

## Contexto

`yajra/laravel-datatables-oracle v9.21.2` exige `illuminate/* ^5-9`. v10 do pacote existe pra Laravel 10+, mas mudou API em alguns pontos.

Vantagem do nosso caso: **escopo contido**. Busca por `DataTables::` no projeto retornou **apenas 10 usos em 6 arquivos reais** (2 arquivos extras são config/aliases).

## Inventário (2026-04-22)

Comando: `grep -rn "DataTables::" --include="*.php" .`

| # | Arquivo | Qtd usos | Status React | Ação |
|---|---|---|---|---|
| 1 | `app/Http/Controllers/RoleController.php` | 1 | Blade legado | **Migrar pra React** OU atualizar yajra v10 |
| 2 | `app/Http/Controllers/AccountController.php` | 3 | Blade legado | **Migrar pra React** (Accounting já em pasta) |
| 3 | `app/Http/Controllers/AccountReportsController.php` | 1 | Blade legado | **Migrar pra React** |
| 4 | `Modules/Essentials/Http/Controllers/DocumentController.php` | 1 | Blade — Documents tela React existe | Remover DataTables (tela já tem tabela React) |
| 5 | `Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController.php` | 1 | Blade | Manter ou migrar |
| 6 | `Modules/Repair/Http/Controllers/JobSheetController.php` | 1 | Blade | Migrar (Repair já em formato pasta) |

**Arquivos de alias (não contam):**
- `config/app.php` — registro do Facade
- `Modules/Chat/chat/config/app.php` — submódulo chat legacy

## Decisão

Abordagem **dual track**:

### Track 1 — Migrar 4 usos pra React (preferencial)

Substituir `DataTables::of($query)->make(true)` pelo padrão Inertia+React:

```php
// Antes (Blade + DataTables AJAX)
public function index(Request $request)
{
    if ($request->ajax()) {
        return DataTables::of($this->query())->make(true);
    }
    return view('roles.index');
}

// Depois (Inertia + paginator React)
public function index(Request $request): Response
{
    $roles = Role::query()
        ->where('business_id', session('business.id'))
        ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"))
        ->orderBy($request->sort ?? 'name', $request->dir ?? 'asc')
        ->paginate(25)
        ->withQueryString();

    return Inertia::render('Roles/Index', [
        'roles'   => $roles,
        'filtros' => $request->only(['search', 'sort', 'dir']),
    ]);
}
```

Pages React usam tabela HTML + Tailwind (padrão já estabelecido em `Ponto/*/Index.tsx`).

**Candidatos Track 1 (4):**
- `RoleController` → `/roles` React
- `AccountController` (3 métodos) → `Accounting/Index|Show|Report` React
- `AccountReportsController` → `Accounting/Reports/Index` React
- `Essentials DocumentController` → já tem `/essentials/document` React, só remover o método DataTables do controller (inutilizado)

### Track 2 — Atualizar yajra pra v10 nas 2 telas onde vale a pena manter Blade (fallback)

`SuperadminSubscriptionsController` e `Repair JobSheetController` — baixo tráfego, pouco impacto. Bumpar yajra e testar.

### Ordem de execução

1. **Sessão N** — Eliminar o uso em `Essentials DocumentController` (só deletar método, tela já é React).
2. **Sessão N+1** — Migrar `AccountController` + `AccountReportsController` pra React (3 telas do Accounting).
3. **Sessão N+2** — Migrar `RoleController` pra React.
4. **Sessão N+3** — Decidir: migrar Superadmin/Subscriptions + Repair/JobSheet OU atualizar yajra v10 só pra elas.
5. **Sessão N+4** — Se 100% migrado: remover yajra. Se não: bumpar pra v10.

## Consequências

**Positivas:**
- Reduz dependência transitiva de `illuminate/*` antigo.
- Telas Blade legadas migram pro React — consistência UX.
- Destrava Laravel 10 (P0 resolvido).

**Negativas:**
- Track 1 gasta ~4 sessões migrando telas.
- Bulk-edit user com filtros avançados precisa reimplementar (DataTables fazia tudo via JSON).

## Alternativas consideradas

- **Só bumpar yajra v10**: viável mas mantém 6 telas Blade envelhecendo. Dívida rola pra frente.
- **TanStack Table**: overkill pra casos simples; descartado em _DesignSystem SPEC.
- **AG Grid / Handsontable**: prescritivo + licença.

## Sinais de conclusão (ADR vira done)

- [ ] 4 arquivos Track 1 migrados pra Inertia
- [ ] Decisão Track 2: manter 2 em Blade (com yajra v10) ou migrar também
- [ ] `composer why yajra/laravel-datatables-oracle` retorna vazio OU yajra está em v10
- [ ] `php artisan docvault:audit-module --all` ≥ baseline
