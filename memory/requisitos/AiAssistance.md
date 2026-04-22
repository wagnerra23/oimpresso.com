---
module: AiAssistance
alias: aiassistance
status: inativo
migration_target: react
migration_priority: baixa (desativado)
risk: baixo
areas: [Core]
last_generated: 2026-04-22
scale:
  routes: 8
  controllers: 3
  views: 6
  entities: 1
  permissions: 1
---

# Requisitos funcionais — AiAssistance

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/AiAssistance.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/AiAssistance`) que linka user stories com telas React,
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

AI Assistant module for UltimatePOS. This module used openAI API to help with in copywriting & reporting

## 2. Áreas funcionais

### 2.1. Core

**Controller(s):** `AiAssistanceController`  
**Ações (4):** `index`, `create`, `generate`, `history`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-AIAS-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-AIAS-001 · Listar Core

> **Área:** Core  
> **Rota:** `GET /dashboard`  
> **Controller/ação:** `AiAssistanceController@index`

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

### US-AIAS-002 · Criar Core

> **Área:** Core  
> **Rota:** `GET /create/{tool}`  
> **Controller/ação:** `AiAssistanceController@create`

**Como** usuário autorizado  
**Quero** criar um novo item em Core  
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

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-AIAS-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo AiAssistance
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-AIAS-002 · Autorização Spatie `aiassistance.access_aiassistance_module`

```gherkin
Dado que um usuário **não** tem a permissão `aiassistance.access_aiassistance_module`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('aiassistance.access_aiassistance_module')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.2. Dependências entre módulos

- 🔼 é consumido por **?** (?x)

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `AiAssistanceHistory` | `aiassistance_history` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements AiAssistance`_  
_Ver no DocVault: `/docs/modulos/AiAssistance`_
