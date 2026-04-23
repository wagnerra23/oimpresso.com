---
module: Project
alias: project
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Activity, Core, Invoice, Report, Task, Task Comment, Time Log]
last_generated: 2026-04-22
scale:
  routes: 20
  controllers: 9
  views: 43
  entities: 10
  permissions: 3
---

# Requisitos funcionais — Project

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Project.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Project`) que linka user stories com telas React,
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

Project Management Module

## 2. Áreas funcionais

### 2.1. Activity

**Controller(s):** `ActivityController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.3. Core

**Controller(s):** `ProjectController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `postSettings`, `postProjectStatus`

_Descrição funcional:_ [TODO]

### 2.2. Invoice

**Controller(s):** `InvoiceController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getProjectInvoiceTaxReport`

_Descrição funcional:_ [TODO]

### 2.5. Report

**Controller(s):** `ReportController`  
**Ações (3):** `index`, `getEmployeeTimeLogReport`, `getProjectTimeLogReport`

_Descrição funcional:_ [TODO]

### 2.7. Task

**Controller(s):** `TaskController`  
**Ações (10):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getTaskStatus`, `postTaskStatus`, `postTaskDescription`

_Descrição funcional:_ [TODO]

### 2.6. Task Comment

**Controller(s):** `TaskCommentController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `postMedia`

_Descrição funcional:_ [TODO]

### 2.4. Time Log

**Controller(s):** `ProjectTimeLogController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-PROJ-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-PROJ-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-PROJ-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Project
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-002 · Autorização Spatie `project.create_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.create_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.create_project')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-003 · Autorização Spatie `project.edit_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.edit_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.edit_project')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-004 · Autorização Spatie `project.delete_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.delete_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.delete_project')`  
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
| `InvoiceLine` | `pjt_invoice_lines` | [TODO] |
| `Project` | `pjt_projects` | [TODO] |
| `ProjectCategory` | `categorizables` | [TODO] |
| `ProjectMember` | `pjt_project_members` | [TODO] |
| `ProjectTask` | `pjt_project_tasks` | [TODO] |
| `ProjectTaskComment` | `pjt_project_task_comments` | [TODO] |
| `ProjectTaskMember` | `pjt_project_task_members` | [TODO] |
| `ProjectTimeLog` | `pjt_project_time_logs` | [TODO] |
| `ProjectTransaction` | `—` | [TODO] |
| `ProjectUser` | `users` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Project`_  
_Ver no DocVault: `/docs/modulos/Project`_
