---
module: Accounting
alias: accounting
status: ativo
migration_target: react
migration_priority: baixa (grande, fazer por último ou dividir)
risk: alto
areas: [Budget, Chart Of Account, Core, Journal Entry, Media, Reconcile, Report, Settings, Transaction]
last_generated: 2026-04-22
scale:
  routes: 69
  controllers: 12
  views: 91
  entities: 70
  permissions: 12
---

# Requisitos funcionais — Accounting

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Accounting.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Accounting`) que linka user stories com telas React,
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

Accounting is the process of recording and tracking financial statements to see the financial health of an entity. This is done by inputting, sorting, measuring, and then communicating transactions in various formats. Accounting consists of bookkeeping and analysis. Once the bookkeeper records and organizes all of the transactions, the next step of accounting is to analyze these transactions into helpful reports which will show the state of one’s finances. These reports can include profit/loss statements, cash flow reports,etc. With small business accounting done right, small business owner will be able to have a clear understanding of the state of your finances so you can make better decisions based on what you have available.

## 2. Áreas funcionais

### 2.4. Budget

**Controller(s):** `BudgetController`  
**Ações (5):** `index`, `store_financial_year_start`, `update_monthly_budget`, `update_quarterly_budget`, `update_yearly_budget`

_Descrição funcional:_ [TODO]

### 2.5. Chart Of Account

**Controller(s):** `ChartOfAccountController`  
**Ações (9):** `index`, `export`, `get_chart_of_accounts`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.1. Core

**Controller(s):** `AccountingController`, `DashboardController`  
**Ações (11):** `trial_balance`, `income_statement`, `balance_sheet`, `transfers`, `create_transfer`, `store_transfer`, `index`, `expense_chart`, `current_financial_year_chart`, `last_30_days_financial_year_chart`, `get_totals`

_Descrição funcional:_ [TODO]

### 2.6. Journal Entry

**Controller(s):** `JournalEntryController`  
**Ações (7):** `index`, `get_journal_entries`, `create`, `store`, `show`, `edit`, `reverse`

_Descrição funcional:_ [TODO]

### 2.7. Media

**Controller(s):** `MediaController`  
**Ações (2):** `download`, `delete`

_Descrição funcional:_ [TODO]

### 2.8. Reconcile

**Controller(s):** `ReconcileController`  
**Ações (4):** `index`, `start_reconcile`, `store_reconcile`, `undo_reconcile`

_Descrição funcional:_ [TODO]

### 2.9. Report

**Controller(s):** `ReportController`  
**Ações (12):** `index`, `trial_balance`, `cash_flow`, `profit_and_loss`, `balance_sheet`, `ledger`, `accounts_receivable_ageing_summary`, `accounts_receivable_ageing_detail`, `accounts_payable_ageing_summary`, `accounts_payable_ageing_detail`, `budget_overview`, `journal`

_Descrição funcional:_ [TODO]

### 2.2. Settings

**Controller(s):** `AccountingSettingsController`  
**Ações (10):** `detail_types`, `store_detail_types`, `edit_detail_types`, `update_detail_types`, `destroy_detail_type`, `account_subtypes`, `store_account_subtypes`, `edit_account_subtypes`, `update_account_subtypes`, `destroy_account_subtype`

_Descrição funcional:_ [TODO]

### 2.3. Transaction

**Controller(s):** `AccountingTransactionController`  
**Ações (4):** `sales`, `expenses`, `purchases`, `map_to_chart_of_account`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-ACCO-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

### US-ACCO-001 · Listar Budget

> **Área:** Budget  
> **Rota:** `GET /`  
> **Controller/ação:** `BudgetController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Budget  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-002 · Listar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET /`  
> **Controller/ação:** `ChartOfAccountController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Chart Of Account  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-003 · Criar Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `POST store`  
> **Controller/ação:** `ChartOfAccountController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Chart Of Account  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-004 · Ver detalhe de Chart Of Account

> **Área:** Chart Of Account  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `ChartOfAccountController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-005 · Listar Core

> **Área:** Core  
> **Rota:** `GET /`  
> **Controller/ação:** `DashboardController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Core  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-006 · Listar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET /`  
> **Controller/ação:** `JournalEntryController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Journal Entry  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-007 · Criar Journal Entry

> **Área:** Journal Entry  
> **Rota:** `POST store`  
> **Controller/ação:** `JournalEntryController@store`

**Como** usuário autorizado  
**Quero** criar um novo item em Journal Entry  
**Para** alimentar o sistema com os dados operacionais

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-008 · Ver detalhe de Journal Entry

> **Área:** Journal Entry  
> **Rota:** `GET {id}/show`  
> **Controller/ação:** `JournalEntryController@show`

**Como** usuário com acesso ao item  
**Quero** consultar informação completa de um item específico  
**Para** tomar decisão com base em contexto completo

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-009 · Listar Reconcile

> **Área:** Reconcile  
> **Rota:** `GET /`  
> **Controller/ação:** `ReconcileController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Reconcile  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

### US-ACCO-010 · Listar Report

> **Área:** Report  
> **Rota:** `GET accounting`  
> **Controller/ação:** `ReportController@index`

**Como** usuário do módulo  
**Quero** ver o conjunto de Report  
**Para** ter visão geral e filtrar o que importa

**Implementado em:** _[TODO — apontar path do arquivo .tsx que atende]_

**Definition of Done:**
- [ ] Rota acessível apenas por papéis autorizados (`403` caso contrário)
- [ ] Scope por `business_id` nas queries
- [ ] Validação dos campos de input com FormRequest
- [ ] Shape JSON-friendly (sem Model inteiro) via `->transform()`
- [ ] Teste Feature cobrindo auth, permissão, validação
- [ ] Dark mode funciona
- [ ] Responsivo mobile (grid cols-1 md:cols-N)
- [ ] Toast `sonner` em mutations (success + error)

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ACCO-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Accounting
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-002 · Autorização Spatie `accounting.chart_of_accounts.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.index')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-003 · Autorização Spatie `accounting.chart_of_accounts.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.create')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-004 · Autorização Spatie `accounting.chart_of_accounts.edit`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.edit`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.edit')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-005 · Autorização Spatie `accounting.chart_of_accounts.destroy`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.chart_of_accounts.destroy`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.chart_of_accounts.destroy')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-006 · Autorização Spatie `accounting.journal_entries.index`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.index`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.index')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-ACCO-007 · Autorização Spatie `accounting.journal_entries.create`

```gherkin
Dado que um usuário **não** tem a permissão `accounting.journal_entries.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('accounting.journal_entries.create')`  
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

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Account` | `—` | [TODO] |
| `AccountDetailType` | `—` | [TODO] |
| `AccountSubtype` | `—` | [TODO] |
| `AccountTransaction` | `—` | [TODO] |
| `AccountType` | `—` | [TODO] |
| `BankDetails` | `—` | [TODO] |
| `Barcode` | `—` | [TODO] |
| `BranchCapital` | `—` | [TODO] |
| `Brands` | `—` | [TODO] |
| `Budget` | `—` | [TODO] |
| `Business` | `business` | [TODO] |
| `BusinessLocation` | `—` | [TODO] |
| `CashRegister` | `—` | [TODO] |
| `CashRegisterTransaction` | `—` | [TODO] |
| `Category` | `—` | [TODO] |
| `ChartOfAccount` | `chart_of_accounts` | [TODO] |
| `ClientRelationship` | `client_relationships` | [TODO] |
| `ClientType` | `client_types` | [TODO] |
| `Contact` | `—` | [TODO] |
| `ContactRestriction` | `—` | [TODO] |
| `Country` | `countries` | [TODO] |
| `Currency` | `currencies` | [TODO] |
| `CustomerGroup` | `—` | [TODO] |
| `DashboardConfiguration` | `—` | [TODO] |
| `Discount` | `—` | [TODO] |
| `DocumentAndNote` | `—` | [TODO] |
| `ExpenseCategory` | `—` | [TODO] |
| `Gender` | `—` | [TODO] |
| `GroupSubTax` | `—` | [TODO] |
| `IncomeCategory` | `—` | [TODO] |
| `InvoiceLayout` | `—` | [TODO] |
| `InvoiceScheme` | `—` | [TODO] |
| `JournalEntry` | `journal_entries` | [TODO] |
| `KycIdentification` | `kyc_identification` | [TODO] |
| `MaritalStatus` | `—` | [TODO] |
| `Media` | `—` | [TODO] |
| `NotificationTemplate` | `—` | [TODO] |
| `PaymentAccount` | `—` | [TODO] |
| `PaymentDetail` | `payment_details` | [TODO] |
| `PaymentTermType` | `—` | [TODO] |
| `PaymentType` | `payment_types` | [TODO] |
| `Printer` | `—` | [TODO] |
| `Product` | `—` | [TODO] |
| `ProductRack` | `—` | [TODO] |
| `ProductVariation` | `—` | [TODO] |
| `Profession` | `professions` | [TODO] |
| `PurchaseLine` | `—` | [TODO] |
| `ReferenceCount` | `—` | [TODO] |
| `SellingPriceGroup` | `—` | [TODO] |
| `StockAdjustmentLine` | `—` | [TODO] |
| `System` | `system` | [TODO] |
| `TaxRate` | `—` | [TODO] |
| `Title` | `titles` | [TODO] |
| `Transaction` | `—` | [TODO] |
| `TransactionPayment` | `—` | [TODO] |
| `TransactionSellLine` | `—` | [TODO] |
| `TransactionSellLinesPurchaseLines` | `—` | [TODO] |
| `Transfer` | `—` | [TODO] |
| `TypesOfService` | `—` | [TODO] |
| `Unit` | `—` | [TODO] |
| `User` | `—` | [TODO] |
| `UserContactAccess` | `—` | [TODO] |
| `Variation` | `—` | [TODO] |
| `VariationGroupPrice` | `—` | [TODO] |
| `VariationLocationDetails` | `—` | [TODO] |
| `VariationTemplate` | `—` | [TODO] |
| `VariationValueTemplate` | `—` | [TODO] |
| `Warranty` | `—` | [TODO] |
| `WorkDetails` | `—` | [TODO] |
| `WorkStatus` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:34_  
_Regerar: `php artisan module:requirements Accounting`_  
_Ver no DocVault: `/docs/modulos/Accounting`_
