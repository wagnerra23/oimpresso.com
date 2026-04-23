# Especificação funcional

## 3. User stories

> Convenção do ID: `US-ACCO-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-ACCO-001 · Listar Budget

> **Área:** Budget  
> **Rota:** `GET /`  
> **Controller/ação:** `BudgetController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Budget  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-002 · Listar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET /`  
> **Controller/ação:** `ChartOfAccountController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Chart Of Account  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-003 · Criar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `POST store`  
> **Controller/ação:** `ChartOfAccountController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Chart Of Account  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-004 · Ver detalhe de Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `ChartOfAccountController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-005 · Listar Core

> **Área:** Core  
> **Rota:** `GET /`  
> **Controller/ação:** `DashboardController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Core  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-006 · Listar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET /`  
> **Controller/ação:** `JournalEntryController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Journal Entry  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-007 · Criar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `POST store`  
> **Controller/ação:** `JournalEntryController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Journal Entry  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-008 · Ver detalhe de Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `JournalEntryController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-009 · Listar Reconcile

> **Área:** Reconcile  
> **Rota:** `GET /`  
> **Controller/ação:** `ReconcileController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Reconcile  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-010 · Listar Report

> **Área:** Report  
> **Rota:** `GET accounting`  
> **Controller/ação:** `ReportController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Report  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ACCO-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Accounting
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-002 · Autorização Spatie `accounting.chart_of_accounts.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.index')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-003 · Autorização Spatie `accounting.chart_of_accounts.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.create')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-004 · Autorização Spatie `accounting.chart_of_accounts.edit`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.edit`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.edit')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-005 · Autorização Spatie `accounting.chart_of_accounts.destroy`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.destroy`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.destroy')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-006 · Autorização Spatie `accounting.journal_entries.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.index')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-007 · Autorização Spatie `accounting.journal_entries.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.create')`  
**Testado em:** _[TODO — apontar caminho do teste]_
