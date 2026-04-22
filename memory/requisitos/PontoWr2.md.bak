---
module: PontoWr2
alias: pontowr2
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Aprovacao, Banco Horas, Colaborador, Configuracao, Core, Escala, Espelho, Importacao, Intercorrencia, Relatorio]
last_generated: 2026-04-22
scale:
  routes: 41
  controllers: 12
  views: 26
  entities: 10
  permissions: 5
---

# Requisitos funcionais — PontoWr2

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/PontoWr2.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/PontoWr2`) que linka user stories com telas React,
> regras Gherkin com testes, e mantém rastreabilidade evidência → requisito.

## Sumário

1. [Objetivo](#1-objetivo)
2. [Áreas funcionais](#2-áreas-funcionais)
3. [User stories](#3-user-stories)
4. [Regras de negócio (Gherkin)](#4-regras-de-negócio-gherkin)
5. [Integrações](#5-integrações)
6. [Dados e entidades](#6-dados-e-entidades)
7. [Decisões em aberto](#7-decisões-em-aberto)
8. [Histórico e notas](#8-histórico-e-notas)

---

## 1. Objetivo

Módulo de Ponto Eletrônico conforme Portaria MTP 671/2021 — WR2 Sistemas. Estende UltimatePOS 6 + Essentials & HRM.

## 2. Áreas funcionais

### 2.1. Aprovacao

**Controller(s):** `AprovacaoController`  
**Ações (4):** `index`, `aprovar`, `rejeitar`, `aprovarEmLote`

_Descrição funcional:_ [TODO]

### 2.2. Banco Horas

**Controller(s):** `BancoHorasController`  
**Ações (3):** `index`, `show`, `ajustarManual`

_Descrição funcional:_ [TODO]

### 2.3. Colaborador

**Controller(s):** `ColaboradorController`  
**Ações (3):** `index`, `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.4. Configuracao

**Controller(s):** `ConfiguracaoController`  
**Ações (3):** `index`, `reps`, `storeRep`

_Descrição funcional:_ [TODO]

### 2.5. Core

**Controller(s):** `DashboardController`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.6. Escala

**Controller(s):** `EscalaController`  
**Ações (6):** `index`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.7. Espelho

**Controller(s):** `EspelhoController`  
**Ações (3):** `index`, `show`, `imprimir`

_Descrição funcional:_ [TODO]

### 2.8. Importacao

**Controller(s):** `ImportacaoController`  
**Ações (5):** `index`, `create`, `store`, `show`, `baixarOriginal`

_Descrição funcional:_ [TODO]

### 2.9. Intercorrencia

**Controller(s):** `IntercorrenciaController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `submeter`, `cancelar`, `aiClassify`

_Descrição funcional:_ [TODO]

### 2.10. Relatorio

**Controller(s):** `RelatorioController`  
**Ações (2):** `index`, `gerar`

_Descrição funcional:_ [TODO]

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
**Testado em:** _[TODO — apontar caminho do teste]_

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

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.2. Dependências entre módulos

- 🔼 é consumido por **?** (?x)
- 🔼 é consumido por **?** (?x)

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `ApuracaoDia` | `ponto_apuracao_dia` | [TODO] |
| `BancoHorasMovimento` | `ponto_banco_horas_movimentos` | [TODO] |
| `BancoHorasSaldo` | `ponto_banco_horas_saldo` | [TODO] |
| `Colaborador` | `ponto_colaborador_config` | [TODO] |
| `Escala` | `ponto_escalas` | [TODO] |
| `EscalaTurno` | `ponto_escala_turnos` | [TODO] |
| `Importacao` | `ponto_importacoes` | [TODO] |
| `Intercorrencia` | `ponto_intercorrencias` | [TODO] |
| `Marcacao` | `ponto_marcacoes` | [TODO] |
| `Rep` | `ponto_reps` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements PontoWr2`_  
_Ver no DocVault: `/docs/modulos/PontoWr2`_
