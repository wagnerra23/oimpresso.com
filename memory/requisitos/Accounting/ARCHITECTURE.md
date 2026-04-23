# Arquitetura

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
