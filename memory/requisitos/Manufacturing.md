---
module: Manufacturing
alias: manufacturing
status: ativo
migration_target: react
migration_priority: média
risk: médio
areas: [Core, Production, Recipe, Settings]
last_generated: 2026-04-22
scale:
  routes: 14
  controllers: 6
  views: 20
  entities: 3
  permissions: 4
---

# Requisitos funcionais — Manufacturing

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Manufacturing.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Manufacturing`) que linka user stories com telas React,
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

Used for businesses where products needs to be manufactured

## 2. Áreas funcionais

### 2.1. Core

**Controller(s):** `ManufacturingController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.2. Production

**Controller(s):** `ProductionController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getManufacturingReport`

_Descrição funcional:_ [TODO]

### 2.3. Recipe

**Controller(s):** `RecipeController`  
**Ações (11):** `index`, `create`, `store`, `show`, `getIngredientRow`, `addIngredients`, `getRecipeDetails`, `getIngredientGroupForm`, `updateRecipeProductPrices`, `destroy`, `isRecipeExist`

_Descrição funcional:_ [TODO]

### 2.4. Settings

**Controller(s):** `SettingsController`  
**Ações (2):** `index`, `store`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-MANU-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-MANU-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-MANU-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Manufacturing
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-MANU-002 · Autorização Spatie `manufacturing.access_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_recipe')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-MANU-003 · Autorização Spatie `manufacturing.add_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.add_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.add_recipe')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-MANU-004 · Autorização Spatie `manufacturing.edit_recipe`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.edit_recipe`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.edit_recipe')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-MANU-005 · Autorização Spatie `manufacturing.access_production`

```gherkin
Dado que um usuário **não** tem a permissão `manufacturing.access_production`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('manufacturing.access_production')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `MfgIngredientGroup` | `—` | [TODO] |
| `MfgRecipe` | `—` | [TODO] |
| `MfgRecipeIngredient` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Manufacturing`_  
_Ver no DocVault: `/docs/modulos/Manufacturing`_
