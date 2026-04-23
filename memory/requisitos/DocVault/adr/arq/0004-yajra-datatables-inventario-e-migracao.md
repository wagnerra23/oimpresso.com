# ADR ARQ-0004 (DocVault) · `yajra/laravel-datatables-oracle` — inventário e migração

- **Status**: accepted (revisado 2026-04-23 — yajra NÃO é blocker)
- **Data**: 2026-04-22 · **Revisado**: 2026-04-23
- **Decisores**: Wagner, Claude
- **Categoria**: arq
- **Desbloqueia**: ADR arq/0002 Fase 2 (**ajuste: não é P0, é P1 simples**)

## 🔄 REVISÃO 2026-04-23 — Yajra não é blocker

Wagner apontou: pesquisei versões yajra e achei até **v13**. Verificação via `composer show -a yajra/laravel-datatables-oracle`:

| Versão yajra | PHP | Laravel |
|---|---|---|
| v9.x | ^8.0 | ^7-9 |
| **v10.11.4** | **^8.0.2** | **^9 OR ^10** ← duplo compat |
| v11.1.6 | ^8.2 | ^11 |
| v12.7.0 | ^8.2 | ^12 |
| v13.0 | ^8.3 | ^13 |

**Conclusão**: yajra mantém major sincronizado com major do Laravel. **v10.11 aceita Laravel 9 E Laravel 10 simultaneamente** — pode bumpar hoje, sem esperar upgrade framework. Na hora do upgrade Laravel N, é só bumpar yajra vN junto.

**Yajra sai do status de P0 blocker do ADR arq/0002** — é trivialmente upgradável.

### Estratégia revisada

**Track A (executar agora — trivial):**
- Bumpar `yajra/laravel-datatables-oracle: ^10.11` → aceita L9 atual E L10 futuro
- Testar que as 7 telas que usam DataTables continuam funcionando
- Commit como melhoria incremental

**Track B (continua válido, mas opcional):**
- Migrar 4 telas DataTables pra React+TanStack não é mais pré-requisito do upgrade Laravel
- Vira modernização progressiva: cada tela migrada remove dívida técnica (140kB jQuery, XSS risk, zero TypeScript)
- Ordem e timing fica à critério de Wagner — sem pressão

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

## Análise técnica detalhada (2026-04-22)

### O que DataTables faz (2 camadas)

1. **`yajra/laravel-datatables-oracle` (PHP)**: wrapper que serializa Eloquent pro JSON que DataTables.net espera.
2. **`DataTables.net` (jQuery)**: lib JS que renderiza tabela + faz AJAX em cada ação do user.

### Features usadas no nosso projeto (7 usos)

| Feature | Substituto em Inertia+React |
|---|---|
| Paginação server-side | Inertia paginator + `withQueryString()` ✅ (já usamos) |
| Sort por coluna | `?sort=X&dir=asc` ✅ |
| Search global | `?search=X` ✅ |
| Filtros avançados | Spatie Query Builder ou query params |
| Colunas custom (ações) | JSX (mais seguro que closures PHP serializando HTML) |
| Export CSV/Excel | `maatwebsite/excel` (já temos) |
| Responsividade | `overflow-x-auto` Tailwind |

### Alternativas avaliadas

**Tier 1 — Sem lib externa (recomendado 90% dos casos):**

- **HTML `<table>` + Tailwind**: padrão PontoWr2. 0kB extra.
- **shadcn/ui `<Table>`**: primitive copy-paste. Acessível (Radix).

**Tier 2 — Headless libs (casos > 1k linhas ou filtros complexos):**

| Lib | Bundle gzip | Licença | Comentário |
|---|---|---|---|
| **TanStack Table v8** | 15kB | MIT | **Escolha quando precisar avançado**. Headless, BYO-UI Tailwind |
| Material React Table | 80kB | MIT | Material UI — contradiz ADR UI-0001 (_DesignSystem, Tailwind+shadcn) |
| AG Grid Community | 200kB | MIT (features avançadas = Enterprise pago) | Muito pesado |
| react-data-grid | 40kB | MIT | Foco em edição inline (nosso caso não pede) |

**Tier 3 — Backend helper:**
- **Spatie Query Builder**: `?filter[name]=X&sort=-created_at` vira Eloquent query. ~100 linhas config.

### Recomendação final

**Substituir yajra+DataTables.net pelo combo:**

**90% dos casos** (Roles, Superadmin Subscriptions, Repair JobSheet):
```
Inertia paginator + Tailwind <table> + withQueryString()
```

**10% dos casos** (Account com filtros complexos, Reports com export):
```
TanStack Table v8 + Spatie Query Builder + maatwebsite/excel (já temos)
```

### Ganho mensurável da substituição

| Aspecto | yajra + DataTables.net | Inertia+React+Tailwind |
|---|---|---|
| Deps | 2 pacotes (PHP + JS) | 0 extras |
| Bundle JS | **~140kB** (jquery + datatables.js) | ~0kB (tabela HTML) |
| Styling | CSS externo + custom | Tailwind direto |
| Colunas custom | PHP closures → HTML string (XSS-prone) | JSX tipado |
| Acessibilidade | Manual | Radix built-in |
| TypeScript end-to-end | ❌ | ✅ |
| jQuery required | ✅ | ❌ (**remove 90kB**) |
| Alinhado com ADR UI-0001 | ❌ | ✅ |

**Substituição elimina ~140kB de JS + dependência jQuery + risco XSS em HTML serializado**. Zero perda funcional.

## Alternativas consideradas

- **Só bumpar yajra v10**: viável mas mantém 6 telas Blade envelhecendo e 140kB de JS legado.
- **TanStack Table sempre**: overkill pra casos simples (5-500 linhas) — só quando features justificarem.
- **AG Grid / Handsontable / Material RT**: contradizem ADR UI-0001 (_DesignSystem Tailwind+shadcn).
- **Manter status quo**: contradiz ADR arq/0001 (upgrade Laravel 10 bloqueia yajra v9).

## Sinais de conclusão (ADR vira done)

- [ ] 4 arquivos Track 1 migrados pra Inertia
- [ ] Decisão Track 2: manter 2 em Blade (com yajra v10) ou migrar também
- [ ] `composer why yajra/laravel-datatables-oracle` retorna vazio OU yajra está em v10
- [ ] `php artisan docvault:audit-module --all` ≥ baseline
