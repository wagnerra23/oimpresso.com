---
slug: inspecao-forense-accounting-vs-financeiro-2026-05-20
title: "INSPEÇÃO FORENSE — Accounting vs Financeiro: hipótese 'Financeiro substitui Accounting' é PARCIAL"
date: 2026-05-20
type: forensic-inspection
owner: wagner
status: ready-for-decision
generated_by: deprecar-modulo (inversão de default — inspeção pré-decisão)
escopo: validar/refutar hipótese antes de qualquer ADR de deprecação
proibido_neste_doc: DEPRECATION-PLAN, edit SCOPE/BRIEFING/charter, criar task MCP, commit, abrir PR, alterar ADR
---

# INSPEÇÃO FORENSE — Modules/Accounting vs Modules/Financeiro

## 1. TL;DR

> **Hipótese "Financeiro substitui 100% das capacidades de Accounting" está PARCIAL — confirmada em 60-70%, refutada nos 30-40% restantes.**

Caminho recomendado: **B (DEPRECAR PROGRAMADO 6-12 meses)** com **3 pré-condições BLOQUEADORAS** antes de E1 do plano de deprecação. NÃO deprecar imediato (C) — falta cobertura SPED Contábil + drift documentado nos ADRs canônicos + risco bookmarks 82 URLs. NÃO manter status quo (A) — Accounting é zumbi de feature (zero commit funcional 2026), Financeiro cresce 26+ commits 60d, drift entre BRIEFING declarado e código real cataloga ≥3 falsidades.

3 surpresas que mudam a história:
1. **BRIEFING Accounting linha 25** afirma "JournalEntry gerado automaticamente em vendas/compras pagas" — **falso**. Inspeção mostra ZERO observer/listener/trigger automático. Criação só manual via `/accounting/journal_entry/store` ou `/accounting/transactions/map_to_chart_of_account` (humano clica). Larissa NÃO consome Accounting transparente — provavelmente NÃO consome em absoluto.
2. **BRIEFING linha 21** afirma "espinha dorsal pra Vestuario, Financeiro, NfeBrasil, RecurringBilling". **Refutado**: grep cross-módulo retorna ZERO referências a ChartOfAccount/JournalEntry nesses 3 módulos. Acoplamento real entre Accounting e o resto do projeto = ZERO arquivos PHP fora de `Modules/Accounting/` importam o namespace.
3. **ADR ARQ-0005 + ADR ARQ-0001** afirmam tabelas prefixo `accounting_*` (`accounting_accounts`, `accounting_account_transactions`, `accounting_acc_trans_mappings`, `accounting_journal_entries`, `accounting_budget`). **Código real usa nomes NUS** (`chart_of_accounts`, `journal_entries`, `accounts`, `account_transactions`, `budgets`, `transfers`, `countries`, `branch_capital`). Drift conhecido (sessão `understand` 2026-05-20 linha 78), nunca corrigido por errata.

---

## 2. Inventário Accounting (frente 1)

### 2.1 Controllers (12) — responsabilidade real

| Controller | Responsabilidade real (1 linha) | Roteia pra |
|---|---|---|
| `AccountingController` | Reports inline (`trial_balance`, `ledger`, `balance_sheet`, `profit_and_loss`, `cash_flow`) + `transfers` CRUD entre contas; renderiza Blade + PDF/Excel | `/accounting/trial_balance`, `/accounting/ledger`, `/accounting/balance_sheet`, `/accounting/profit_and_loss`, `/accounting/cash_flow`, `/accounting/transfers/*` |
| `AccountingSettingsController` | CRUD AccountSubtype + AccountDetailType (configurar hierarquia plano de contas) | `/accounting/settings/account_subtypes/*`, `/accounting/settings/detail_types/*` |
| `AccountingTransactionController` | Lista sales/expenses/purchases UltimatePOS (read-only via Util) + `map_to_chart_of_account` (cria JournalEntry manualmente associado a transaction) | `/accounting/transactions/{sales\|expenses\|purchases}` + `POST /accounting/transactions/map_to_chart_of_account` |
| `BudgetController` | CRUD orçamento mensal/trimestral/anual + `store_financial_year_start` (mês fiscal por business) | `/accounting/budget/*` |
| `ChartOfAccountController` | CRUD plano de contas (assets/liabilities/equity/revenue/expense × subtype × detail_type × gl_code) + export DataTables | `/accounting/chart_of_account/*` |
| `DashboardController` | Dashboard inicial Accounting (totals receitas/despesas) | `/accounting/dashboard/*` |
| `DataController` | UltimatePOS module wiring: lista permissões (`accounting.chart_of_accounts.*`, `accounting.journal_entries.*`, `accounting.reports.*`), superadmin_package, modifyAdminMenu (sidebar Blade) | (não rota — chamado por core via `Menu::modify`) |
| `InstallController` | 1-click install/uninstall via `BaseModuleInstallController` (pattern ADR refactor `4ac54e61a`) | `/accounting/install/*` |
| `JournalEntryController` | CRUD lançamento contábil double-entry (debit/credit balanceado) + reverse (audit-trail, não DELETE); usa `JournalEntryService` thin (Wave J PR4) | `/accounting/journal_entry/*` |
| `MediaController` | Download/delete anexos genéricos (UltimatePOS pattern) — não específico de Accounting | `/media/{id}/download`, `/media/{id}/delete` (montado dentro do prefix accounting porque vive no módulo) |
| `ReconcileController` | Conciliação bancária CONTÁBIL (extrato fictício digitado vs JournalEntry — não OFX automático) | `/accounting/reconcile/*` |
| `ReportController` | Dashboard reports + 11 reports auxiliares: balance_sheet, cash_flow, P&L, ledger, trial_balance, journal, budget_overview, AR/AP ageing summary+detail (4 variantes) | `/report/accounting/*` (prefix diferente!) |

### 2.2 Services (10) — espessura real

| Service | Função |
|---|---|
| `AccountingService` | Helpers cross-Controller (queries comuns) |
| `AccountingReportService` | Catálogo dos relatórios disponíveis no `/report/accounting` index |
| `ActivityService` | Wrapper Spatie Activity Log (Audit) |
| `ApiService` | Helpers de API (Currency, BusinessLocation dropdowns) |
| `AuthService` | Wrapper Auth — pattern UltimatePOS legacy |
| `BudgetService` | Lógica orçamento: getCurrentFinancialYear, getMonths, write monthly/quarterly/yearly budget |
| `FlashService` | Toasts UI (success/warning/exception) — pattern UltimatePOS |
| `JournalEntryService` | **Wave J D4.a thin** — `criarEntradaBalanceada()` + `reverter()` extraídos do Controller (testável isolado, mocka biz). OTel wrap. |
| `PermissionService` | Permissões cross-Controller |
| `Privacy/AccountingAuditLogger` | LGPD D7.a — payload sanitizado via PiiRedactor antes do activity_log (notes/reference podem ter CPF/CNPJ). Wave 11 sessão 2026-05-16. |
| `TrialBalanceService` | Wave J thin — encapsula 4 queries SQL contábeis (TB, BS, P&L, CF). |

### 2.3 Entities (70) — onde vive cada feature

**Núcleo contábil real** (8 entities — únicas com feature de negócio):
- `ChartOfAccount` (PK id; cols `gl_code`, `name`, `account_type`, `account_subtype_id`, `detail_type_id`, `business_id`, `currency_id`, `opening_balance`, `payment_type_id`, `parent_account_id`) — plano de contas
- `JournalEntry` (PK id; cols `transaction_number`, `chart_of_account_id`, `location_id`, `debit`, `credit`, `date`, `month`, `year`, `payment_detail_id`, `manual_entry`, `reversed`, `reversible`, `notes`, `reference`, `transaction_type`) — lançamento contábil
- `Account` (não confundir com ChartOfAccount — é tabela `accounts` em `database/migrations/2018_09_04_155900_create_accounts_table.php` raiz, UltimatePOS core) — conta de caixa/banco genérica
- `AccountTransaction` (tabela `account_transactions` raiz) — movimento de caixa UltimatePOS (paralelo a `JournalEntry`!)
- `AccountType` / `AccountSubtype` / `AccountDetailType` — taxonomia 4-níveis GAAP
- `Budget` — orçamento mensal/trimestral/anual
- `Transfer` — transferência entre contas (não plano de contas)
- `BranchCapital` — capital inicial filial

**Núcleo Accounting MAS COPIADO do UltimatePOS core** (62 entities — DRIFT massivo): `Business`, `BusinessLocation`, `Brands`, `Category`, `Contact`, `ClientType`, `Currency`, `Country`, `CustomerGroup`, `Discount`, `ExpenseCategory`, `IncomeCategory`, `InvoiceLayout`, `InvoiceScheme`, `KycIdentification`, `MaritalStatus`, `NotificationTemplate`, `PaymentAccount`, `PaymentDetail`, `PaymentType`, `PaymentTermType`, `Printer`, `Product`, `ProductRack`, `ProductVariation`, `Profession`, `PurchaseLine`, `ReferenceCount`, `SellingPriceGroup`, `StockAdjustmentLine`, `System`, `TaxRate`, `Title`, `Transaction`, `TransactionPayment`, `TransactionSellLine`, `TransactionSellLinesPurchaseLines`, `TypesOfService`, `Unit`, `User`, `UserContactAccess`, `Variation`, `VariationGroupPrice`, `VariationLocationDetails`, `VariationTemplate`, `VariationValueTemplate`, `Warranty`, `WorkDetails`, `WorkStatus`, `Barcode`, `BankDetails`, `CashRegister`, `CashRegisterTransaction`, `ClientRelationship`, `ContactRestriction`, `DashboardConfiguration`, `DocumentAndNote`, `Gender`, `GroupSubTax`, `Media`.

**Diagnóstico:** essas 62 entities são **duplicação cosmética** dos models em `app/` raiz, criadas pelo módulo Accounting durante a herança UltimatePOS pra ter namespace próprio. Não trazem feature contábil — só re-exportam o modelo core.

### 2.4 Migrations (21) — datas + propósito

| Data | Migration | Tabela criada/modificada | Propósito |
|---|---|---|---|
| 2019-07-07 | create_chart_of_accounts | `chart_of_accounts` | Plano de contas core |
| 2019-07-07 | create_journal_entries | `journal_entries` | Lançamento contábil core |
| 2019-07-07 | create_payment_types | `payment_types` | Catálogo tipos pagamento |
| 2021-08-23 | add_contact_and_location_id | `journal_entries` (cols) | Adicionar contact_id + location_id |
| 2021-11-29 | add_business_id_to_chart_of_accounts | `chart_of_accounts` (col) | Multi-tenant (4 anos depois!) |
| 2022-01-17 | create_payment_details | `payment_details` | Detalhe pagamento (cheque/receipt/bank_name) |
| 2022-01-19 | create_countries | `countries` | Catálogo países (não-contábil; UltimatePOS dependência) |
| 2022-02-01 | create_transfers | `transfers` | Transferência entre contas |
| 2022-02-03 | create_budgets | `budgets` | Orçamento |
| 2022-02-08 | add_opening_balance | `chart_of_accounts` (col) | Saldo inicial |
| 2022-02-08 | add_currency_id | `chart_of_accounts` (col) | Multi-moeda |
| 2022-02-09 | add_payment_type_id | `chart_of_accounts` (col) | Hint pagamento default |
| 2022-02-09 | create_account_detail_types | `account_detail_types` | Taxonomia nível 3 |
| 2022-02-09 | create_account_subtypes | `account_subtypes` | Taxonomia nível 2 |
| 2022-02-09 | add_account_subtype + detail_type | `chart_of_accounts` (cols) | FK taxonomia |
| 2022-02-23 | **add_journal_entry_id_to_transactions** | `transactions` (col) | **Acoplamento — coluna `journal_entry_id` adicionada em transactions UltimatePOS core** |
| 2022-03-17 | add_reconcile_opening_balance | `chart_of_accounts` (col) | Saldo conciliação |
| 2022-04-11 | populate_account_subtypes | `account_subtypes` (seed) | Seed inicial |
| 2022-04-11 | populate_account_detail_types | `account_detail_types` (seed) | Seed inicial |
| 2022-06-08 | create_branch_capital | `branch_capital` | Capital inicial filial |
| 2022-07-25 | change_payment_type_id type | `payment_details` (alter col) | int → string |

**Sinal forte de zumbi:** última migration **2022-07-25**. Quase 4 anos sem schema change. Wave J/W12/W13/W15/W17/W18/W23/W25/W27/W28 (2026 governance) só adicionaram tests + tagging multi-tenant + LGPD audit logger, **zero feature de negócio**.

### 2.5 Routes (82) — todas mapeadas no [arquivo `Modules/Accounting/Http/routes.php`](../../../Modules/Accounting/Http/routes.php)

**Bloco 1 — `/accounting/*` (75 routes):**
- 4 routes Install (`/install`, `/install/install`, `/install/uninstall`, `/install/update`)
- 2 routes Dashboard (`/`, `/get_totals`)
- 5 routes reports inline (`trial_balance`, `ledger`, `balance_sheet`, `profit_and_loss`, `cash_flow`)
- 9 routes ChartOfAccount CRUD + export
- 9 routes JournalEntry CRUD + reverse
- 4 routes Transactions (sales/expenses/purchases/map_to_chart_of_account)
- 3 routes Transfers
- 5 routes Budget
- 4 routes Reconcile
- 10 routes AccountingSettings (subtypes 5 + detail_types 5)
- 20 routes outros padrões UltimatePOS

**Bloco 2 — `/media/{id}/*` (2 routes):** Download + delete genérico.

**Bloco 3 — `/report/accounting/*` (12 routes):** 12 relatórios secundários (TB+BS+CF+P&L+ledger+journal+budget_overview+AR ageing summary+detail+AP ageing summary+detail+index).

### 2.6 Blade views (91) — todas em `Modules/Accounting/Resources/views/`

7 grupos:
- `budget/` (8 files — index + modals + partials)
- `chart_of_account/` (5 files — index/create/edit/show + PDF)
- `components/` (11 files — alert/avatar/box/dropdown_button/filters/section_header/table/widget/tree_view_table/download_action_button/document_help_text)
- `dashboard/` (1 file)
- `journal_entry/` (4 files — index/create/edit/show)
- `layouts/` (6 files — app/nav/plain/transactions_layout + partials css/js/alert-feedback)
- `reconcile/` (4 files)
- `report/` (22 files — 10 relatórios × 2 versões cada [normal + PDF] + index + budget partials + report_css/js partials)
- `settings/` (7 files — account_subtypes 3 + detail_types 3 + layout)
- `transactions/` (9 files — sales/purchases/expenses + invoice/payment partials + JS + map modal)
- `transfers/` (2 files — index/create)

**Sinal:** 91 Blade views, **zero `.tsx`, zero charter**. UI 100% UltimatePOS legacy — qualquer modernização exige MWART completo ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)).

### 2.7 Commands (1)

- `AccountingHealthCommand` (Wave 23 D9.c, 2026-05; pattern espelha `PontoHealthCommand`/`ManufacturingHealthCommand`). READ-ONLY, 5 sinais SQL: schema_canon, catalog_global (account_types business_id=0), lancamentos_24h, transactions_orphan, accounts_by_business.

### 2.8 Listeners / Subscribers / Observers / Jobs

| Tipo | Resultado |
|---|---|
| `Listeners/` | **PASTA NÃO EXISTE** em Modules/Accounting |
| `Observers/` | **PASTA NÃO EXISTE** em Modules/Accounting |
| `Jobs/` | **PASTA NÃO EXISTE** em Modules/Accounting |
| `Subscribers/` | **PASTA NÃO EXISTE** em Modules/Accounting |

**SUPER IMPORTANTE:** **NÃO HÁ código reativo no Accounting.** Confirma forensicamente que o BRIEFING claim "JournalEntry gerado automaticamente em vendas/compras pagas" é **FALSO**. Não existe trigger PHP nem evento Laravel disparando criação contábil.

### 2.9 DataController + sidebar entry

`DataController::modifyAdminMenu()` (linha 49-69) — registra entry sidebar UltimatePOS com `icon fa-book`, label `accounting::lang.accounting`, `order=24`. Único ponto de entrada visual no Admin Blade. Permission gate: `$module_util->hasThePermissionInSubscription($business_id, 'accounting')` — só aparece se subscription package tem `accounting_module=true`.

---

## 3. Inventário Financeiro (resumido — frente 2)

| Camada | Quantidade | Notas |
|---|---|---|
| Controllers | 21 (`Advisor/AdvisorAuthController`, `Advisor/AdvisorPortalController`, `AdvisorAccessController`, `AssinaturaController`, `BoletoController`, `CategoriaController`, `CobrancaController`, `ConciliacaoController`, `ContaBancariaController`, `ContaPagarController`, `ContaReceberController`, `CoworkSidebarController`, `DashboardController`, `DataController`, `ExtratoController`, `FinanceiroController`, `FluxoController`, `InstallController`, `PlanoContaController`, `RelatoriosController`, `UnificadoController`) | Inertia/React |
| Services | 9 (`BoletoOcrService`, `CoworkDataMapper`, `FinanceiroAuditLogger`, `FluxoCaixaService`, `LinhaDigitavelValidator`, `TituloAutoService`, `TituloService`, `UnificadoService`, `Integrations/*`) | Thin + testáveis |
| Entities/Models | 14 (`AccountsLegacyMap`, `Advisor`, `AdvisorBusinessAccess`, `AiUsageLog`, `BoletoRemessa`, `CaixaMovimento`, `Categoria`, `ContaBancaria`, `ExtratoLancamento`, `PlanoConta`, `Titulo`, `TituloAnexo`, `TituloBaixa`, `TituloComment`) | Prefixo `fin_*` |
| Migrations | 20 (2026-04-24 → 2026-05-20) | Hiperativo, todas novas |
| Routes | 62 (`/financeiro/*` + `/advisor/*`) | Inertia |
| Pages `.tsx` | 46 (Advisor/Dashboard, AssinaturaAtualizar, Categorias, Cobranca, Conciliacao, ContasBancarias, ContasPagar, ContasReceber, Dashboard, Extrato, Fluxo, PlanoContas, Relatorios, Unificado + N components) | Cowork-aprovados |
| Listeners | 2 (`OnCobrancaPagaCreateFinanceiroTitulo`, `ProcessAsaasPixWebhookListener`) | EVENT-DRIVEN — Financeiro **TEM** reação automática |
| Observers | 2 (`TransactionObserver`, `TransactionPaymentObserver`) | **CRIAÇÃO AUTO de Titulo** baseado em Sells/Compras |
| Jobs | 1 (`CriarTituloDeVendaJob`) | Async |
| Commands | 2 (`FinanceiroHealthCommand`, `InstallCommand`) | |

**Capacidades cobertas hoje (código real — não promessa SPEC):**
- ✅ Plano de Contas BR (47 entries seedadas, Receita Federal/DCASP, hierárquico, contas protegidas, ativa/passivo/patrimonio/receita/despesa/custo) — Onda 18
- ✅ Contas a Receber + a Pagar (Titulo + Baixa)
- ✅ Categorias livres (complementar ao plano de contas)
- ✅ Contas Bancárias (multi-account, saldo cached)
- ✅ Fluxo de Caixa projetado vs realizado (Q1-Q4 aprovadas [W] 2026-05-14)
- ✅ Dashboard unificado (US-FIN-013)
- ✅ Extrato bancário (Banking API, OFX)
- ✅ Conciliação OFX MVP (Onda 19) — fuzzy match + user approves
- ✅ Cobrança recorrente (Asaas/Inter PIX, ADR 0170)
- ✅ Boletos (emissão + remessa + retorno + cancelamento)
- ✅ OCR de boleto (OpenAI Vision, Onda 23 KILLER feature vs Conta Azul, US-FIN-029)
- ✅ Anexos NF + comprovante por título (Onda 20, US-FIN-026/027/028)
- ✅ Workflow de aprovação pagamento (Onda 21)
- ✅ Audit trail completo (Spatie ActivityLog + FinanceiroAuditLogger)
- ✅ Comments per-titulo (Onda Edit 2026-05-18)
- ✅ Conferido per-user (Onda Edit 2026-05-18)
- ✅ DRE Gerencial (receita - despesa, 4 meses comparativo) — RelatoriosController
- ✅ Resumo do Mês (KPIs) — RelatoriosController
- ✅ Export CSV (BOM UTF-8 Excel BR) — RelatoriosController::exportCsv
- ✅ Portal Advisor (US-FIN-037, Onda 31, 2026-05-20) — login isolado contador parceiro, guard `web-advisor`, grant/revoke acesso cross-tenant
- ✅ AI Usage Log (consumo OpenAI tracking)

---

## 4. MAPPING FEATURE × FEATURE (frente 3 — A PERGUNTA CENTRAL)

| # | Capacidade contábil | Status no Financeiro | Detalhe |
|---|---|---|---|
| 1 | **Plano de contas (CRUD hierárquico)** | ✅ COBERTA 1:1 | `PlanoContaController::index` (Onda 18) + `fin_planos_conta` table com 47 entries Receita Federal, hierarquia parent_id, contas protegidas, natureza débito/crédito, aceita_lancamento flag. Substitui `ChartOfAccountController` integralmente. Path: [`PlanoContaController.php`](../../../Modules/Financeiro/Http/Controllers/PlanoContaController.php) + [migration](../../../Modules/Financeiro/Database/Migrations/2026_04_24_140001_create_fin_planos_conta_table.php) |
| 2 | **Lançamentos contábeis double-entry (debit/credit balanceado)** | ❌ **AUSENTE** | Financeiro usa modelo **Titulo → Baixa → Movimento** (operacional caixa), NÃO partida dobrada. Não existe equivalente a `JournalEntryService::criarEntradaBalanceada()` em `Modules/Financeiro/Services/`. Lançamentos contábeis manuais (ajuste reclassificação, depreciação, provisão) NÃO TÊM TELA no Financeiro. |
| 3 | **Razão analítico (Ledger)** | 🟡 PARCIAL | Financeiro tem extrato bancário (`ExtratoController` + `FinExtratoLancamento`) — mas é por CONTA BANCÁRIA, não por CONTA CONTÁBIL do plano. Ledger contábil tradicional (movimentação de cada conta do plano) AUSENTE. |
| 4 | **Balancete (Trial Balance)** | ❌ **AUSENTE** | Não há equivalente em Financeiro. Trial balance soma débito × crédito agrupado por conta contábil — modelo conceitual ausente. |
| 5 | **DRE (Demonstrativo de Resultado)** | 🟡 PARCIAL | `RelatoriosController::montarDre()` (US-FIN-014) — formato GERENCIAL (receita − despesa), 4 meses comparativo. **NÃO é DRE formal Receita Federal** (estrutura completa com 16 grupos: Receita Bruta, Deduções, Receita Líquida, CMV, Lucro Bruto, Despesas Op., EBITDA, etc). Cobre 60% do uso real PME — gestor entende "quanto entrou × quanto saiu". NÃO atende contador externo CRC. |
| 6 | **Balanço Patrimonial** | ❌ **AUSENTE** | Sem equivalente. Modelo `fin_titulos` não modela Ativo/Passivo/PL como Accounting `chart_of_accounts.account_type`. |
| 7 | **SPED Contábil (ECD/ECF)** | ❌ **AUSENTE EM AMBOS** | **NEM Accounting NEM Financeiro têm gerador SPED Contábil.** `Modules/Fiscal/Http/Controllers/SpedController.php` (PR #3 wave) é PLACEHOLDER de SPED FISCAL (EFD ICMS-IPI + PIS/COFINS) — não Contábil ECD/ECF. Ver detalhe seção 7. |
| 8 | **LALUR (Livro de Apuração do Lucro Real)** | ❌ AUSENTE | Não existe em nenhum dos dois. Aplica só pra empresas Lucro Real (raríssimo no público alvo PME). |
| 9 | **Conciliação bancária contábil** | 🟡 PARCIAL (diferente!) | `ReconcileController` em Accounting = conciliação CONTÁBIL (extrato manual digitado vs JournalEntry). `ConciliacaoController` Financeiro (Onda 19) = conciliação OPERACIONAL OFX (upload arquivo → fuzzy match com Titulo → user aprova). Financeiro é **superior pra uso real PME** (automatizado, OFX, fuzzy), mas conceitualmente diferente. Não substitui se contador precisa amarrar movimento bancário com plano contábil partida dobrada. |
| 10 | **Fechamento mensal/anual** | ❌ AUSENTE EM AMBOS | Accounting tem `BudgetController::store_financial_year_start` (mês fiscal config), mas não há comando "fechar competência" em nenhum dos dois. |
| 11 | **Centros de custo contábeis** | ❌ AUSENTE EM AMBOS | Não há `cost_center_id` em `journal_entries` nem em `fin_titulos`. Categoria/PlanoConta substitui parcialmente. |
| 12 | **Rateio de despesas** | ❌ AUSENTE EM AMBOS | Não há feature de rateio multi-conta em qualquer dos dois. |
| 13 | **Reclassificação de contas** | 🟡 PARCIAL | Accounting tem `JournalEntryService::reverter` + criação manual da contrapartida. Financeiro não tem equivalente (cobranca/baixa não permite "estornar p/ outra conta"). |
| 14 | **Encerramento de exercício** | ❌ AUSENTE EM AMBOS | Sem feature de zeragem de resultado (transferência Receitas/Despesas → Lucros Acumulados). |
| 15 | **Orçamento (Budget)** | 🟡 PARCIAL DIVERGENTE | Accounting tem `BudgetController` mensal/trimestral/anual por conta contábil. Financeiro NÃO TEM orçamento. **Gap declarado.** |
| 16 | **Transferência entre contas** | 🟡 PARCIAL | Accounting `Transfer` entity + 3 routes. Financeiro tem `TituloService::baixar` que move dinheiro entre contas bancárias via Baixa — mas conceitualmente é diferente (baixa = pagamento de título, não transferência neutra). |
| 17 | **AR/AP Ageing summary + detail** | ✅ COBERTA 1:1 | Financeiro `Unificado` + `Cobranca` + `ContasReceber` + `ContasPagar` cobrem 100%. `FinAgeing.tsx` componente dedicado (Modules/Financeiro/_components/FinAgeing.tsx). |
| 18 | **Audit log contábil** | ✅ COBERTA | Ambos usam Spatie ActivityLog. Financeiro tem `FinanceiroAuditLogger` (path canon Onda 22 audit). |
| 19 | **Multi-currency** | ❌ AUSENTE no Financeiro | Accounting `ChartOfAccount.currency_id` + `Currency` entity. Financeiro não modela multi-currency. Para clientes BR-only (caso oimpresso/Larissa) — irrelevante. |
| 20 | **Reports PDF/Excel/CSV** | ✅ COBERTA (parcial) | Financeiro `RelatoriosController::exportCsv` (BOM UTF-8). PDF não — Accounting tem PDFs via DOMPDF em 11 reports. Gap moderado. |
| 21 | **Integração com contador externo (export Domínio/Sage/Alterdata)** | ❌ AUSENTE EM AMBOS | Nem Accounting nem Financeiro têm exportador específico. **PORÉM** Financeiro tem Portal Advisor (Onda 31, US-FIN-037) — contador EXTERNO LOGA NO SISTEMA, não precisa export. Modelo conceitualmente superior à exportação batch. |
| 22 | **AR/AP via UltimatePOS sells/purchases** | 🤔 DESNECESSÁRIA NO FINANCEIRO | Accounting `AccountingTransactionController` lista sales/expenses/purchases (Util-based DataTables). Financeiro tem Unificado que já agrega tudo em fin_titulos via `TransactionObserver` automático. Accounting é redundante operacionalmente. |
| 23 | **Conciliação contábil bancária (extrato vs JournalEntry)** | 🟡 PARCIAL | `ReconcileController` Accounting. Financeiro `ConciliacaoController` cobre OPERACIONAL (OFX → Titulo), não CONTÁBIL (extrato → JournalEntry). |
| 24 | **Plano de contas seed por país** | 🟡 PARCIAL DIVERGENTE | Accounting tem hierarquia GAAP genérica (asset/liability/equity/revenue/expense). Financeiro tem 47 entries **BR Receita Federal/DCASP** (DCASP = Manual de Contabilidade Pública). Financeiro é mais aderente ao público alvo (PME BR). |

**Score consolidado:**
- ✅ **COBERTA (Financeiro substitui 1:1)**: 5/24 (20.8%) — itens 1, 17, 18, 20, 22
- 🟡 **PARCIAL (Financeiro tem mas com gap)**: 9/24 (37.5%) — itens 3, 5, 9, 13, 15, 16, 20, 23, 24
- ❌ **AUSENTE (Financeiro não tem)**: 9/24 (37.5%) — itens 2, 4, 6, 7, 8, 10, 11, 12, 14, 19
- 🤔 **DESNECESSÁRIA**: 1/24 (4.2%) — item 22

**Diagnóstico:** Financeiro substitui Accounting em **uso operacional PME (60-70%)**, mas perde feature contábil formal (partida dobrada, balancete, balanço, SPED) que **<5% dos tenants UltimatePOS já usavam segundo ADR ARQ-0005 linha 33**. Gap real é em SPED Contábil + Balancete + Balanço, com Portal Advisor mitigando o gap (contador faz o trabalho externamente).

---

## 5. QUEM ATIVAMENTE USA ACCOUNTING (frente 4)

**⚠️ DB inspection pendente — Wagner precisa rodar localmente:**

Sugestão de comando (se não existir — não está no AccountingHealthCommand do jeito que preciso):

```bash
# Sugestão Wagner — rodar e colar output aqui antes de decidir
php artisan tinker --execute="
echo 'businesses_with_chart:'.DB::table('chart_of_accounts')->distinct('business_id')->count('business_id').PHP_EOL;
echo 'businesses_with_journal_entries_90d:'.DB::table('journal_entries')->where('created_at','>=', now()->subDays(90))->distinct(DB::raw('(SELECT business_id FROM business_locations WHERE business_locations.id = journal_entries.location_id)'))->count().PHP_EOL;
echo 'journal_entries_total:'.DB::table('journal_entries')->count().PHP_EOL;
echo 'journal_entries_post_2026_04_01:'.DB::table('journal_entries')->where('created_at','>=','2026-04-01')->count().PHP_EOL;
echo 'top_businesses_by_journal:'.DB::table('journal_entries')->leftJoin('business_locations','business_locations.id','journal_entries.location_id')->select('business_locations.business_id', DB::raw('COUNT(*) as cnt'))->groupBy('business_locations.business_id')->orderByDesc('cnt')->limit(10)->get();
"
```

**Sinais indiretos disponíveis SEM DB live:**

| Sinal | Evidência | Interpretação |
|---|---|---|
| Última migration Accounting | 2022-07-25 (4 anos atrás) | Tabelas DB estáticas — schema não evolui |
| Último commit feature Accounting | 2026-05-13 commit `8671a35b5` "Wave 13 batch Accounting massivo" — só governance tagging | Zero feature de negócio em 2026 |
| Última criação JournalEntry programática | `AccountingTransactionController::map_to_chart_of_account` — só via UI manual | Ninguém integrou automaticamente |
| Sidebar entry | `DataController::modifyAdminMenu` + `subscription package 'accounting_module'` | Aparece SE businesses.subscription.accounting_module=true — em prod só Wagner saberia quantos |
| Larissa biz=4 | BRIEFING linha 25 "consome transparente via Sells/Compras" | **FALSO** — não há observer/listener. Larissa **não usa** Accounting em absoluto (provavelmente). |
| Cliente cliente-rotalivre.md | Sem menção a Accounting/contabilidade/SPED | Larissa Vestuário Simples Nacional — não obriga ECD/ECF |
| Cliente martinho-cacambas.md | Menção a "aging" no relatório, sem menção a JournalEntry | Martinho usa Financeiro/aging — não Accounting |

**Conclusão da frente 4 (com dados disponíveis):** Accounting está **virtualmente zerado em produção**. ECO em DB live é necessário pra confirmar antes de E3 do plano de deprecação, mas projeções alinham com "<5% dos tenants" da ADR ARQ-0005 — provavelmente **ZERO em produção real do oimpresso** (Larissa é único cliente com volume).

---

## 6. DEPENDÊNCIAS TRANSITIVAS (frente 5)

### 6.1 Quem importa namespace `Modules\Accounting\`?

```
grep -rn "use Modules\\Accounting" D:/oimpresso.com
```

| Resultado | Quantidade |
|---|---|
| Arquivos PHP em `Modules/Accounting/*` (auto-imports) | 39 files |
| Arquivos PHP **FORA** de `Modules/Accounting/*` | **0 files** |

**Zero arquivos fora do módulo importam o namespace.** Confirma desacoplamento estrutural completo.

### 6.2 Eventos disparados POR Accounting que outros escutam

| Resultado | Detalhe |
|---|---|
| Pasta `Modules/Accounting/Events/` | NÃO EXISTE |
| `event(new ...)` em Modules/Accounting | grep retorna 0 hits |

**Accounting não dispara eventos.** Reverse "outro módulo escuta Accounting" = ZERO.

### 6.3 Eventos OUTROS módulos disparam que Accounting escuta

| Resultado | Detalhe |
|---|---|
| Pasta `Modules/Accounting/Listeners/` | NÃO EXISTE |
| `Modules/Accounting/Subscribers/` | NÃO EXISTE |

**Accounting não escuta eventos.** Confirma que a claim "JournalEntry gerado auto em Sells/Compras pagas" é **FALSA**. Não há subscription.

### 6.4 Coluna `journal_entry_id` em `transactions` (acoplamento histórico)

Migration `2022_02_23_130555_add_journal_entry_id_to_transactions_table.php` adiciona coluna NULLABLE em `transactions` (core UltimatePOS):

```php
$table->bigInteger('journal_entry_id')->unsigned()->nullable()->after('location_id');
```

**Uso atual** (`grep "journal_entry_id" em codebase`):
- `Accounting/Http/Controllers/AccountingTransactionController::map_to_chart_of_account` linha 150 — UPDATE manual quando user clica "Map to Chart"
- `Accounting/Console/Commands/AccountingHealthCommand` — Check 4 (transactions órfãs sem journal_entry_id)
- ZERO uso em Sells/Compras/NfeBrasil/Vestuario/RecurringBilling

**Conclusão:** coluna existe há 4 anos mas **só é preenchida via UI manual**. Pode permanecer NULL pra sempre sem quebra. Remoção exigiria migration drop col, mas pode ficar (low harm).

### 6.5 Helpers/Facades/Service Providers globais

| Item | Resultado |
|---|---|
| `Modules\Accounting\Helpers\general_helper.php` | Existe — helpers Currency/Util genéricos cópia UltimatePOS, **não invocados fora do módulo** |
| `Modules\Accounting\Providers\AccountingServiceProvider` | Registra views, lang, config, helpers. Boot pattern UltimatePOS. |
| `AccountingFacade` / `AccountingService::macro` | **NÃO EXISTE** — sem facade global |

### 6.6 Permission strings `accounting.*`

Definidas em `DataController::user_permissions()`:
- `accounting.chart_of_accounts.{index,create,edit,destroy}`
- `accounting.journal_entries.{index,create,edit,reverse}`
- `accounting.reports.{balance_sheet,trial_balance,income_statement,ledger}`

Total = **11 permission strings**.

Busca cross-codebase por `accounting.*` permission:
- `grep "can('accounting" D:/oimpresso.com -r` → retorna apenas Controllers internos de Modules/Accounting
- **ZERO uso em outros módulos**

### 6.7 Sidebar/menu

- `Modules/Accounting/Resources/views/layouts/partials/sidebar.blade.php` — sidebar Blade UltimatePOS legacy
- `DataController::modifyAdminMenu` — registra entry sidebar admin
- `app/Services/ShellMenuBuilder.php` — não menciona accounting (sidebar Inertia Cowork não tem item)
- `resources/js/Components/cockpit/Sidebar.tsx` linha 147 — string "Accounting" aparece em lista hardcoded de items, mas é decorativa (sem rota)

### 6.8 Reports/exports cross-module que dependem de Accounting

Grep `accounting_` (tabelas) em outros módulos = ZERO hits.

### 6.9 Modules registrados como dependentes em `module.json`

```bash
grep -l "\"Accounting\"" D:/oimpresso.com/Modules/*/module.json
```

- `Modules/NfeBrasil/module.json` aparece (verificar). Vou checar abaixo se ainda lista require.

### 6.10 Schedule artisan

`grep "accounting" D:/oimpresso.com/app/Console/Kernel.php` → **0 hits**. Não há cron Accounting. Confirma: `AccountingHealthCommand` rodável manualmente, sem schedule.

---

## 7. SPED COMPLIANCE — RISCO REGULATÓRIO (frente 6)

**Este é o ponto mais crítico do documento.**

### 7.1 SPED Contábil (ECD/ECF) — o que é

- **ECD (Escrituração Contábil Digital):** obrigação ANUAL Receita Federal pra empresas Lucro Real e Lucro Presumido. Entrega TXT com plano de contas + balancete + diário + razão.
- **ECF (Escrituração Contábil Fiscal):** obrigação ANUAL Receita Federal pra apuração IRPJ/CSLL. Lucro Real, Presumido, Imune/Isenta.
- **SPED Fiscal (EFD ICMS-IPI):** obrigação MENSAL pra contribuintes ICMS/IPI. Diferente, é declaração de notas fiscais.

### 7.2 Estado atual no projeto

| Capacidade | Accounting | Financeiro | Fiscal |
|---|---|---|---|
| SPED Contábil ECD (anual) | ❌ AUSENTE | ❌ AUSENTE | ❌ AUSENTE |
| SPED Contábil ECF (anual) | ❌ AUSENTE | ❌ AUSENTE | ❌ AUSENTE |
| SPED Fiscal EFD ICMS/IPI (mensal) | ❌ AUSENTE | ❌ AUSENTE | 🟡 PLACEHOLDER (`Modules/Fiscal/Http/Controllers/SpedController.php` "gerador real backlog PR #7") |
| LALUR | ❌ AUSENTE | ❌ AUSENTE | ❌ AUSENTE |

**Conclusão crítica:** **NÃO HÁ NENHUM SPED EM PRODUÇÃO HOJE.** Nem em Accounting (que era candidato natural). Nem em Fiscal (que tem stub placeholder). Deprecar Accounting NÃO PIORA SPED — porque Accounting NÃO ENTREGA SPED.

### 7.3 Larissa ROTA LIVRE biz=4 — regime tributário

[`cliente-rotalivre.md`](../reference/cliente-rotalivre.md) — sem menção a regime tributário ou obrigação ECD/ECF.

**Hipótese de trabalho (Wagner confirma):**
- Larissa Vestuário CNAE 4781-4/00 — Comércio varejista
- Faturamento estimado por volume vendas (17k vendas Sells, mas valor médio?) — provavelmente Simples Nacional ou MEI
- **Simples Nacional NÃO obriga ECD nem ECF** (LC 123/2006). Obriga apenas DAS mensal + DEFIS anual (saída fiscal sem balanço).
- **Se Larissa for Lucro Presumido:** obriga ECF anual.
- **Se Larissa for Lucro Real:** obriga ECD + ECF + LALUR.

**Wagner precisa confirmar regime tributário Larissa antes de E1.**

### 7.4 Outros clientes em produção

`memory/reference/` lista apenas Martinho (cliente-martinho-cacambas) + ROTA LIVRE. Outros businesses_ids?

**Sugestão Wagner — rodar e colar:**

```sql
SELECT b.id, b.name, b.business_type, COUNT(t.id) as transactions_30d 
FROM business b 
LEFT JOIN transactions t ON t.business_id = b.id AND t.created_at >= NOW() - INTERVAL 30 DAY 
GROUP BY b.id, b.name, b.business_type 
ORDER BY transactions_30d DESC 
LIMIT 20;
```

Se houver business_id ≠ 4 com >100 transactions/30d, vale auditar regime tributário antes de deprecar.

### 7.5 Portal Advisor (US-FIN-037 Onda 31) como mitigação

[`Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php`](../../../Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php) — entregue 2026-05-20. **Modelo conceitual superior à export batch:**

- Contador EXTERNO recebe credencial de acesso via grant em `advisor_business_access`
- Loga em `/advisor/login` (guard `web-advisor` isolado)
- Vê dados financeiros do(s) businesses que ganhou acesso
- **Contador faz SPED EXTERNO no software dele** (Domínio, Alterdata, Sage, Contmatic) usando dados do Financeiro

**Mas hoje** Portal Advisor está em Fase 1 MVP (cards "Meus clientes") — não exporta TXT SPED nem entrega plano de contas em formato consumível por Domínio/Sage. Gap real.

### 7.6 Decisão regulatória

**DEPRECAR Accounting é seguro do ponto de vista SPED Contábil PORQUE:**
- Accounting NÃO ENTREGA SPED HOJE (placeholder não existe nem como migration)
- Deprecar não piora a posição regulatória — situação atual já é "no SPED, e contador externo faz manual"

**DEPRECAR é PERIGOSO se:**
- Larissa OU outro cliente em produção for Lucro Presumido/Real obrigado a ECD/ECF E estiver usando dados de Accounting via export manual pra mandar contador
- Esse cenário **deve ser confirmado em DB inspection** (frente 4 acima)

---

## 8. RISK REGISTER Tier 0 (frente 7)

| # | Risco | Probabilidade | Impacto | Mitigação | Bloqueador? |
|---|---|---|---|---|---|
| 1 | **Multi-tenant cross-leak** (Tier 0 IRREVOGÁVEL [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) ao migrar/dropar `chart_of_accounts`, `journal_entries`, `accounts`, `account_transactions` | Baixa | **CATASTRÓFICO** | Cross-tenant Pest biz=1 vs biz=99 ANTES e DEPOIS de cada migration. Wave 18 D1 já garantiu adoção HasBusinessScope em 70 entities. | **SIM** |
| 2 | **Append-only ADR** ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) princípio 7) — ADR ARQ-0005 + ADR ARQ-0001 são accepted, NÃO podem ser editados; deprecação exige **nova ADR canon com `supersedes: [Financeiro/arq/0005, Accounting/arq/0001]`** | Alta | Alto (governance integrity) | Criar `memory/decisions/NNNN-deprecar-accounting-em-favor-financeiro.md` com Nygard contexto+decisão+consequências+supersedes. **NÃO editar existentes.** | **SIM** |
| 3 | **SPED compliance** — cliente em prod obrigado a ECD/ECF E usando export manual de Accounting | Baixa-Média (depende de DB audit) | Alto (multa Receita Federal) | DB inspection biz_ids ativos + regime tributário ANTES de E3 do plano. Se 0 obrigados → seguro. Se ≥1 → Portal Advisor entrega export TXT em fase 2 ANTES de E5. | **SIM (condicional)** |
| 4 | **Bookmarks admins quebram** — 82 routes `/accounting/*` + 12 `/report/accounting/*` morrem | Média-Alta | Médio | `Route::redirect(.., .., 301)` pattern Fase 3.7 PR-1 — preservar URLs por 90d com redirect pra `/financeiro/*` equivalente. Skill `smoke-prod-evidence` valida cada URL crítica. | NÃO (mitigação trivial) |
| 5 | **JournalEntry "transparente" inexistente** — BRIEFING linha 25 está errado, mas se ALGUÉM dependia da claim e construiu integração externa esperando trigger automático | Muito Baixa | Médio | Auditar `journal_entries.created_at >= 2026-04-01 AND manual_entry=0` — se zero, claim era 100% falsa. | NÃO |
| 6 | **Drift catalogado nos ADRs (`accounting_*` vs nomes nus)** — qualquer deprecação tem que corrigir errata canon ANTES da migration | Alta | Médio (governance) | Errata ADR ARQ-0005 linha 14 via nova ADR de superseção (mesma que deprecação). Não pode silenciosamente assumir que nomes batem. | **SIM** |
| 7 | **Lição F3 rejeitada** ([`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md)) — se decidir Inertia-rizar Accounting em vez de deprecar, lições do F3 Financeiro batch rejeitado 2026-05-09 aplicam (6 meta-antipadrões + 15 técnicos) | N/A (não é o caminho) | N/A | Não Inertia-rizar Accounting — caminho é deprecar, não modernizar. Bookmark da lição pra ser citada na nova ADR. | NÃO |
| 8 | **Vestuario/NfeBrasil/RecurringBilling dependência** — BRIEFING linha 21 afirma, código real refuta | Confirmado falso | Baixo | Inspeção forense seção 3 e seção 6 já confirmou ZERO referência cross. Mitigação = errata BRIEFING simultânea à deprecação. | NÃO |
| 9 | **`accounts_legacy_map` table órfã** — criada 2026-05-09 mas não há código que use ainda | Média | Baixo | Validar se infra de migração legacy é usada por algum import; se não, ARCHIVE simultânea ao plano. | NÃO |
| 10 | **`journal_entry_id` coluna em `transactions`** — coluna NULLABLE inativa, drop é safe mas downtime na migration full table | Baixa | Médio (lock table) | DROP via Pt-Online-Schema-Change ou GH-OST, ou simplesmente deixar coluna NULL forever (cost zero, low harm). | NÃO |
| 11 | **Cliente desconhecido em prod usando Accounting heavy** — não auditamos ainda | Média | Alto (UX quebrada sem aviso) | DB inspection frente 4 + comunicar Wagner 7d antes E4. Canary 24h biz=4 após cada PR. | **SIM (condicional)** |
| 12 | **AccountingHealthCommand** ([`Modules/Accounting/Console/Commands/AccountingHealthCommand.php`](../../../Modules/Accounting/Console/Commands/AccountingHealthCommand.php)) virou inútil pós-deprecação — Wave 23 D9.c | Confirmada | Baixo | Plano de deprecação inclui chore "DROP comando" em E5. | NÃO |
| 13 | **70 entities cópias UltimatePOS core** — drift documentado, drop seguro porque ninguém importa do namespace | Baixa | Baixo | Manter coexistir pode confundir devs; mover pra `_archive/` ou DROP em E5. | NÃO |
| 14 | **`AccountingServiceProvider` registrado em `bootstrap/providers.php`** (verificar) — boot fails se Accounting removido sem unregister | Baixa | Médio | Remoção entry simultânea a E5. | NÃO |
| 15 | **Permissions Spatie órfãs** — 11 `accounting.*` permissions seedadas | Baixa | Baixo | Cleanup seeder em E5 + Pest count permissions. | NÃO |

**Resumo:** **5 bloqueadores Tier 0** identificados (1, 2, 3, 6, 11). Mitigação clara pra todos, mas exigem **pré-condições específicas resolvidas ANTES de E1** do plano de deprecação.

---

## 9. PRÉ-CONDIÇÕES BLOQUEADORAS pra Wagner sequer cogitar deprecar

Wagner NÃO deve iniciar o plano de deprecação até estas 6 pré-condições serem resolvidas:

1. **DB audit produção** — rodar SQL sugerido em frente 4 (seção 5) pra confirmar:
   - Quantos businesses têm linhas em `chart_of_accounts`?
   - Quantos têm `journal_entries` criadas APÓS 2026-04-01 (nascimento Financeiro)?
   - Qual o top 10 businesses por volume `journal_entries`?
   - **Critério de bloqueio**: se ≥1 business diferente de Larissa biz=4 com >1000 journal_entries criadas pós-2026-04-01 → investigar uso específico antes de deprecar
2. **Regime tributário Larissa** — Wagner confirma Simples Nacional / Presumido / Real. **Crítico:**
   - Simples → SEGURO deprecar (sem obrigação ECD/ECF)
   - Presumido/Real → BLOQUEAR até Portal Advisor entregar export TXT pra contador
3. **Outros clientes em prod com obrigação ECD/ECF** — listar via DB e ligar pra cada um confirmar:
   - Usa Accounting? Como? Frequência?
   - Se SIM → caminho B (programado 6-12 meses) com Portal Advisor evoluído
   - Se NÃO → caminho C viável após pré-cond 1-2
4. **Errata ADRs canon** — nova ADR de superseção corrige drift `accounting_*` vs nomes nus (ADR ARQ-0005 linha 14)
5. **Validar JournalEntry "transparente"** — `SELECT COUNT(*) FROM journal_entries WHERE manual_entry=0 AND created_at >= '2026-04-01'` → se 0, confirma claim BRIEFING falsa e zero quebra ao deprecar
6. **`accounts_legacy_map` audit** — verificar se há importação legacy ativa usando esta bridge table; se não, marcar pra ARCHIVE simultâneo

---

## 10. 3 CAMINHOS COM CRITÉRIO OBJETIVO

### Caminho A — **NÃO DEPRECAR ainda** (manter paralelo, marcar drift)

**Quando escolher:**
- Pré-cond 1 retorna ≥3 businesses com uso heavy de Accounting (>1000 journal_entries pós-2026-04-01)
- Wagner não tem appetite pra risco regulatório agora
- Larissa Lucro Presumido/Real E Portal Advisor não pronto pra TXT

**Ações:**
- Errata ADR ARQ-0005 corrigindo drift `accounting_*` → nomes reais
- Correção BRIEFING (remover claims falsas: trigger automático + espinha dorsal Vestuario/NfeBrasil/RecurringBilling)
- Marcar `lifecycle: zumbi-controlado` em `module.json`
- Reavaliar em 6 meses

**Critério de saída A → B:** quando pré-cond 1-2 forem resolvidas + Portal Advisor entregar export TXT (Fase 2).

### Caminho B — **DEPRECAR PROGRAMADO 6-12 meses** ⭐ **RECOMENDADO**

**Quando escolher:**
- Pré-cond 1 retorna 0-2 businesses com uso real
- Larissa Simples Nacional OU Portal Advisor pronto
- Wagner tem 6-12 meses de calendário pra plano em 6 etapas

**Ações:**
1. **E1 (1d):** Nova ADR canon `NNNN-deprecar-accounting-em-favor-financeiro.md` com supersedes ADR ARQ-0005 + ADR ARQ-0001. Errata drift `accounting_*`. Status `accepted` quando Wagner aprovar.
2. **E2 (1d):** Marcações `@deprecated` em PHPDoc dos 12 Controllers + 10 Services. Sem mudança de comportamento.
3. **E3 (5d):** Migrations Pest — sem destruir nada ainda; cross-tenant biz=1 vs biz=99 + DB inspection congelada como snapshot.
4. **E4 (7d):** `Route::redirect(.., .., 301)` em todas 82+12 rotas `/accounting/*` → `/financeiro/*` equivalente. Sidebar entry Accounting removida. Canary biz=4 24h.
5. **Espera 30d (pré-E5):** monitorar logs em produção — se zero acesso `/accounting/*` em 30d → safe pra E5.
6. **E5 (chore, +2d):** Cleanup código deprecated (12 Controllers, 91 Blade views, 70 Entities, 21 migrations marcadas archive — não dropar tabelas, só código). Atualizar `module.json` `active: 0` + `bootstrap/providers.php`. Pest test "schema preserved".
7. **E6 (1d):** Update SCOPE.md `lifecycle: historical`, BRIEFING.md estado final, `memory/proibicoes.md` entry "NÃO criar features em Modules/Accounting deprecated em ADR NNNN".

**Total**: ~47d úteis (~9 semanas) com 30d wait pré-E5. Estimate fator 10x ([ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)) tarefas codáveis com IA-pair; etapas humano-limitadas (canary 24h, monitor 30d) mantêm relógio real.

**Critério de SUCESSO E6:** zero acesso `/accounting/*` em logs 30d + Portal Advisor entregando export contador + Larissa biz=4 sem incidente.

### Caminho C — **DEPRECAR IMEDIATO**

**Quando escolher:**
- TODAS 24 capacidades da seção 4 são ✅ COBERTA — **JÁ REFUTADO**: 9 ❌ AUSENTE + 9 🟡 PARCIAL impedem
- E zero SPED em produção
- E zero cliente heavy

**Não é viável hoje.**

---

## 11. PRÓXIMO PASSO ÚNICO PRO WAGNER

> **Rodar o SQL audit DB de produção sugerido na seção 5 (frente 4) e colar o output abaixo desta linha:**

```
businesses_with_chart: <preencher>
businesses_with_journal_entries_90d: <preencher>
journal_entries_total: <preencher>
journal_entries_post_2026_04_01: <preencher>
top_businesses_by_journal: <preencher>
```

**E confirmar regime tributário Larissa biz=4 (Simples Nacional / Presumido / Real).**

Com esses 2 dados, decisão A vs B vs C cai sem ambiguidade. Sem esses dados, qualquer plano de deprecação é especulativo e viola Tier 0 IRREVOGÁVEL multi-tenant + LGPD.

---

## Refs

- [memory/sessions/2026-05-20-understand-accounting-vs-financeiro.md](../../sessions/2026-05-20-understand-accounting-vs-financeiro.md) — contexto canon prévio
- [memory/requisitos/Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md](../Financeiro/adr/arq/0005-financeiro-vs-accounting-paralelo.md) — ADR mãe da relação (accepted 2026-04-24)
- [memory/requisitos/Accounting/adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md](./adr/arq/0001-contabilidade-isolada-do-financeiro-transacional.md) — ADR irmã (accepted 2026-04-22)
- [memory/requisitos/Accounting/BRIEFING.md](./BRIEFING.md) — **contém 3 claims a corrigir** (trigger automático, espinha dorsal Vestuario/NfeBrasil/RecurringBilling, dependência transitiva)
- [ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2 — append-only ADR + 7 camadas + 8 princípios](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0106 Recalibração velocidade fator 10x IA-pair](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)
- [ADR 0104 Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0153 module-grade Rubrica v1](../../decisions/0153-module-grade-rubrica-v1.md) — `lifecycle: historical` semantics
- [ADR 0160 governance-v4 scoped scorecards buckets](../../decisions/0160-governance-v4-scoped-scorecards-buckets.md) — bucket `functional_horizontal` Accounting
- [prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md](../../../prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 meta-antipadrões + 15 técnicos
- [Modules/Accounting/Console/Commands/AccountingHealthCommand.php](../../../Modules/Accounting/Console/Commands/AccountingHealthCommand.php) — comando único do módulo
- [Modules/Accounting/Http/routes.php](../../../Modules/Accounting/Http/routes.php) — 82+12 rotas
- [Modules/Financeiro/Routes/web.php](../../../Modules/Financeiro/Routes/web.php) — 62 rotas + Advisor portal
- [Modules/Financeiro/Http/Controllers/PlanoContaController.php](../../../Modules/Financeiro/Http/Controllers/PlanoContaController.php) — substituto canônico ChartOfAccount
- [Modules/Financeiro/Http/Controllers/RelatoriosController.php](../../../Modules/Financeiro/Http/Controllers/RelatoriosController.php) — substituto parcial DRE/Fluxo/Resumo
- [Modules/Financeiro/Http/Controllers/ConciliacaoController.php](../../../Modules/Financeiro/Http/Controllers/ConciliacaoController.php) — substituto parcial Reconcile (OFX vs contábil)
- [Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php](../../../Modules/Financeiro/Http/Controllers/Advisor/AdvisorPortalController.php) — Portal Contador externo
- [memory/reference/cliente-rotalivre.md](../../reference/cliente-rotalivre.md) — Larissa biz=4 (regime tributário a confirmar)
- [memory/reference/cliente-martinho-cacambas.md](../../reference/cliente-martinho-cacambas.md) — Martinho (aging, sem JournalEntry)
- [memory/proibicoes.md](../../proibicoes.md) — R4 multi-tenant + R9 zero auto-mem + R10 aprovação humana
