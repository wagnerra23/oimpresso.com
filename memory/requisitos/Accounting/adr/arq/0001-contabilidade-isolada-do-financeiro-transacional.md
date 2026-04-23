# ADR ARQ-0001 (Accounting) · Contabilidade isolada do financeiro transacional

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

UltimatePOS já tem tabelas de transações financeiras (`transactions`, `transaction_payments`). Adicionar contabilidade formal (Chart of Accounts, Journal Entries, Reconciliation) criaria confusão: "o que é transação operacional vs. lançamento contábil?".

## Decisão

Módulo **Accounting** vive em tabelas próprias prefixadas `accounting_*`:
- `accounting_accounts` — plano de contas (assets, liabilities, equity, revenue, expense)
- `accounting_account_transactions` — lançamentos em partida dobrada
- `accounting_acc_trans_mappings` — bridge entre transações UltimatePOS e lançamentos contábeis
- `accounting_budget` — orçamentos

Transações do POS continuam em `transactions` (core) — Accounting **lê** e gera espelho contábil via bridge.

## Consequências

**Positivas:**
- Contabilidade formal sem bagunçar POS operacional.
- Contador pode trabalhar em contas sem quebrar fluxo de venda.
- Multi-entidade contábil (business_id) isolado.

**Negativas:**
- Sync entre transaction → account_transaction precisa ser trigger/job confiável.
- Estorno no POS vira journal entry de reversão (não pode deletar direto).

## Alternativas consideradas

- **Mesclar com `transactions`**: rejeitado (quebra isolamento, dificulta auditoria).
- **ERP externo (Odoo, etc.) via API**: overkill pro porte do UltimatePOS.
