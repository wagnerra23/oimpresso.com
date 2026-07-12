---
module: PontoWr2
version: "1.0"
last_updated: "2026-06-13"
owners: [W]
status: historical
---

> ⚰️ **HISTORICAL — pasta renomeada pra `Modules/Ponto` (KL-E2 · decisão E1 2026-06-15).** As `US-PONT-NNN` aqui **não são contrato vivo** — o SPEC ativo usa a convenção `US-PONTO-NNN`. Verdade viva → [`requisitos/Ponto/`](../Ponto/BRIEFING.md).

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

**Implementado em:** `resources/js/Pages/Ponto/Aprovacoes/Index.tsx` · `Modules/Ponto/Http/Controllers/AprovacaoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-001) · `Modules/Ponto/Tests/Feature/AprovacaoTest.php` (covers US-PONT-001)

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

**Implementado em:** `resources/js/Pages/Ponto/BancoHoras/Index.tsx` · `Modules/Ponto/Http/Controllers/BancoHorasController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-002) · `Modules/Ponto/Tests/Feature/BancoHorasTest.php` (covers US-PONT-002)

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

**Implementado em:** `resources/js/Pages/Ponto/BancoHoras/Show.tsx` · `Modules/Ponto/Http/Controllers/BancoHorasController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/BancoHorasTest.php` (covers US-PONT-003)

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

**Implementado em:** `resources/js/Pages/Ponto/Colaboradores/Index.tsx` · `Modules/Ponto/Http/Controllers/ColaboradorController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-004)

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

**Implementado em:** `resources/js/Pages/Ponto/Configuracoes/Index.tsx` · `Modules/Ponto/Http/Controllers/ConfiguracaoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-005)

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

**Implementado em:** `resources/js/Pages/Ponto/Dashboard/Index.tsx` · `Modules/Ponto/Http/Controllers/DashboardController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-006) · `Modules/Ponto/Tests/Feature/DashboardTest.php` (covers US-PONT-006)

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

**Implementado em:** `resources/js/Pages/Ponto/Espelho/Index.tsx` · `Modules/Ponto/Http/Controllers/EspelhoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-007)

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

**Implementado em:** `resources/js/Pages/Ponto/Espelho/Show.tsx` · `Modules/Ponto/Http/Controllers/EspelhoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/EspelhoTest.php` (covers US-PONT-008)

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

**Implementado em:** `resources/js/Pages/Ponto/Importacoes/Index.tsx` · `Modules/Ponto/Http/Controllers/ImportacaoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-009)

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

**Implementado em:** `resources/js/Pages/Ponto/Importacoes/Create.tsx` · `Modules/Ponto/Http/Controllers/ImportacaoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-010)

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

**Implementado em:** `resources/js/Pages/Ponto/Importacoes/Show.tsx` · `Modules/Ponto/Http/Controllers/ImportacaoController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/ImportacaoTest.php` (covers US-PONT-011)

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

**Implementado em:** `resources/js/Pages/Ponto/Relatorios/Index.tsx` · `Modules/Ponto/Http/Controllers/RelatorioController.php` · verificado@138246f (2026-07-01)

**Testado em:** `Modules/Ponto/Tests/Feature/TelasNavegacaoTest.php` (covers US-PONT-012)

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
**Testado em:** `Modules/Ponto/Tests/Feature/MultiTenantIsolationTest` (teste real — valida 10 rotas com scope + isolamento cross-business + session)

### R-PONT-002 · Autorização Spatie `ponto.access`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.access`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.access')`  
**Testado em:** `Modules/Ponto/Tests/Feature/SpatiePermissionsTest` (real — 10 testes, 15 assertions, valida ambas direções: sem permissão bloqueia, com permissão passa)

### R-PONT-003 · Autorização Spatie `ponto.colaboradores.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.colaboradores.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.colaboradores.manage')`  
**Testado em:** `Modules/Ponto/Tests/Feature/SpatiePermissionsTest` (real — 10 testes, 15 assertions, valida ambas direções: sem permissão bloqueia, com permissão passa)

### R-PONT-004 · Autorização Spatie `ponto.aprovacoes.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.aprovacoes.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.aprovacoes.manage')`  
**Testado em:** `Modules/Ponto/Tests/Feature/SpatiePermissionsTest` (real — 10 testes, 15 assertions, valida ambas direções: sem permissão bloqueia, com permissão passa)

### R-PONT-005 · Autorização Spatie `ponto.relatorios.view`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.relatorios.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.relatorios.view')`  
**Testado em:** `Modules/Ponto/Tests/Feature/SpatiePermissionsTest` (real — 10 testes, 15 assertions, valida ambas direções: sem permissão bloqueia, com permissão passa)

### R-PONT-006 · Autorização Spatie `ponto.configuracoes.manage`

```gherkin
Dado que um usuário **não** tem a permissão `ponto.configuracoes.manage`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('ponto.configuracoes.manage')`  
**Testado em:** `Modules/Ponto/Tests/Feature/SpatiePermissionsTest` (real — 10 testes, 15 assertions, valida ambas direções: sem permissão bloqueia, com permissão passa)
