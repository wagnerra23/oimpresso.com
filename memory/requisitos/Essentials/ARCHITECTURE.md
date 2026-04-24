# Arquitetura

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
_Ver no MemCofre: `/docs/modulos/Essentials`_
