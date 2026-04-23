---
module: Repair
alias: repair
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Core, Customer Repair Status, Device Model, Job Sheet, Settings, Status]
last_generated: 2026-04-22
scale:
  routes: 30
  controllers: 9
  views: 52
  entities: 3
  permissions: 12
---

# Requisitos funcionais — Repair

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Repair.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Repair`) que linka user stories com telas React,
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

Useful for all kind of repair shops

## 2. Áreas funcionais

### 2.2. Core

**Controller(s):** `DashboardController`, `RepairController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `editRepairStatus`, `updateRepairStatus`, `deleteMedia`, `printLabel`, `printCustomerCopy`

_Descrição funcional:_ [TODO]

### 2.1. Customer Repair Status

**Controller(s):** `CustomerRepairStatusController`  
**Ações (2):** `index`, `postRepairStatus`

_Descrição funcional:_ [TODO]

### 2.3. Device Model

**Controller(s):** `DeviceModelController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getDeviceModels`, `getRepairChecklists`

_Descrição funcional:_ [TODO]

### 2.4. Job Sheet

**Controller(s):** `JobSheetController`  
**Ações (17):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `editStatus`, `updateStatus`, `deleteJobSheetImage`, `addParts`, `saveParts` _+ 5_

_Descrição funcional:_ [TODO]

### 2.5. Settings

**Controller(s):** `RepairSettingsController`  
**Ações (3):** `index`, `store`, `updateJobsheetSettings`

_Descrição funcional:_ [TODO]

### 2.6. Status

**Controller(s):** `RepairStatusController`  
**Ações (5):** `index`, `create`, `store`, `edit`, `update`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-REPA-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-REPA-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-REPA-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Repair
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-002 · Autorização Spatie `repair.create`

```gherkin
Dado que um usuário **não** tem a permissão `repair.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.create')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-003 · Autorização Spatie `repair.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.update')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-004 · Autorização Spatie `repair.view`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-005 · Autorização Spatie `repair.view_own`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view_own`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view_own')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-006 · Autorização Spatie `repair.delete`

```gherkin
Dado que um usuário **não** tem a permissão `repair.delete`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.delete')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-REPA-007 · Autorização Spatie `repair_status.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair_status.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair_status.update')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles
- **`get_pos_screen_view()`** — hook do UltimatePOS
- **`after_sale_saved()`** — hook do UltimatePOS
- **`after_product_saved()`** — hook do UltimatePOS
- **`addTaxonomies()`** — registra categorias/taxonomias customizadas

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `DeviceModel` | `repair_device_models` | [TODO] |
| `JobSheet` | `repair_job_sheets` | [TODO] |
| `RepairStatus` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Repair`_  
_Ver no DocVault: `/docs/modulos/Repair`_
