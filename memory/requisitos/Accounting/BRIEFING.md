---
module: Accounting
status: legacy UltimatePOS contábil (núcleo comum — 70 entities + 12 controllers)
piloto: N/A (cross-business interno consumido por Vestuario/Financeiro/NfeBrasil/RecurringBilling)
last_review: 2026-05-16
owner: wagner
parent_adr: 0011
related_adrs: [0011, 0093, 0101, 0121, 0153, 0155, 0156]
nota_atual_v2: "~50/100 (injusto — D5 + D6.a Blade zero Inertia penalizados)"
nota_esperada_v3: "~70-80/100 pós-PR4 na_justified D5+D6.a declarados"
na_justified: [D5, "D6.a"]
---

# Accounting — BRIEFING

> 1-pager estado consolidado do módulo. Mantido por PR (skill `brief-update` Tier B).
> Última atualização: 2026-05-16 — Wave J Boost (51→meta 65) + Wave 5 re-try `na_justified` D5+D6.a

## O que é

Módulo contábil **CORE UltimatePOS herdado** — núcleo de **70 entities** vivas (`Modules/Accounting/Entities/`) que servem de espinha dorsal pra **Vestuario**, **Financeiro**, **NfeBrasil**, **RecurringBilling** e qualquer módulo que toque livro razão/balancete. É o módulo de **maior superfície** do núcleo comum (70 modelos × 12 controllers × suite de relatórios SPED-ready).

## Cliente piloto

- **ROTA LIVRE** (`business_id=4`, Larissa, Modules/Vestuario CNAE 4781-4/00) — usa Accounting de forma **transparente** via Sells/Compras (JournalEntry gerado automaticamente em vendas/compras pagas).
- Larissa não acessa a UI Accounting diretamente — consome via relatórios financeiros.

## Capacidades hoje

| Eixo | Estado |
|---|---|
| **Plano de contas** | ChartOfAccount + AccountType/Subtype/DetailType — 4-níveis padrão GAAP |
| **Lançamentos manuais** | JournalEntryController (debit/credit balanceados, reverse audit-trail) |
| **Relatórios** | Trial Balance · Balance Sheet · Cash Flow · P&L · Ledger · AR/AP Ageing · Budget Overview · Journal |
| **Budget** | BudgetController + BudgetService (financial year configurável via `business.fy_start_month`) |
| **Reconciliação** | ReconcileController (extrato bancário ↔ JournalEntry) |
| **Exports** | PDF + Excel 2007/xls/csv via Maatwebsite |
| **Audit log** | `activity()->log(...)` Spatie — 100% Create/Reverse JournalEntry |
| **Multi-tenant** | Filtro via JOIN `business_locations.business_id` (D1 5/30 — **GAP**: sem `BusinessScope` global em Entities) |

## Score module-grade (v3 pós-PR4)

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR4) | ~50/100 | Penalizava D5 (cliente externo) + D6.a (zero Inertia — UI 100% Blade legacy) |
| **v3 (pós-PR4)** | **~70-80/100** (esperado) | `na_justified` D5+D6.a declarados no SPEC → rubrica v3 redistribui pesos (ADR 0156) |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** núcleo contábil cross-business consumido pelos verticais via JournalEntry automático — Larissa biz=4 consome transparentemente sem UI direta. Sem cliente externo único piloto.
- **D6.a (Inertia::defer):** UI 100% Blade legacy UltimatePOS (12 Controllers chamam `view(...)`, zero `Inertia::render`). Migração Blade→Inertia não planejada (módulo candidato a `lifecycle: historical` se Vestuario/Financeiro encapsular relatórios próprios).

## Gaps catalogados (Wave J — meta 65)

| ID | Dimensão | Status |
|---|---|---|
| **D1** (5/30) | BusinessScope global em 70 Entities | **GAP** — Entities crus, escopo só na query do Controller (frágil) |
| **D4.a** (0/12) | Services thin (extração lógica fat-Controller) | **GAP** — JournalEntry/Trial Balance hoje vivem inline no Controller |
| D2 (Pest) | 3 testes Wave A (Smoke + MultiTenant + EntityBizConsistency) | OK |
| D3 (SPEC) | Existe | OK |
| D5 (BRIEFING) | Este doc | **NOVO** (Wave J) |

## Diferenciais vs mercado

- **70 entities reutilizadas** — não duplicar Account, ChartOfAccount, Transaction em outros módulos verticais.
- **Multi-currency** (Currency entity) — pronto pra OfficeImpresso legacy migration.
- **AccountSubtype/DetailType configuráveis por business** — não amarrado a 1 plano fixo.

## Próximos passos (Wave J entregas)

1. `JournalEntryService` thin — extrai `store()` + `reverse()` do Controller (testável isolado, mocka biz).
2. `TrialBalanceService` thin — encapsula query SQL dos 4 relatórios core (trial balance, balance sheet, P&L, cash flow) preservando filtros `business_id` via `business_locations`.
3. Pest `JournalEntryServiceTest` — 3 cenários: cria entrada balanceada · reverse marca `reversed=1` · isolamento biz=1 vs biz=99.

## Referências

- [SPEC.md](./SPEC.md) · [SCOPE.md](../../../Modules/Accounting/SCOPE.md)
- ADR 0093 (multi-tenant Tier 0) · ADR 0101 (tests biz=1) · ADR 0121 (modular vertical)
- ADR 0155 (Rubrica v3 `na_justified`) · ADR 0156 (Pesos redistribuídos v3)
- Entities (70): `Modules/Accounting/Entities/*.php`
- Controllers (12): `Modules/Accounting/Http/Controllers/*.php`
