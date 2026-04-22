---
module: Essentials
alias: essentials
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Allowance And Deduction, Attendance, Core, Document, Document Share, Holiday, Knowledge Base, Leave, Leave Type, Message, Payroll, Reminder, Sales Target, Settings, Shift, To Do]
last_generated: 2026-04-22
scale:
  routes: 53
  controllers: 19
  views: 87
  entities: 18
  permissions: 23
---

# Requisitos funcionais — Essentials

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Essentials.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Essentials`) que linka user stories com telas React,
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

Some essentials functionality for every businesses & Human resource management (HRM) feature.

## 2. Áreas funcionais

### 2.5. Allowance And Deduction

**Controller(s):** `EssentialsAllowanceAndDeductionController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.1. Attendance

**Controller(s):** `AttendanceController`  
**Ações (13):** `index`, `create`, `store`, `edit`, `update`, `destroy`, `clockInClockOut`, `getUserAttendanceSummary`, `validateClockInClockOut`, `getAttendanceByShift`, `getAttendanceByDate`, `importAttendance` _+ 1_

_Descrição funcional:_ [TODO]

### 2.2. Core

**Controller(s):** `DashboardController`, `EssentialsController`  
**Ações (10):** `hrmDashboard`, `getUserSalesTargets`, `essentialsDashboard`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `index`

_Descrição funcional:_ [TODO]

### 2.3. Document

**Controller(s):** `DocumentController`  
**Ações (7):** `index`, `store`, `show`, `edit`, `update`, `destroy`, `download`

_Descrição funcional:_ [TODO]

### 2.4. Document Share

**Controller(s):** `DocumentShareController`  
**Ações (2):** `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.6. Holiday

**Controller(s):** `EssentialsHolidayController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.11. Knowledge Base

**Controller(s):** `KnowledgeBaseController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.7. Leave

**Controller(s):** `EssentialsLeaveController`  
**Ações (10):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `changeStatus`, `activity`, `getUserLeaveSummary`

_Descrição funcional:_ [TODO]

### 2.8. Leave Type

**Controller(s):** `EssentialsLeaveTypeController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.9. Message

**Controller(s):** `EssentialsMessageController`  
**Ações (4):** `index`, `store`, `destroy`, `getNewMessages`

_Descrição funcional:_ [TODO]

### 2.12. Payroll

**Controller(s):** `PayrollController`  
**Ações (16):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getAllowanceAndDeductionRow`, `payrollGroupDatatable`, `viewPayrollGroup`, `getEditPayrollGroup`, `getUpdatePayrollGroup` _+ 4_

_Descrição funcional:_ [TODO]

### 2.13. Reminder

**Controller(s):** `ReminderController`  
**Ações (5):** `index`, `store`, `show`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.14. Sales Target

**Controller(s):** `SalesTargetController`  
**Ações (3):** `index`, `setSalesTarget`, `saveSalesTarget`

_Descrição funcional:_ [TODO]

### 2.10. Settings

**Controller(s):** `EssentialsSettingsController`  
**Ações (2):** `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.15. Shift

**Controller(s):** `ShiftController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getAssignUsers`, `postAssignUsers`

_Descrição funcional:_ [TODO]

### 2.16. To Do

**Controller(s):** `ToDoController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `addComment`, `deleteComment`, `uploadDocument`, `deleteDocument`, `viewSharedDocs`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-ESSE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-ESSE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ESSE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Essentials
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-002 · Autorização Spatie `essentials.crud_leave_type`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_leave_type`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_leave_type')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-003 · Autorização Spatie `essentials.crud_all_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_leave')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-004 · Autorização Spatie `essentials.crud_own_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_own_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_own_leave')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-005 · Autorização Spatie `essentials.approve_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.approve_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.approve_leave')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-006 · Autorização Spatie `essentials.crud_all_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_attendance')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ESSE-007 · Autorização Spatie `essentials.view_own_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.view_own_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.view_own_attendance')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — registra categorias/taxonomias customizadas
- **`moduleViewPartials()`** — injeta conteúdo em views do core

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Document` | `essentials_documents` | [TODO] |
| `DocumentShare` | `essentials_document_shares` | [TODO] |
| `EssentialsAllowanceAndDeduction` | `essentials_allowances_and_deductions` | [TODO] |
| `EssentialsAttendance` | `—` | [TODO] |
| `EssentialsHoliday` | `—` | [TODO] |
| `EssentialsLeave` | `—` | [TODO] |
| `EssentialsLeaveType` | `—` | [TODO] |
| `EssentialsMessage` | `—` | [TODO] |
| `EssentialsTodoComment` | `—` | [TODO] |
| `EssentialsUserAllowancesAndDeduction` | `essentials_user_allowance_and_deductions` | [TODO] |
| `EssentialsUserSalesTarget` | `essentials_user_sales_targets` | [TODO] |
| `EssentialsUserShift` | `—` | [TODO] |
| `KnowledgeBase` | `essentials_kb` | [TODO] |
| `PayrollGroup` | `essentials_payroll_groups` | [TODO] |
| `PayrollGroupTransaction` | `essentials_payroll_group_transactions` | [TODO] |
| `Reminder` | `essentials_reminders` | [TODO] |
| `Shift` | `essentials_shifts` | [TODO] |
| `ToDo` | `essentials_to_dos` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements Essentials`_  
_Ver no DocVault: `/docs/modulos/Essentials`_
