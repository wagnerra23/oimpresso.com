# Arquitetura

## 1. Objetivo

Crm Module

## 2. Áreas funcionais

### 2.1. Call Log

**Controller(s):** `CallLogController`  
**Ações (3):** `index`, `massDestroy`, `allUsersCallLog`

_Descrição funcional:_ [TODO]

### 2.2. Campaign

**Controller(s):** `CampaignController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `sendNotification`, `__sendCampaignNotification`

_Descrição funcional:_ [TODO]

### 2.3. Contact Booking

**Controller(s):** `ContactBookingController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.4. Contact Login

**Controller(s):** `ContactLoginController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `allContactsLoginList`, `commissions`

_Descrição funcional:_ [TODO]

### 2.5. Core

**Controller(s):** `CrmDashboardController`, `DashboardController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.8. Lead

**Controller(s):** `LeadController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `convertToCustomer`, `postLifeStage`

_Descrição funcional:_ [TODO]

### 2.9. Ledger

**Controller(s):** `LedgerController`  
**Ações (2):** `index`, `getLedger`

_Descrição funcional:_ [TODO]

### 2.10. Manage Profile

**Controller(s):** `ManageProfileController`  
**Ações (3):** `getProfile`, `updateProfile`, `updatePassword`

_Descrição funcional:_ [TODO]

### 2.6. Marketplace

**Controller(s):** `CrmMarketplaceController`  
**Ações (3):** `index`, `save`, `importLeads`

_Descrição funcional:_ [TODO]

### 2.11. Order Request

**Controller(s):** `OrderRequestController`  
**Ações (5):** `index`, `create`, `store`, `getProductRow`, `listOrderRequests`

_Descrição funcional:_ [TODO]

### 2.12. Proposal

**Controller(s):** `ProposalController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.13. Proposal Template

**Controller(s):** `ProposalTemplateController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getEdit`, `postEdit`, `getView`, `send`, `deleteProposalMedia`

_Descrição funcional:_ [TODO]

### 2.14. Purchase

**Controller(s):** `PurchaseController`  
**Ações (1):** `getPurchaseList`

_Descrição funcional:_ [TODO]

### 2.15. Report

**Controller(s):** `ReportController`  
**Ações (5):** `index`, `followUpsByUser`, `followUpsContact`, `leadToCustomerConversion`, `showLeadToCustomerConversionDetails`

_Descrição funcional:_ [TODO]

### 2.16. Schedule

**Controller(s):** `ScheduleController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getTodaysSchedule`, `getLeadSchedule`, `getInvoicesForFollowUp`, `getFollowUpGroups`, `getCustomerDropdown`

_Descrição funcional:_ [TODO]

### 2.17. Schedule Log

**Controller(s):** `ScheduleLogController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.18. Sell

**Controller(s):** `SellController`  
**Ações (1):** `getSellList`

_Descrição funcional:_ [TODO]

### 2.7. Settings

**Controller(s):** `CrmSettingsController`  
**Ações (2):** `index`, `updateSettings`

_Descrição funcional:_ [TODO]

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles
- **`addTaxonomies()`** — registra categorias/taxonomias customizadas

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Campaign` | `crm_campaigns` | [TODO] |
| `CrmCallLog` | `—` | [TODO] |
| `CrmContact` | `contacts` | [TODO] |
| `CrmContactPersonCommission` | `—` | [TODO] |
| `CrmMarketplace` | `—` | [TODO] |
| `Leaduser` | `crm_lead_users` | [TODO] |
| `Proposal` | `crm_proposals` | [TODO] |
| `ProposalTemplate` | `crm_proposal_templates` | [TODO] |
| `Schedule` | `crm_schedules` | [TODO] |
| `ScheduleLog` | `crm_schedule_logs` | [TODO] |
| `ScheduleUser` | `crm_schedule_users` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements Crm`_  
_Ver no MemCofre: `/docs/modulos/Crm`_
