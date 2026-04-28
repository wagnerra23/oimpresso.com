---
name: multi-tenant-patterns
description: Use ao criar ou alterar Eloquent Model, Controller, Service, Job, Command ou Migration que toca dados de negócio (qualquer entidade com `business_id`). Garante que queries usam global scope, jobs em fila não perdem o tenant, e código de CLI/superadmin trata o caso cross-business explicitamente. UltimatePOS é multi-tenant por `business_id` — vazar dados entre tenants é o pior bug possível neste projeto.
---

# Multi-tenant patterns no Oimpresso ERP

## Quando ativa

Toda vez que o trabalho toca em **qualquer dado de negócio** — leia "tem coluna `business_id`". Ex.: criar nova tabela, model, query, job assíncrono, comando de console, endpoint Inertia, observer.

## Regra de ouro

`business_id` é **um global scope no Eloquent**, não um `where` no controller. O controller nunca deve precisar lembrar de filtrar tenant — ele já vem filtrado da camada de model. Filtrar manualmente é o bug, não a solução.

## Como aplicar (padrão canônico)

### 1. Schema — toda tabela de negócio tem `business_id` indexado

```php
$table->unsignedInteger('business_id')->nullable()->index();
$table->foreign('business_id')->references('id')->on('business')->onDelete('cascade');
```

`nullable` é proposital: registros "da plataforma" (vistos por superadmin, não por usuário de business específico) usam `business_id = null`. Isso é tenancy híbrida (ver ADR `arq/0001` do Copiloto).

### 2. Model — global scope no `booted()`

Referência canônica: [Modules/Copiloto/Scopes/ScopeByBusiness.php](../../Modules/Copiloto/Scopes/ScopeByBusiness.php)

```php
class MinhaEntidade extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new ScopeByBusiness);
    }
}
```

O `ScopeByBusiness` lê `session('user.business_id')`, aplica `where business_id = X` (e, pra superadmin, adiciona `OR business_id IS NULL`).

### 3. Controller — confia no scope, não duplica

```php
// ✅ Correto — scope já filtrou
$itens = MinhaEntidade::orderBy('created_at', 'desc')->get();

// ❌ Errado — duplicação que aparenta segurança mas não é
$businessId = session('user.business_id');
$itens = MinhaEntidade::where('business_id', $businessId)->get();
// (problema: se algum outro código esquecer o where, vaza. O scope existe pra ser único ponto de verdade.)
```

Ao **inserir**, sempre setar `business_id` explicitamente (scope filtra, não preenche):

```php
MinhaEntidade::create([
    'business_id' => session('user.business_id'),
    // ...
]);
```

### 4. Sair do scope deliberadamente

Quando o trabalho realmente exige cross-business (admin, relatório agregado, migration de dados):

```php
MinhaEntidade::withoutGlobalScope(ScopeByBusiness::class)->...
```

Comente **sempre por que** está desligando. Sem comentário = code review reprova.

## Pegadinhas (já queimaram)

### 4a. Jobs em fila perdem `session()`

`session('user.business_id')` retorna `null` dentro de um job assíncrono — não há request HTTP, não há sessão. Solução: passar `$businessId` no constructor do job.

```php
class ProcessarRelatorioJob implements ShouldQueue
{
    public function __construct(public int $businessId) {}

    public function handle()
    {
        // global scope vai puxar session() = null e filtrar errado.
        // Aqui você É obrigado a sair do scope e filtrar manual:
        $itens = MinhaEntidade::withoutGlobalScope(ScopeByBusiness::class)
            ->where('business_id', $this->businessId)
            ->get();
    }
}
```

### 4b. Comandos de console (Artisan) não têm sessão

Mesmo problema. Se o comando opera em um business específico, recebe `--business-id=` como flag e usa `withoutGlobalScope`. Se é cross-business (ex.: rebuild de cache global), itera por business_id.

### 4c. Observer / Event listener pode rodar fora de request

Sempre validar se há sessão; se não houver, ler tenant de uma source explícita (model relation, payload do evento).

### 4d. `whereHas` / joins pulam global scope da relação?

Não — o scope acompanha o model alvo da relação se ele tiver `booted` configurado. Mas **subqueries cruas** (`DB::table('...')`) **não** aplicam scope. Se for inevitável usar `DB::table()`, adicione `where('business_id', ...)` à mão e comente que é por isso.

### 4e. Superadmin vê demais por padrão

O `ScopeByBusiness` injeta o caso `OR business_id IS NULL` automaticamente quando o usuário tem permissão de superadmin. **Não** é "vê tudo de todos os businesses" — é "vê o próprio business + a plataforma". Pra ver cross-business você precisa `withoutGlobalScope` explícito.

## Tests obrigatórios

Toda nova entidade com tenancy precisa de **dois testes mínimos**, no estilo de [Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php](../../Modules/Financeiro/Tests/Feature/MultiTenantIsolationTest.php):

1. **Isolamento positivo:** usuário do business A faz `Model::all()` e só vê linhas do business A.
2. **Isolamento cross-tenant:** insere linha no business B, troca a sessão pra business A, faz `Model::all()`, asserta que a linha do B **não** aparece.

Pra entidades com plataforma-wide (`business_id = null`) também:

3. **Superadmin vê plataforma:** com permissão de superadmin, usuário vê linhas com `business_id = null` somadas às do próprio business.
4. **User comum NÃO vê plataforma:** sem permissão de superadmin, linhas com `business_id = null` ficam invisíveis.

## Como NÃO fazer

- ❌ Sem global scope — confiar no controller pra sempre lembrar do `where`. Frágil; um endpoint esquecido vaza tudo.
- ❌ Global scope só lendo de variável global em vez de sessão — quebra em jobs/CLI sem sinalizar.
- ❌ Aplicar `business_id` no `select` / blade view — filtragem em camada de apresentação. Errado.
- ❌ Setar `business_id` no `creating` event do model usando session — funciona em request, falha em job. Setar sempre explícito quem chama o `create`.

## Resumo prático

Antes de mergear código que toca tabela com `business_id`:

- [ ] Migration tem `business_id` indexado e FK
- [ ] Model tem `booted()` adicionando `ScopeByBusiness` (ou equivalente do módulo)
- [ ] Controller NÃO duplica `where business_id`
- [ ] Inserções setam `business_id` explicitamente
- [ ] Jobs/Commands recebem `business_id` no constructor e usam `withoutGlobalScope`
- [ ] Test de isolamento positivo + cross-tenant existe
- [ ] Toda chamada `withoutGlobalScope` tem comentário explicando por quê
