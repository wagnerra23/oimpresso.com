# Especificação funcional

## 3. User stories

> Convenção do ID: `US-PONT-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-PONT-001 · Listar Aprovacao

> **Área:** Aprovacao  
> **Rota:** `GET /aprovacoes`  
> **Controller/ação:** `AprovacaoController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Aprovacao  
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

### US-PONT-002 · Listar Banco Horas

> **Área:** Banco Horas  
> **Rota:** `GET /banco-horas`  
> **Controller/ação:** `BancoHorasController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Banco Horas  
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

### US-PONT-003 · Ver detalhe de Banco Horas

> **Área:** Banco Horas  
> **Rota:** `GET /banco-horas/{colaborador}`  
> **Controller/ação:** `BancoHorasController@show`

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

### US-PONT-004 · Listar Colaborador

> **Área:** Colaborador  
> **Rota:** `GET /colaboradores`  
> **Controller/ação:** `ColaboradorController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Colaborador  
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

### US-PONT-005 · Listar Configuracao

> **Área:** Configuracao  
> **Rota:** `GET /configuracoes`  
> **Controller/ação:** `ConfiguracaoController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Configuracao  
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

### US-PONT-006 · Listar Core

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

### US-PONT-007 · Listar Espelho

> **Área:** Espelho  
> **Rota:** `GET /espelho`  
> **Controller/ação:** `EspelhoController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Espelho  
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

### US-PONT-008 · Ver detalhe de Espelho

> **Área:** Espelho  
> **Rota:** `GET /espelho/{colaborador}`  
> **Controller/ação:** `EspelhoController@show`

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

### US-PONT-009 · Listar Importacao

> **Área:** Importacao  
> **Rota:** `GET /importacoes`  
> **Controller/ação:** `ImportacaoController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Importacao  
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

### US-PONT-010 · Criar Importacao

> **Área:** Importacao  
> **Rota:** `POST /importacoes`  
> **Controller/ação:** `ImportacaoController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Importacao  
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

### US-PONT-011 · Ver detalhe de Importacao

> **Área:** Importacao  
> **Rota:** `GET /importacoes/{id}`  
> **Controller/ação:** `ImportacaoController@show`

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

### US-PONT-012 · Listar Relatorio

> **Área:** Relatorio  
> **Rota:** `GET /relatorios`  
> **Controller/ação:** `RelatorioController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Relatorio  
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

### R-PONT-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo PontoWr2
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** `Modules/PontoWr2/Tests/Feature/EspelhoShowTest::test_business_isolation`

### R-PONT-002 · Autorização Spatie `ponto.access`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.access`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.access')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PONT-003 · Autorização Spatie `ponto.colaboradores.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.colaboradores.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.colaboradores.manage')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PONT-004 · Autorização Spatie `ponto.aprovacoes.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.aprovacoes.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.aprovacoes.manage')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PONT-005 · Autorização Spatie `ponto.relatorios.view`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.relatorios.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.relatorios.view')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PONT-006 · Autorização Spatie `ponto.configuracoes.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.configuracoes.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.configuracoes.manage')`  
**Testado em:** _[TODO — apontar caminho do teste]_
