---
module: Woocommerce
alias: woocommerce
status: ativo
migration_target: react
migration_priority: média
risk: médio
areas: [Core, Webhook]
last_generated: 2026-04-22
scale:
  routes: 19
  controllers: 4
  views: 13
  entities: 1
  permissions: 5
---

# Requisitos funcionais — Woocommerce

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Woocommerce.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Woocommerce`) que linka user stories com telas React,
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

Allows you to connect POS with WooCommerce website.

## 2. Áreas funcionais

### 2.1. Core

**Controller(s):** `WoocommerceController`  
**Ações (12):** `index`, `apiSettings`, `updateSettings`, `syncCategories`, `syncProducts`, `syncOrders`, `getSyncLog`, `mapTaxRates`, `viewSyncLog`, `getLogDetails`, `resetCategories`, `resetProducts`

_Descrição funcional:_ [TODO]

### 2.2. Webhook

**Controller(s):** `WoocommerceWebhookController`  
**Ações (4):** `orderCreated`, `orderUpdated`, `orderDeleted`, `orderRestored`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-WOOC-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-WOOC-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-WOOC-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Woocommerce
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-WOOC-002 · Autorização Spatie `woocommerce.syc_categories`

```gherkin
Dado que um usuário **não** tem a permissão `woocommerce.syc_categories`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('woocommerce.syc_categories')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-WOOC-003 · Autorização Spatie `woocommerce.sync_products`

```gherkin
Dado que um usuário **não** tem a permissão `woocommerce.sync_products`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('woocommerce.sync_products')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-WOOC-004 · Autorização Spatie `woocommerce.sync_orders`

```gherkin
Dado que um usuário **não** tem a permissão `woocommerce.sync_orders`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('woocommerce.sync_orders')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-WOOC-005 · Autorização Spatie `woocommerce.map_tax_rates`

```gherkin
Dado que um usuário **não** tem a permissão `woocommerce.map_tax_rates`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('woocommerce.map_tax_rates')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-WOOC-006 · Autorização Spatie `woocommerce.access_woocommerce_api_settings`

```gherkin
Dado que um usuário **não** tem a permissão `woocommerce.access_woocommerce_api_settings`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('woocommerce.access_woocommerce_api_settings')`  
**Testado em:** _[TODO — apontar caminho do teste]_

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `WoocommerceSyncLog` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Woocommerce`_  
_Ver no DocVault: `/docs/modulos/Woocommerce`_
