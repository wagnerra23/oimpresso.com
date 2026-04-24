---
module: Grow
alias: grow
status: inativo
migration_target: react
migration_priority: baixa (desativado)
risk: baixo
areas: [Authenticate, Bank, Calendar, Canned, Captcha, Categories, Clients, Comments, Common, Company, Compose, Contacts, Contracts, Core, Cronjobs, Currency, Customfields, Database Response, Documents, Dynamic, Email, Emailtemplates, Errorlogs, Estimates, Events, Expenses, Feed, Files, Fileupload, Finish Response, Fooos, Forgot Password, Formbuilder, General, Home, Income Statement, Index Response, Invoices, Ipn, Items, KBCategories, Leads, Login, Logos, Messages, Milestones, Modules, Mollie, Notes, Payments, Paypal, Paystack, Polling, Preferences, Proposals, Razorpay, Register, Reminders, Reports, Requirements Response, Reset Password, Roles, Runonce, Search, Server Info Response, Settings Response, Setup, Sources, Spaces, Start, Stripe, Subscriptions, System, Tags, Tap, Tasks, Taxrates, Team, Template, Test, Theme, Tickets, Timebilling, Timeline, Timesheets, Tweak, Updates, User, User Response, Webform, Webforms, Webhooks, Webmail Templates, s]
last_generated: 2026-04-22
scale:
  routes: 797
  controllers: 142
  views: 957
  entities: 0
  permissions: 1
---

# Requisitos funcionais — Grow

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Grow.md`.
>
> Arquivos deste formato são consumidos pelo módulo **MemCofre**
> (`/docs/modulos/Grow`) que linka user stories com telas React,
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

Sistema de produção do Office impresso

## 2. Áreas funcionais

### 2.7. Authenticate

**Controller(s):** `Authenticate`  
**Ações (8):** `logIn`, `signUp`, `forgotPassword`, `resetPassword`, `logInAction`, `forgotPasswordAction`, `resetPasswordAction`, `signUpAction`

_Descrição funcional:_ [TODO]

### 2.49. Bank

**Controller(s):** `Bank`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.8. Calendar

**Controller(s):** `Calendar`  
**Ações (7):** `index`, `create`, `store`, `update`, `destroy`, `show`, `deleteFiles`

_Descrição funcional:_ [TODO]

### 2.9. Canned

**Controller(s):** `Canned`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `search`, `updateRecentlyUsed`

_Descrição funcional:_ [TODO]

### 2.50. Captcha

**Controller(s):** `Captcha`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.10. Categories

**Controller(s):** `Categories`  
**Ações (8):** `index`, `create`, `store`, `edit`, `update`, `destroy`, `showTeam`, `updateTeam`

_Descrição funcional:_ [TODO]

### 2.11. Clients

**Controller(s):** `Clients`, `Clients`, `Clients`, `Clients`, `Clients`  
**Ações (20):** `index`, `create`, `store`, `show`, `showDynamic`, `edit`, `update`, `destroy`, `getCustomFields`, `customFieldValidationFailed`, `profile`, `logo` _+ 8_

_Descrição funcional:_ [TODO]

### 2.12. Comments

**Controller(s):** `Comments`  
**Ações (3):** `index`, `store`, `destroy`

_Descrição funcional:_ [TODO]

### 2.33. Common

**Controller(s):** `Common`  
**Ações (1):** `showErrorLog`

_Descrição funcional:_ [TODO]

### 2.51. Company

**Controller(s):** `Company`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.94. Compose

**Controller(s):** `Compose`  
**Ações (3):** `compose`, `send`, `prefillTemplate`

_Descrição funcional:_ [TODO]

### 2.13. Contacts

**Controller(s):** `Contacts`  
**Ações (8):** `index`, `show`, `create`, `store`, `edit`, `update`, `destroy`, `updatePreferences`

_Descrição funcional:_ [TODO]

### 2.14. Contracts

**Controller(s):** `Contracts`, `Contracts`, `Contracts`  
**Ações (28):** `index`, `create`, `store`, `show`, `showPublic`, `editingContract`, `destroy`, `bulkDelete`, `changeCategory`, `changeCategoryUpdate`, `publish`, `publishScheduled` _+ 16_

_Descrição funcional:_ [TODO]

### 2.15. Core

**Controller(s):** `Controller`, `GrowController`, `Knowledgebase`, `Knowledgebase`  
**Ações (14):** `index`, `create`, `generate`, `history`, `store`, `show`, `edit`, `update`, `destroy`, `categories`, `storeCategory`, `editCategory` _+ 2_

_Descrição funcional:_ [TODO]

### 2.52. Cronjobs

**Controller(s):** `Cronjobs`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.53. Currency

**Controller(s):** `Currency`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.54. Customfields

**Controller(s):** `Customfields`  
**Ações (16):** `showClient`, `updateClient`, `showProject`, `updateProject`, `showLead`, `updateLead`, `showTask`, `updateTask`, `showTicket`, `updateTicket`, `destroy`, `deleteField` _+ 4_

_Descrição funcional:_ [TODO]

### 2.79. Database Response

**Controller(s):** `DatabaseResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.16. Documents

**Controller(s):** `Documents`  
**Ações (3):** `updateHero`, `updateDetails`, `updateBody`

_Descrição funcional:_ [TODO]

### 2.44. Dynamic

**Controller(s):** `Dynamic`, `Dynamic`  
**Ações (1):** `showDynamic`

_Descrição funcional:_ [TODO]

### 2.55. Email

**Controller(s):** `Email`  
**Ações (16):** `general`, `updateGeneral`, `smtp`, `updateSMTP`, `testEmail`, `testEmailAction`, `testSMTP`, `queueShow`, `queueRead`, `queueDelete`, `queuePurge`, `queueReschedule` _+ 4_

_Descrição funcional:_ [TODO]

### 2.56. Emailtemplates

**Controller(s):** `Emailtemplates`  
**Ações (3):** `index`, `show`, `update`

_Descrição funcional:_ [TODO]

### 2.57. Errorlogs

**Controller(s):** `Errorlogs`  
**Ações (4):** `index`, `download`, `delete`, `update`

_Descrição funcional:_ [TODO]

### 2.17. Estimates

**Controller(s):** `Estimates`, `Estimates`, `Estimates`, `Estimates`  
**Ações (40):** `index`, `create`, `store`, `applyDefaultAutomation`, `show`, `showPublic`, `saveEstimate`, `publishEstimate`, `publishScheduledEstimate`, `publishRevisedEstimate`, `resendEstimate`, `acceptEstimate` _+ 28_

_Descrição funcional:_ [TODO]

### 2.18. Events

**Controller(s):** `Events`  
**Ações (3):** `topNavEvents`, `markMyEventRead`, `markAllMyEventRead`

_Descrição funcional:_ [TODO]

### 2.19. Expenses

**Controller(s):** `Expenses`, `Expenses`, `Expenses`  
**Ações (20):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `downloadAttachment`, `attachDettach`, `attachDettachUpdate`, `deleteAttachment`, `changeCategory` _+ 8_

_Descrição funcional:_ [TODO]

### 2.29. Feed

**Controller(s):** `Feed`  
**Ações (12):** `companyNames`, `contactNames`, `emailAddress`, `tags`, `projects`, `leads`, `leadNames`, `projectAssignedUsers`, `projectAssignedTasks`, `cloneTaskProjects`, `projectsMilestones`, `projectClientUsers`

_Descrição funcional:_ [TODO]

### 2.30. Files

**Controller(s):** `Files`, `Files`  
**Ações (31):** `index`, `externalRequest`, `create`, `store`, `edit`, `renameFile`, `showImage`, `download`, `downloadAttachment`, `update`, `destroy`, `createFolder` _+ 19_

_Descrição funcional:_ [TODO]

### 2.31. Fileupload

**Controller(s):** `Fileupload`  
**Ações (9):** `save`, `saveWebForm`, `saveGeneralImage`, `saveLogo`, `saveAvatar`, `saveAppLogo`, `saveTinyMCEImage`, `uploadImportFiles`, `uploadCoverImage`

_Descrição funcional:_ [TODO]

### 2.80. Finish Response

**Controller(s):** `FinishResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.25. Fooos

**Controller(s):** `Fooos`, `Fooos`, `Fooos`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.3. Forgot Password

**Controller(s):** `ForgotPasswordController`  
**Ações (0):** ``

_Descrição funcional:_ [TODO]

### 2.58. Formbuilder

**Controller(s):** `Formbuilder`  
**Ações (10):** `buildForm`, `saveForm`, `availableFormFields`, `cleanLanguage`, `update`, `formSettings`, `saveSettings`, `formStyle`, `saveStyle`, `embedCode`

_Descrição funcional:_ [TODO]

### 2.59. General

**Controller(s):** `General`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.32. Home

**Controller(s):** `Home`, `Home`  
**Ações (6):** `index`, `csAffiliateDashboard`, `teamDashboard`, `clientDashboard`, `adminDashboard`, `widgetLeads`

_Descrição funcional:_ [TODO]

### 2.45. Income Statement

**Controller(s):** `IncomeStatement`  
**Ações (1):** `report`

_Descrição funcional:_ [TODO]

### 2.81. Index Response

**Controller(s):** `IndexResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.20. Invoices

**Controller(s):** `Invoices`, `Invoices`, `Invoices`, `Invoices`  
**Ações (40):** `index`, `redirectURL`, `createSelector`, `create`, `store`, `show`, `saveInvoice`, `publishInvoice`, `publishScheduledInvoice`, `resendInvoice`, `edit`, `update` _+ 28_

_Descrição funcional:_ [TODO]

### 2.2. Ipn

**Controller(s):** `Ipn`  
**Ações (2):** `index`, `initialiseIPN`

_Descrição funcional:_ [TODO]

### 2.21. Items

**Controller(s):** `Items`, `Items`  
**Ações (18):** `index`, `categoryItems`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `bulkDelete`, `changeCategory`, `changeCategoryUpdate`, `indexTasks` _+ 6_

_Descrição funcional:_ [TODO]

### 2.34. KBCategories

**Controller(s):** `KBCategories`  
**Ações (6):** `index`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.22. Leads

**Controller(s):** `Leads`, `Leads`, `Leads`, `Leads`  
**Ações (76):** `index`, `create`, `store`, `indexList`, `indexKanban`, `getCustomFields`, `customFieldValidationFailed`, `show`, `edit`, `update`, `destroy`, `changeCategory` _+ 64_

_Descrição funcional:_ [TODO]

### 2.4. Login

**Controller(s):** `LoginController`  
**Ações (0):** ``

_Descrição funcional:_ [TODO]

### 2.60. Logos

**Controller(s):** `Logos`  
**Ações (3):** `index`, `logo`, `updateLogo`

_Descrição funcional:_ [TODO]

### 2.36. Messages

**Controller(s):** `Messages`  
**Ações (8):** `index`, `getFeed`, `storeText`, `destroy`, `countMessages`, `getDeletedMessages`, `storeFiles`, `getUserStatus`

_Descrição funcional:_ [TODO]

### 2.37. Milestones

**Controller(s):** `Milestones`, `Milestones`  
**Ações (12):** `index`, `create`, `store`, `edit`, `update`, `destroy`, `updatePositions`, `categories`, `storeCategory`, `editCategory`, `updateCategory`, `updateCategoryPositions`

_Descrição funcional:_ [TODO]

### 2.61. Modules

**Controller(s):** `Modules`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.62. Mollie

**Controller(s):** `Mollie`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.38. Notes

**Controller(s):** `Notes`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `downloadAttachment`, `deleteAttachment`

_Descrição funcional:_ [TODO]

### 2.23. Payments

**Controller(s):** `Payments`, `Payments`  
**Ações (11):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `thankYou`, `thankYouRazorpay`, `thankYouTap`, `togglePinning`

_Descrição funcional:_ [TODO]

### 2.63. Paypal

**Controller(s):** `Paypal`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.64. Paystack

**Controller(s):** `Paystack`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.39. Polling

**Controller(s):** `Polling`  
**Ações (3):** `generalPoll`, `timersPoll`, `activeTimerPoll`

_Descrição funcional:_ [TODO]

### 2.40. Preferences

**Controller(s):** `Preferences`  
**Ações (5):** `updateTableConfig`, `create`, `store`, `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.41. Proposals

**Controller(s):** `Proposals`, `Proposals`, `Proposals`  
**Ações (28):** `index`, `create`, `store`, `applyDefaultAutomation`, `editAutomation`, `updateAutomation`, `show`, `showPublic`, `editingProposal`, `destroy`, `bulkDelete`, `changeCategory` _+ 16_

_Descrição funcional:_ [TODO]

### 2.65. Razorpay

**Controller(s):** `Razorpay`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.5. Register

**Controller(s):** `RegisterController`  
**Ações (0):** ``

_Descrição funcional:_ [TODO]

### 2.42. Reminders

**Controller(s):** `Reminders`  
**Ações (10):** `show`, `edit`, `create`, `store`, `getResourceItem`, `delete`, `close`, `topNavFeed`, `deleteReminder`, `deleteAllReminders`

_Descrição funcional:_ [TODO]

### 2.43. Reports

**Controller(s):** `Reports`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.82. Requirements Response

**Controller(s):** `RequirementsResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.6. Reset Password

**Controller(s):** `ResetPasswordController`  
**Ações (0):** ``

_Descrição funcional:_ [TODO]

### 2.66. Roles

**Controller(s):** `Roles`  
**Ações (8):** `index`, `create`, `store`, `edit`, `editHomePage`, `updateHomePage`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.91. Runonce

**Controller(s):** `Runonce`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.48. Search

**Controller(s):** `Search`  
**Ações (12):** `index`, `clients`, `projects`, `contracts`, `proposals`, `tickets`, `tasks`, `leads`, `files`, `attachments`, `knowledgebase`, `contacts`

_Descrição funcional:_ [TODO]

### 2.83. Server Info Response

**Controller(s):** `ServerInfoResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.84. Settings Response

**Controller(s):** `SettingsResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.85. Setup

**Controller(s):** `Setup`  
**Ações (11):** `index`, `serverInfo`, `checkRequirements`, `showDatabase`, `updateDatabase`, `updateSettings`, `updateUser`, `createUserSpace`, `createTeamSpace`, `postActions`, `mailingList`

_Descrição funcional:_ [TODO]

### 2.67. Sources

**Controller(s):** `Sources`  
**Ações (6):** `index`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.87. Spaces

**Controller(s):** `Spaces`  
**Ações (1):** `showDynamic`

_Descrição funcional:_ [TODO]

### 2.46. Start

**Controller(s):** `Start`  
**Ações (1):** `showStart`

_Descrição funcional:_ [TODO]

### 2.68. Stripe

**Controller(s):** `Stripe`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.69. Subscriptions

**Controller(s):** `Subscriptions`, `Subscriptions`  
**Ações (12):** `index`, `update`, `create`, `getProductPrices`, `store`, `show`, `setupStripePayment`, `subscriptionInvoices`, `edit`, `destroy`, `cancelSubscription`, `togglePinning`

_Descrição funcional:_ [TODO]

### 2.70. System

**Controller(s):** `System`  
**Ações (1):** `clearLaravelCache`

_Descrição funcional:_ [TODO]

### 2.71. Tags

**Controller(s):** `Tags`, `Tags`  
**Ações (6):** `index`, `update`, `create`, `store`, `edit`, `destroy`

_Descrição funcional:_ [TODO]

### 2.72. Tap

**Controller(s):** `Tap`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.26. Tasks

**Controller(s):** `Tasks`, `Tasks`, `Tasks`  
**Ações (73):** `index`, `update`, `statuses`, `editStatus`, `updateStatus`, `createStatus`, `storeStatus`, `move`, `updateMove`, `updateStagePositions`, `destroyStatus`, `priorities` _+ 61_

_Descrição funcional:_ [TODO]

### 2.73. Taxrates

**Controller(s):** `Taxrates`  
**Ações (6):** `index`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.88. Team

**Controller(s):** `Team`  
**Ações (7):** `index`, `create`, `store`, `edit`, `update`, `updatePreferences`, `destroy`

_Descrição funcional:_ [TODO]

### 2.47. Template

**Controller(s):** `Template`, `Template`  
**Ações (5):** `index`, `create`, `store`, `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.35. Test

**Controller(s):** `Test`, `Test`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.74. Theme

**Controller(s):** `Theme`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.27. Tickets

**Controller(s):** `Tickets`, `Tickets`, `Tickets`  
**Ações (37):** `index`, `update`, `statuses`, `editStatus`, `updateStatus`, `createStatus`, `storeStatus`, `move`, `statusSettings`, `statusSettingsUpdate`, `updateMove`, `updateStagePositions` _+ 25_

_Descrição funcional:_ [TODO]

### 2.89. Timebilling

**Controller(s):** `Timebilling`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.90. Timeline

**Controller(s):** `Timeline`  
**Ações (2):** `projectTimeline`, `clientTimeline`

_Descrição funcional:_ [TODO]

### 2.28. Timesheets

**Controller(s):** `Timesheets`, `Timesheets`, `Timesheets`  
**Ações (9):** `index`, `team`, `client`, `project`, `create`, `store`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.75. Tweak

**Controller(s):** `Tweak`  
**Ações (2):** `index`, `update`

_Descrição funcional:_ [TODO]

### 2.76. Updates

**Controller(s):** `Updates`  
**Ações (2):** `index`, `checkUpdates`

_Descrição funcional:_ [TODO]

### 2.92. User

**Controller(s):** `User`  
**Ações (9):** `avatar`, `updateAvatar`, `updatePassword`, `updatePasswordAction`, `updateTheme`, `updateThemeAction`, `updateLanguage`, `updateNotifications`, `updateNotificationsAction`

_Descrição funcional:_ [TODO]

### 2.86. User Response

**Controller(s):** `UserResponse`  
**Ações (1):** `toResponse`

_Descrição funcional:_ [TODO]

### 2.93. Webform

**Controller(s):** `Webform`  
**Ações (4):** `showWeb`, `saveForm`, `formFieldsArray`, `formFields`

_Descrição funcional:_ [TODO]

### 2.77. Webforms

**Controller(s):** `Webforms`  
**Ações (8):** `index`, `create`, `edit`, `store`, `embedCode`, `destroy`, `assignedUsers`, `updateAssignedUsers`

_Descrição funcional:_ [TODO]

### 2.1. Webhooks

**Controller(s):** `Webhooks`, `Webhooks`, `Webhooks`  
**Ações (3):** `index`, `getKey`, `onetimePayment`

_Descrição funcional:_ [TODO]

### 2.78. Webmail Templates

**Controller(s):** `WebmailTemplates`  
**Ações (6):** `index`, `create`, `edit`, `store`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.24. s

**Controller(s):** `Projects`, `Projects`, `Projects`, `Projects`, `Projects`  
**Ações (50):** `index`, `create`, `store`, `show`, `showDynamic`, `edit`, `update`, `getCustomFields`, `getClientCustomFields`, `customFieldValidationFailed`, `destroy`, `details` _+ 38_

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-GROW-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-GROW-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-GROW-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Grow
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-GROW-002 · Autorização Spatie `grow.access_grow_module`

```gherkin
Dado que um usuário **não** tem a permissão `grow.access_grow_module`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('grow.access_grow_module')`  
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

_Módulo não declara entities próprias._

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Grow`_  
_Ver no MemCofre: `/docs/modulos/Grow`_
