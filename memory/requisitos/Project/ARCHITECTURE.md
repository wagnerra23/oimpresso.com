# Arquitetura

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
