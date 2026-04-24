---
module: AssetManagement
alias: assetmanagement
status: ativo
migration_target: react
migration_priority: média
risk: médio
areas: [Asset, Asset Allocation, Asset Maitenance, Asset Settings, Revoke Allocated Asset]
last_generated: 2026-04-22
scale:
  routes: 10
  controllers: 7
  views: 17
  entities: 4
  permissions: 6
---

# Requisitos funcionais — AssetManagement

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/AssetManagement.md`.
>
> Arquivos deste formato são consumidos pelo módulo **MemCofre**
> (`/docs/modulos/AssetManagement`) que linka user stories com telas React,
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

Useful for managing all kinds of assets.

## 2. Áreas funcionais

### 2.2. Asset

**Controller(s):** `AssetController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `dashboard`

_Descrição funcional:_ [TODO]

### 2.1. Asset Allocation

**Controller(s):** `AssetAllocationController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.3. Asset Maitenance

**Controller(s):** `AssetMaitenanceController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.4. Asset Settings

**Controller(s):** `AssetSettingsController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.5. Revoke Allocated Asset

**Controller(s):** `RevokeAllocatedAssetController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-ASSE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-ASSE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ASSE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo AssetManagement
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-002 · Autorização Spatie `asset.view`

```gherkin
Dado que um usuário **não** tem a permissão `asset.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.view')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-003 · Autorização Spatie `asset.create`

```gherkin
Dado que um usuário **não** tem a permissão `asset.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.create')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-004 · Autorização Spatie `asset.update`

```gherkin
Dado que um usuário **não** tem a permissão `asset.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.update')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-005 · Autorização Spatie `asset.delete`

```gherkin
Dado que um usuário **não** tem a permissão `asset.delete`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.delete')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-006 · Autorização Spatie `asset.view_all_maintenance`

```gherkin
Dado que um usuário **não** tem a permissão `asset.view_all_maintenance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.view_all_maintenance')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ASSE-007 · Autorização Spatie `asset.view_own_maintenance`

```gherkin
Dado que um usuário **não** tem a permissão `asset.view_own_maintenance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('asset.view_own_maintenance')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — registra categorias/taxonomias customizadas

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Asset` | `—` | [TODO] |
| `AssetMaintenance` | `—` | [TODO] |
| `AssetTransaction` | `—` | [TODO] |
| `AssetWarranty` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements AssetManagement`_  
_Ver no MemCofre: `/docs/modulos/AssetManagement`_
