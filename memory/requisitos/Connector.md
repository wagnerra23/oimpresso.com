---
module: Connector
alias: connector
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Api, Attendance, Brand, Business Location, Call Logs, Cash Register, Category, Client, Common Resource, Contact, Core, Expense, Field Force, Follow Up, Product, Product Sell, Sell, Table, Tax, Types Of Service, Unit, User]
last_generated: 2026-04-22
scale:
  routes: 55
  controllers: 25
  views: 2
  entities: 0
  permissions: 0
---

# Requisitos funcionais — Connector

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Connector.md`.
>
> Arquivos deste formato são consumidos pelo módulo **MemCofre**
> (`/docs/modulos/Connector`) que linka user stories com telas React,
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

Provide the API's for POS

## 2. Áreas funcionais

### 2.1. Api

**Controller(s):** `ApiController`  
**Ações (7):** `getStatusCode`, `setStatusCode`, `respondUnauthorized`, `respond`, `modelNotFoundExceptionResult`, `otherExceptions`, `getClient`

_Descrição funcional:_ [TODO]

### 2.2. Attendance

**Controller(s):** `AttendanceController`  
**Ações (4):** `getAttendance`, `clockin`, `clockout`, `getHolidays`

_Descrição funcional:_ [TODO]

### 2.3. Brand

**Controller(s):** `BrandController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.4. Business Location

**Controller(s):** `BusinessLocationController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.9. Call Logs

**Controller(s):** `CallLogsController`  
**Ações (2):** `saveCallLogs`, `searchUser`

_Descrição funcional:_ [TODO]

### 2.5. Cash Register

**Controller(s):** `CashRegisterController`  
**Ações (3):** `index`, `store`, `show`

_Descrição funcional:_ [TODO]

### 2.6. Category

**Controller(s):** `CategoryController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.22. Client

**Controller(s):** `ClientController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `regenerate`

_Descrição funcional:_ [TODO]

### 2.7. Common Resource

**Controller(s):** `CommonResourceController`  
**Ações (7):** `getPaymentAccounts`, `getPaymentMethods`, `getBusinessDetails`, `getProfitLoss`, `getProductStock`, `getNotifications`, `getLocation`

_Descrição funcional:_ [TODO]

### 2.8. Contact

**Controller(s):** `ContactController`  
**Ações (5):** `index`, `store`, `show`, `update`, `contactPay`

_Descrição funcional:_ [TODO]

### 2.16. Core

**Controller(s):** `SuperadminController`, `ConnectorController`  
**Ações (9):** `getActiveSubscription`, `getPackages`, `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.11. Expense

**Controller(s):** `ExpenseController`  
**Ações (6):** `index`, `show`, `store`, `update`, `listExpenseRefund`, `listExpenseCategories`

_Descrição funcional:_ [TODO]

### 2.12. Field Force

**Controller(s):** `FieldForceController`  
**Ações (3):** `index`, `store`, `updateStatus`

_Descrição funcional:_ [TODO]

### 2.10. Follow Up

**Controller(s):** `FollowUpController`  
**Ações (6):** `index`, `getFollowUpResources`, `store`, `show`, `update`, `getLeads`

_Descrição funcional:_ [TODO]

### 2.13. Product

**Controller(s):** `ProductController`  
**Ações (4):** `index`, `show`, `listVariations`, `getSellingPriceGroup`

_Descrição funcional:_ [TODO]

### 2.14. Product Sell

**Controller(s):** `ProductSellController`  
**Ações (3):** `newProduct`, `newSell`, `newContactApi`

_Descrição funcional:_ [TODO]

### 2.15. Sell

**Controller(s):** `SellController`  
**Ações (8):** `index`, `show`, `store`, `update`, `destroy`, `updateSellShippingStatus`, `addSellReturn`, `listSellReturn`

_Descrição funcional:_ [TODO]

### 2.17. Table

**Controller(s):** `TableController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.18. Tax

**Controller(s):** `TaxController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.19. Types Of Service

**Controller(s):** `TypesOfServiceController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.20. Unit

**Controller(s):** `UnitController`  
**Ações (2):** `index`, `show`

_Descrição funcional:_ [TODO]

### 2.21. User

**Controller(s):** `UserController`  
**Ações (7):** `index`, `show`, `loggedin`, `updatePassword`, `registerUser`, `generateRandomString`, `forgetPassword`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-CONN-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-CONN-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-CONN-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Connector
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin

### 5.2. Dependências entre módulos

- 🔼 é consumido por **?** (?x)
- 🔼 é consumido por **?** (?x)
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
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements Connector`_  
_Ver no MemCofre: `/docs/modulos/Connector`_
