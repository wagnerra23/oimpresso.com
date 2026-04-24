---
module: Superadmin
alias: superadmin
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Base, Business, Communicator, Core, Packages, Page, Pesa Pal, Pricing, Settings, Subscription, Subscriptions]
last_generated: 2026-04-22
scale:
  routes: 33
  controllers: 13
  views: 45
  entities: 4
  permissions: 1
---

# Requisitos funcionais — Superadmin

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Superadmin.md`.
>
> Arquivos deste formato são consumidos pelo módulo **MemCofre**
> (`/docs/modulos/Superadmin`) que linka user stories com telas React,
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

Allows you to create packages & sell subscription to multiple businesses

## 2. Áreas funcionais

### 2.1. Base

**Controller(s):** `BaseController`  
**Ações (2):** `_payment_gateways`, `_add_subscription`

_Descrição funcional:_ [TODO]

### 2.2. Business

**Controller(s):** `BusinessController`  
**Ações (10):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `toggleActive`, `usersList`, `updatePassword`

_Descrição funcional:_ [TODO]

### 2.3. Communicator

**Controller(s):** `CommunicatorController`  
**Ações (3):** `index`, `send`, `getHistory`

_Descrição funcional:_ [TODO]

### 2.9. Core

**Controller(s):** `SuperadminController`  
**Ações (2):** `index`, `stats`

_Descrição funcional:_ [TODO]

### 2.4. Packages

**Controller(s):** `PackagesController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.5. Page

**Controller(s):** `PageController`  
**Ações (7):** `index`, `create`, `store`, `showPage`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.6. Pesa Pal

**Controller(s):** `PesaPalController`  
**Ações (1):** `pesaPalPaymentConfirmation`

_Descrição funcional:_ [TODO]

### 2.7. Pricing

**Controller(s):** `PricingController`  
**Ações (1):** `index`

_Descrição funcional:_ [TODO]

### 2.10. Settings

**Controller(s):** `SuperadminSettingsController`  
**Ações (2):** `edit`, `update`

_Descrição funcional:_ [TODO]

### 2.8. Subscription

**Controller(s):** `SubscriptionController`  
**Ações (10):** `index`, `pay`, `registerPay`, `confirm`, `paypalExpressCheckout`, `getRedirectToPaystack`, `postPaymentPaystackCallback`, `postFlutterwavePaymentCallback`, `show`, `allSubscriptions`

_Descrição funcional:_ [TODO]

### 2.11. Subscriptions

**Controller(s):** `SuperadminSubscriptionsController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `editSubscription`, `updateSubscription`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-SUPE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-SUPE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-SUPE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Superadmin
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-SUPE-002 · Autorização Spatie `superadmin.access_package_subscriptions`

```gherkin
Dado que um usuário **não** tem a permissão `superadmin.access_package_subscriptions`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('superadmin.access_package_subscriptions')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Package` | `—` | [TODO] |
| `Subscription` | `—` | [TODO] |
| `SuperadminCommunicatorLog` | `—` | [TODO] |
| `SuperadminFrontendPage` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Superadmin`_  
_Ver no MemCofre: `/docs/modulos/Superadmin`_
