---
name: Financeiro — Onda 1 + Onda 2 mergeadas em 6.7-bootstrap (2026-04-25 fim de tarde)
description: Tudo mergeado. Categorias CRUD + integration test + fix baixa automática + purchase. 49 tests Pest PASS. Numeração mudou pra R/P000001. CriarTituloDeVendaJob @deprecated. Backfill purchases legadas pendente.
type: project
originSessionId: 3d07367c-170f-4f24-a317-c57ccf4fe557
---
# Módulo Financeiro — estado em 2026-04-25 fim de tarde

## TL;DR

Onda 1 do Financeiro foi mergeada em `6.7-bootstrap` de manhã, mas o integration test E2E (escrito à tarde) descobriu que o **ciclo de baixa não funcionava**. À tarde, **3 agents em worktrees isolados** entregaram fix completo, e tudo foi **mergeado em `6.7-bootstrap`** (5 merge commits + build commit em sequência: categorias → integration-test → onda2-baixa-automatica → cms-react-redesign → vite rebuild).

Estado atual: 49 tests Pest PASS, 6.7-bootstrap atualizado, **NADA pushado pro remote ainda** (deploy a Hostinger é decisão separada).

## Mergeado em `6.7-bootstrap` (manhã 2026-04-25)

**6 PRs (#4-#9):** Inertia v3, backend MVP, contas-bancarias, contas-receber, contas-pagar, boletos.

Backend: 7 migrations + 8 models + BoletoStrategy + CnabDirectStrategy mock (21 bancos, fork local lib eduardokum) + TituloService + TituloAutoService + TransactionObserver no core.

5 telas Inertia v3 + React 19: dashboard, contas-bancarias, contas-receber, contas-pagar, boletos.

## Mergeado em `6.7-bootstrap` (tarde 2026-04-25)

| Merge commit | Conteúdo | Tests adicionados |
|---|---|---|
| `a2d9c133 merge: feat/financeiro-categorias` | Tela `/categorias` CRUD, color picker, plano_conta_id opcional | 7 |
| `0f2ef178 merge: feat/financeiro-integration-test` | Integration test E2E + audit doc + CHANGELOG | 9 (6 PASS + 3 SKIP iniciais) |
| `602e5917 merge: feat/financeiro-onda2-baixa-automatica` | Fix BUG-1/2/3: Observer payment + Service refatorado, R/P000001 | 7 + reabilita 3 SKIPs |
| `3546b37d merge: claude/cms-react-redesign` | (não-Financeiro) CMS landing Inertia/React | — |
| `015a4df3 build(vite): rebuild apos merges` | Build artifacts atualizados | — |

**Total módulo Financeiro: 49 tests Pest PASS** após todos os merges.

## Bugs descobertos pelo integration test (Onda 2 entregou fix de 3 dos 4)

Detalhe completo em `memory/requisitos/Financeiro/audits/2026-04-25-bugs-integration-test.md`.

- 🔴 **BUG-1/2** — `transaction_payment` não criava `TituloBaixa` nem `CaixaMovimento`. **FIXED** em `feat/financeiro-onda2-baixa-automatica`: novo `TransactionPaymentObserver` + métodos `registrarPagamento/cancelarPagamento` no Service + coluna `transaction_payment_id` em `fin_titulo_baixas` (UNIQUE pra idempotência).
- 🟡 **BUG-3** — `purchase` não gerava Titulo a pagar. **FIXED**: `sincronizarDeVenda` virou `sincronizarDeTransacao`, suporta sell+purchase, numeração `R/P000001` migrada do Job órfão pro Service. `CriarTituloDeVendaJob` marcado `@deprecated`.
- ℹ️ **BUG-4** — `due → paid` marca Titulo como `cancelado` (não `quitado`). **NÃO FIXED** (intencional, fora do escopo da Onda 2; cosmético).

## Mudanças relevantes pra deploy (impacto da Onda 2)

1. **Numeração de Títulos mudou.** Onda 1 gravava `numero = (string)tx.id` (ex: `"42"`). Onda 2: `R000001/P000001/...` sequencial business-isolado com `lockForUpdate`. Frontend exibe `numero` mas não compara — sem regressão visível.

2. **Compras geram Titulo automaticamente** — antes ignoradas. Se ROTA LIVRE ou outro business tem purchases legadas em `due` no DB, **backfill será necessário** em deploy. Comando dedicado (`financeiro:backfill-titulos`) sugerido mas não implementado — sai do escopo.

3. **Pagamento via UltimatePOS (`/sells/{id}/payment`, `addPayment`) dispara cadeia automática:**
   - cria `fin_titulo_baixas` com `transaction_payment_id` linkando (idempotente)
   - atualiza `fin_titulos.valor_aberto` + `status`
   - cria `fin_caixa_movimentos` (entrada/saída)
   - Larissa nem percebe — fluxo dela não muda

4. **TransactionPayment delete estorna automaticamente** (append-only, TECH-0002 respeitado): row negativa em `fin_titulo_baixas` + ajuste oposto em `fin_caixa_movimentos`.

5. **Pre-requisito**: business precisa de pelo menos 1 row em `fin_contas_bancarias` antes do 1º pagamento sincronizado. ROTA LIVRE (biz=4) já tem.

6. **Deploy precisa**: rodar `composer dump-autoload` pra ver `TransactionPaymentObserver`.

## Pendências (Onda 3+ ou cleanup)

- **Push pro remote + deploy Hostinger** quando Wagner decidir
- **Backfill** purchases legadas em `due` (command `financeiro:backfill-titulos` sugerido, não implementado)
- **Deletar `CriarTituloDeVendaJob`** @deprecated após ~2 semanas de validação em prod
- BUG-4 cosmético (status `quitado` em vez de `cancelado`)
- `saldo_apos` em CaixaMovimento setado como 0 (precisa service de saldo dedicado)
- Caixa projetado (US-FIN-007), juros+multa (R-FIN-006)

## Refs

- `memory/requisitos/Financeiro/PLANO_DETALHADO.md`
- `memory/requisitos/Financeiro/CHANGELOG.md` (atualizado)
- `memory/requisitos/Financeiro/audits/2026-04-25-bugs-integration-test.md`
- ADR 0024 (Inertia+UPos), TECH-0001 (idempotência), TECH-0002 (append-only), TECH-0003 (mock CNAB), ARQ-0005 (paralelo a Accounting)
