# Módulo: Accounting

> **Accounting is the process of recording and tracking financial statements to see the financial health of an entity. This is done by inputting, sorting, measuring, and then communicating transactions in various formats. Accounting consists of bookkeeping and analysis. Once the bookkeeper records and organizes all of the transactions, the next step of accounting is to analyze these transactions into helpful reports which will show the state of one’s finances. These reports can include profit/loss statements, cash flow reports,etc. With small business accounting done right, small business owner will be able to have a clear understanding of the state of your finances so you can make better decisions based on what you have available.**

- **Alias:** `accounting`
- **Versão:** ?
- **Path:** `D:\oimpresso.com\Modules/Accounting`
- **Status:** 🟢 ativo
- **Providers:** Modules\Accounting\Providers\AccountingServiceProvider
- **Requires (módulo.json):** nenhum

## Sinais detectados

- 🔗 Registra 3 hook(s) UltimatePOS: modifyAdminMenu, superadmin_package, user_permissions
- 🔴 +50 rotas — módulo grande, migrar em fases
- 🔴 +50 views — trabalho pesado
- 🔐 Registra 12 permissão(ões) Spatie
- 🔗 Acoplamento: depende de 1 outro(s) módulo(s)

- **Prioridade sugerida de migração:** baixa (grande, fazer por último ou dividir)
- **Risco estimado:** alto

## Escopo

| Peça | Qtde |
|---|---:|
| Rotas (web+api) | 69 |
| Controllers | 12 |
| Entities (Models) | 70 |
| Services | 8 |
| FormRequests | 0 |
| Middleware | 0 |
| Views Blade | 91 |
| Migrations | 21 |
| Arquivos de lang | 50 |
| Testes | 0 |

## Rotas

### `routes.php`

| Método | URI | Controller |
|---|---|---|
| `GET` | `/install` | `'InstallController@index'` |
| `POST` | `/install` | `'InstallController@install'` |
| `GET` | `/install/uninstall` | `'InstallController@uninstall'` |
| `GET` | `/install/update` | `'InstallController@update'` |
| `GET` | `/` | `'DashboardController@index'` |
| `GET` | `get_totals` | `'DashboardController@get_totals'` |
| `GET` | `trial_balance` | `'AccountingController@trial_balance'` |
| `GET` | `ledger` | `'AccountingController@ledger'` |
| `GET` | `balance_sheet` | `'AccountingController@balance_sheet'` |
| `GET` | `profit_and_loss` | `'AccountingController@profit_and_loss'` |
| `GET` | `cash_flow` | `'AccountingController@cash_flow'` |
| `GET` | `/` | `'ChartOfAccountController@index'` |
| `GET` | `get_chart_of_accounts` | `'ChartOfAccountController@get_chart_of_accounts'` |
| `GET` | `create` | `'ChartOfAccountController@create'` |
| `POST` | `store` | `'ChartOfAccountController@store'` |
| `GET` | `{id}/show` | `'ChartOfAccountController@show'` |
| `GET` | `{id}/edit` | `'ChartOfAccountController@edit'` |
| `POST` | `{id}/update` | `'ChartOfAccountController@update'` |
| `GET` | `{id}/destroy` | `'ChartOfAccountController@destroy'` |
| `GET` | `export` | `'ChartOfAccountController@export'` |
| `GET` | `/` | `'JournalEntryController@index'` |
| `GET` | `get_journal_entries` | `'JournalEntryController@get_journal_entries'` |
| `GET` | `create` | `'JournalEntryController@create'` |
| `POST` | `store` | `'JournalEntryController@store'` |
| `GET` | `{id}/show` | `'JournalEntryController@show'` |
| `GET` | `{id}/edit` | `'JournalEntryController@edit'` |
| `GET` | `{id}/reverse` | `'JournalEntryController@reverse'` |
| `POST` | `{id}/update` | `'JournalEntryController@update'` |
| `GET` | `{id}/destroy` | `'JournalEntryController@destroy'` |
| `GET` | `sales` | `'AccountingTransactionController@sales'` |
| `GET` | `expenses` | `'AccountingTransactionController@expenses'` |
| `GET` | `purchases` | `'AccountingTransactionController@purchases'` |
| `POST` | `map_to_chart_of_account` | `'AccountingTransactionController@map_to_chart_of_account'` |
| `GET` | `/` | `'AccountingController@transfers'` |
| `GET` | `create` | `'AccountingController@create_transfer'` |
| `POST` | `store` | `'AccountingController@store_transfer'` |
| `GET` | `/` | `'BudgetController@index'` |
| `POST` | `update_monthly_budget` | `'BudgetController@update_monthly_budget'` |
| `POST` | `update_quarterly_budget` | `'BudgetController@update_quarterly_budget'` |
| `POST` | `update_yearly_budget` | `'BudgetController@update_yearly_budget'` |

_... +29 rotas_

## Controllers

- **`AccountingController`** — 6 ação(ões): trial_balance, income_statement, balance_sheet, transfers, create_transfer, store_transfer
- **`AccountingSettingsController`** — 10 ação(ões): detail_types, store_detail_types, edit_detail_types, update_detail_types, destroy_detail_type, account_subtypes, store_account_subtypes, edit_account_subtypes +2
- **`AccountingTransactionController`** — 4 ação(ões): sales, expenses, purchases, map_to_chart_of_account
- **`BudgetController`** — 5 ação(ões): index, store_financial_year_start, update_monthly_budget, update_quarterly_budget, update_yearly_budget
- **`ChartOfAccountController`** — 9 ação(ões): index, export, get_chart_of_accounts, create, store, show, edit, update +1
- **`DashboardController`** — 5 ação(ões): index, expense_chart, current_financial_year_chart, last_30_days_financial_year_chart, get_totals
- **`DataController`** — 3 ação(ões): user_permissions, superadmin_package, modifyAdminMenu
- **`InstallController`** — 4 ação(ões): index, install, uninstall, update
- **`JournalEntryController`** — 7 ação(ões): index, get_journal_entries, create, store, show, edit, reverse
- **`MediaController`** — 2 ação(ões): download, delete
- **`ReconcileController`** — 4 ação(ões): index, start_reconcile, store_reconcile, undo_reconcile
- **`ReportController`** — 12 ação(ões): index, trial_balance, cash_flow, profit_and_loss, balance_sheet, ledger, accounts_receivable_ageing_summary, accounts_receivable_ageing_detail +4

## Entities (Models Eloquent)

- **`Account`** (tabela: `—`)
- **`AccountDetailType`** (tabela: `—`)
- **`AccountSubtype`** (tabela: `—`)
- **`AccountTransaction`** (tabela: `—`)
- **`AccountType`** (tabela: `—`)
- **`BankDetails`** (tabela: `—`)
- **`Barcode`** (tabela: `—`)
- **`BranchCapital`** (tabela: `—`)
- **`Brands`** (tabela: `—`)
- **`Budget`** (tabela: `—`)
- **`Business`** (tabela: `business`)
- **`BusinessLocation`** (tabela: `—`)
- **`CashRegister`** (tabela: `—`)
- **`CashRegisterTransaction`** (tabela: `—`)
- **`Category`** (tabela: `—`)
- **`ChartOfAccount`** (tabela: `chart_of_accounts`)
- **`ClientRelationship`** (tabela: `client_relationships`)
- **`ClientType`** (tabela: `client_types`)
- **`Contact`** (tabela: `—`)
- **`ContactRestriction`** (tabela: `—`)
- **`Country`** (tabela: `countries`)
- **`Currency`** (tabela: `currencies`)
- **`CustomerGroup`** (tabela: `—`)
- **`DashboardConfiguration`** (tabela: `—`)
- **`Discount`** (tabela: `—`)
- **`DocumentAndNote`** (tabela: `—`)
- **`ExpenseCategory`** (tabela: `—`)
- **`Gender`** (tabela: `—`)
- **`GroupSubTax`** (tabela: `—`)
- **`IncomeCategory`** (tabela: `—`)
- **`InvoiceLayout`** (tabela: `—`)
- **`InvoiceScheme`** (tabela: `—`)
- **`JournalEntry`** (tabela: `journal_entries`)
- **`KycIdentification`** (tabela: `kyc_identification`)
- **`MaritalStatus`** (tabela: `—`)
- **`Media`** (tabela: `—`)
- **`NotificationTemplate`** (tabela: `—`)
- **`PaymentAccount`** (tabela: `—`)
- **`PaymentDetail`** (tabela: `payment_details`)
- **`PaymentTermType`** (tabela: `—`)
- **`PaymentType`** (tabela: `payment_types`)
- **`Printer`** (tabela: `—`)
- **`Product`** (tabela: `—`)
- **`ProductRack`** (tabela: `—`)
- **`ProductVariation`** (tabela: `—`)
- **`Profession`** (tabela: `professions`)
- **`PurchaseLine`** (tabela: `—`)
- **`ReferenceCount`** (tabela: `—`)
- **`SellingPriceGroup`** (tabela: `—`)
- **`StockAdjustmentLine`** (tabela: `—`)
- **`System`** (tabela: `system`)
- **`TaxRate`** (tabela: `—`)
- **`Title`** (tabela: `titles`)
- **`Transaction`** (tabela: `—`)
- **`TransactionPayment`** (tabela: `—`)
- **`TransactionSellLine`** (tabela: `—`)
- **`TransactionSellLinesPurchaseLines`** (tabela: `—`)
- **`Transfer`** (tabela: `—`)
- **`TypesOfService`** (tabela: `—`)
- **`Unit`** (tabela: `—`)
- **`User`** (tabela: `—`)
- **`UserContactAccess`** (tabela: `—`)
- **`Variation`** (tabela: `—`)
- **`VariationGroupPrice`** (tabela: `—`)
- **`VariationLocationDetails`** (tabela: `—`)
- **`VariationTemplate`** (tabela: `—`)
- **`VariationValueTemplate`** (tabela: `—`)
- **`Warranty`** (tabela: `—`)
- **`WorkDetails`** (tabela: `—`)
- **`WorkStatus`** (tabela: `—`)

## Migrations

- `2019_07_07_093258_create_chart_of_accounts_table.php`
- `2019_07_07_093648_create_journal_entries_table.php`
- `2019_07_07_110645_create_payment_types_table.php`
- `2021_08_23_175321_add_contact_and_location_id_to_journal_entries_table.php`
- `2021_11_29_170819_add_business_id_to_chart_of_accounts_table.php`
- `2022_01_17_202319_create_payment_details_table.php`
- `2022_01_19_034231_create_countries_table.php`
- `2022_02_01_031031_create_transfers_table.php`
- `2022_02_03_215602_create_budgets_table.php`
- `2022_02_08_113906_add_opening_balance_to_chart_of_accounts_table.php`
- `2022_02_08_121045_add_currency_id_to_chart_of_accounts_table.php`
- `2022_02_09_002406_add_payment_type_id_to_chart_of_accounts_table.php`
- `2022_02_09_125328_create_account_detail_types_table.php`
- `2022_02_09_223848_create_account_subtypes_table.php`
- `2022_02_09_223849_add_account_subtype_id_and_detail_type_id_to_chart_of_accounts_table.php`
- `2022_02_23_130555_add_journal_entry_id_to_transactions_table.php`
- `2022_03_17_140457_add_reconcile_opening_balance_to_chart_of_accounts_table.php`
- `2022_04_11_163625_populate_account_subtypes_table.php`
- `2022_04_11_165143_populate_account_detail_types_table.php`
- `2022_06_08_105942_create_branch_capital_table.php`
- `2022_07_25_100234_change_payment_type_id_column_from_int_to_string_in_payment_details_table.php`

## Views (Blade)

**Total:** 91 arquivos

**Pastas principais:**

- `report/` — 30 arquivo(s)
- `transactions/` — 12 arquivo(s)
- `components/` — 11 arquivo(s)
- `budget/` — 8 arquivo(s)
- `layouts/` — 7 arquivo(s)
- `settings/` — 7 arquivo(s)
- `chart_of_account/` — 5 arquivo(s)
- `journal_entry/` — 4 arquivo(s)
- `reconcile/` — 4 arquivo(s)
- `transfers/` — 2 arquivo(s)
- `dashboard/` — 1 arquivo(s)

## Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — Injeta itens na sidebar admin
- **`superadmin_package()`** — Registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — Registra permissões Spatie no cadastro de Roles

## Permissões

**Registradas pelo módulo** (via `user_permissions()`):

- `accounting.chart_of_accounts.index`
- `accounting.chart_of_accounts.create`
- `accounting.chart_of_accounts.edit`
- `accounting.chart_of_accounts.destroy`
- `accounting.journal_entries.index`
- `accounting.journal_entries.create`
- `accounting.journal_entries.edit`
- `accounting.journal_entries.reverse`
- `accounting.reports.balance_sheet`
- `accounting.reports.trial_balance`
- `accounting.reports.income_statement`
- `accounting.reports.ledger`

**Usadas nas views** (`@can`/`@cannot`):

- `product.view`
- `accounting.chart_of_accounts.create`
- `accounting.chart_of_accounts.edit`
- `accounting.chart_of_accounts.destroy`
- `accounting.journal_entries.create`
- `accounting.journal_entries.reverse`
- `brand.view`
- `all_expense.access`
- `client.clients.client_types.create`
- `client.clients.client_types.edit`
- `client.clients.client_types.destroy`
- `purchase_order.create`
- `purchase.create`
- `direct_sell.view`
- `view_own_sell_only`
- `view_commission_agent_sell`
- `account.access`

## Peças adicionais

- **Seeders:** `AccountingDatabaseSeeder`

## Configuração (`Config/config.php`)

| Chave | Valor |
|---|---|
| `name` | `Accounting` |
| `module_version` | `1.3.1` |
| `pid` | `1` |
| `lic1` | `aHR0cHM6Ly9sLnBubi5zb2x1dGlvbnMvYXBpL3R5cGVfMQ==` |

## Dependências cross-module detectadas

_Referências a outros módulos encontradas no código PHP._

| Módulo referenciado | Ocorrências |
|---|---:|
| `Superadmin` | 1 |

## Assets (JS / CSS)

| Tipo | Qtde |
|---|---:|
| JavaScript (.js/.mjs) | 13 |
| TypeScript (.ts) | 0 |
| Vue SFC (.vue) | 0 |
| CSS/SCSS | 9 |
| Imagens | 4 |

- Build: **Laravel Mix** (webpack.mix.js presente)
- `package.json` presente
- **Deps JS:** `cross-env`, `laravel-mix`, `laravel-mix-merge-manifest`

**Frameworks/libs detectados no JS:** jQuery, Bootstrap, DataTables, Select2, SweetAlert, Toastr, Moment

**Arquivos JS** (primeiros 13):

- `js\accounting.js` (1.8 KB)
- `js\accounting_transactions.js` (90 B)
- `js\app.js` (0 B)
- `js\expense_transactions.js` (92.2 KB)
- `js\helper-functions.js` (766 B)
- `js\notification.js` (400 B)
- `js\plugins\datepicker.custom.js` (505 B)
- `js\plugins\form-wizard.js` (1.9 KB)
- `js\plugins\jquerytree\jquery.treegrid.bootstrap3.js` (166 B)
- `js\plugins\jquerytree\jquery.treegrid.init.js` (202 B)
- `js\plugins\jquerytree\jquery.treegrid.js` (21.1 KB)
- `js\plugins\sweetalert.custom.js` (1.8 KB)
- `js\purchase_payment.js` (43 KB)

**Arquivos CSS/SCSS** (primeiros 9):

- `css\box-shadow.css` (979 B)
- `css\plugins\bootstrap.custom.css` (2.7 KB)
- `css\plugins\form-wizard.css` (3.3 KB)
- `css\plugins\jquery.treegrid.css` (371 B)
- `css\plugins\money-fields.css` (135.4 KB)
- `css\plugins\sweetalert.custom.css` (40 B)
- `css\plugins\vue.custom.css` (199 B)
- `css\theme.custom.css` (3.5 KB)
- `sass\app.scss` (0 B)

## Presença em branches

| Branch | Presente |
|---|:-:|
| atual (6.7-react) | ✅ |
| `main-wip-2026-04-22` (backup Wagner) | ✅ |
| `origin/3.7-com-nfe` (versão antiga) | ❌ |
| `origin/6.7-bootstrap` | ✅ |

## Diferenças vs versões anteriores

### vs `origin/3.7-com-nfe`

- **Arquivos alterados:** 307
- **Linhas +:** 38370 **-:** 0
- **Primeiros arquivos alterados:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2019_07_07_093258_create_chart_of_accounts_table.php`
  - `Database/Migrations/2019_07_07_093648_create_journal_entries_table.php`
  - `Database/Migrations/2019_07_07_110645_create_payment_types_table.php`
  - `Database/Migrations/2021_08_23_175321_add_contact_and_location_id_to_journal_entries_table.php`
  - `Database/Migrations/2021_11_29_170819_add_business_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_01_17_202319_create_payment_details_table.php`
  - `Database/Migrations/2022_01_19_034231_create_countries_table.php`
  - `Database/Migrations/2022_02_01_031031_create_transfers_table.php`
  - `Database/Migrations/2022_02_03_215602_create_budgets_table.php`
  - `Database/Migrations/2022_02_08_113906_add_opening_balance_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_08_121045_add_currency_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_09_002406_add_payment_type_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_09_125328_create_account_detail_types_table.php`
  - `Database/Migrations/2022_02_09_223848_create_account_subtypes_table.php`
  - `Database/Migrations/2022_02_09_223849_add_account_subtype_id_and_detail_type_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_23_130555_add_journal_entry_id_to_transactions_table.php`
  - `Database/Migrations/2022_03_17_140457_add_reconcile_opening_balance_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_04_11_163625_populate_account_subtypes_table.php`
  - `Database/Migrations/2022_04_11_165143_populate_account_detail_types_table.php`
  - `Database/Migrations/2022_06_08_105942_create_branch_capital_table.php`
  - `Database/Migrations/2022_07_25_100234_change_payment_type_id_column_from_int_to_string_in_payment_details_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AccountingDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/Account.php`
  - `Entities/AccountDetailType.php`

### vs `main-wip-2026-04-22` (backup das customizações)

- **Arquivos alterados:** 307
- **Linhas +:** 38370 **-:** 0
- ⚠️ **Arquivos que podem conter customizações suas não trazidas para 6.7-react:**
  - `Config/.gitkeep`
  - `Config/config.php`
  - `Console/.gitkeep`
  - `Database/Migrations/.gitkeep`
  - `Database/Migrations/2019_07_07_093258_create_chart_of_accounts_table.php`
  - `Database/Migrations/2019_07_07_093648_create_journal_entries_table.php`
  - `Database/Migrations/2019_07_07_110645_create_payment_types_table.php`
  - `Database/Migrations/2021_08_23_175321_add_contact_and_location_id_to_journal_entries_table.php`
  - `Database/Migrations/2021_11_29_170819_add_business_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_01_17_202319_create_payment_details_table.php`
  - `Database/Migrations/2022_01_19_034231_create_countries_table.php`
  - `Database/Migrations/2022_02_01_031031_create_transfers_table.php`
  - `Database/Migrations/2022_02_03_215602_create_budgets_table.php`
  - `Database/Migrations/2022_02_08_113906_add_opening_balance_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_08_121045_add_currency_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_09_002406_add_payment_type_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_09_125328_create_account_detail_types_table.php`
  - `Database/Migrations/2022_02_09_223848_create_account_subtypes_table.php`
  - `Database/Migrations/2022_02_09_223849_add_account_subtype_id_and_detail_type_id_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_02_23_130555_add_journal_entry_id_to_transactions_table.php`
  - `Database/Migrations/2022_03_17_140457_add_reconcile_opening_balance_to_chart_of_accounts_table.php`
  - `Database/Migrations/2022_04_11_163625_populate_account_subtypes_table.php`
  - `Database/Migrations/2022_04_11_165143_populate_account_detail_types_table.php`
  - `Database/Migrations/2022_06_08_105942_create_branch_capital_table.php`
  - `Database/Migrations/2022_07_25_100234_change_payment_type_id_column_from_int_to_string_in_payment_details_table.php`
  - `Database/Seeders/.gitkeep`
  - `Database/Seeders/AccountingDatabaseSeeder.php`
  - `Database/factories/.gitkeep`
  - `Entities/Account.php`
  - `Entities/AccountDetailType.php`

## Gaps & próximos passos (preencher manualmente)

- [ ] Identificar customizações do Wagner (comparar com UltimatePOS 6.7 original)
- [ ] Listar bugs conhecidos no módulo
- [ ] Priorizar telas para migração React
- [ ] Marcar rotas que devem virar Inertia

---
**Gerado automaticamente por `ModuleSpecGenerator` em 2026-04-22 14:13.**
**Reaxecutar com:** `php artisan module:spec Accounting`
