---
module: Crm
alias: crm
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Call Log, Campaign, Contact Booking, Contact Login, Core, Lead, Ledger, Manage Profile, Marketplace, Order Request, Proposal, Proposal Template, Purchase, Report, Schedule, Schedule Log, Sell, Settings]
last_generated: 2026-04-22
scale:
  routes: 52
  controllers: 21
  views: 68
  entities: 11
  permissions: 13
---

# Requisitos funcionais — Crm

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Crm.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Crm`) que linka user stories com telas React,
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

## 3. User stories

> Convenção do ID: `US-CRM-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-CRM-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-CRM-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Crm
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-002 · Autorização Spatie `crm.access_all_schedule`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_schedule`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_schedule')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-003 · Autorização Spatie `crm.access_own_schedule`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_schedule`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_schedule')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-004 · Autorização Spatie `crm.access_all_leads`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_leads`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_leads')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-005 · Autorização Spatie `crm.access_own_leads`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_leads`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_leads')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-006 · Autorização Spatie `crm.access_all_campaigns`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_campaigns`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_campaigns')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-007 · Autorização Spatie `crm.access_own_campaigns`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_campaigns`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_campaigns')`  
**Testado em:** _[TODO — apontar caminho do teste]_

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
_Ver no DocVault: `/docs/modulos/Crm`_
