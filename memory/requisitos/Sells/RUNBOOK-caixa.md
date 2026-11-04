---
slug: sells-runbook-caixa
title: "Sells — Runbook da tela Caixa do dia /vendas/caixa"
type: runbook
module: Sells
tela: Sells/Caixa/Index
status: ativo
owner: W
last_validated: "2026-11-04"
preconditions:
  - "Permissão direct_sell.view, view_own_sell_only ou view_commission_agent_sell"
  - "Sessão com user.business_id (Tier 0, ADR 0093)"
steps:
  - "Ler o charter e o casos.md da tela"
  - "Conferir a âncora com ancora.mjs"
  - "Mudar só leitura e layout; fechamento segue no Blade"
  - "Provar valores por dois caminhos antes de mexer em agregação"
related_adrs: [0093-multi-tenant-isolation-tier-0, 0104-processo-mwart-canonico-unico-caminho]
---

# RUNBOOK — Caixa do dia (`/vendas/caixa`)

> **Tela:** `resources/js/Pages/Sells/Caixa/Index.tsx` · charter [`Index.charter.md`](../../../resources/js/Pages/Sells/Caixa/Index.charter.md) · casos [`Index.casos.md`](../../../resources/js/Pages/Sells/Caixa/Index.casos.md)
> **Rota:** `GET /vendas/caixa` → `SellController@inertiaCaixa`
> **Âncora de design:** `prototipo-ui/cowork/Wagner/vendas-extras.jsx` · função `VendasCaixaPage` (linhas 172-413, medido em 2026-11-04). Confira com `node scripts/design/ancora.mjs Sells/Caixa/Index --staging prototipo-ui/cowork`.
> **Inventário visual:** [`Caixa-r1-visual-comparison.md`](Caixa-r1-visual-comparison.md)

Até 2026-11-04 esta tela não tinha RUNBOOK próprio. O hook `block-mwart-violation` a deixava passar porque casava `RUNBOOK-index.md`, que é da lista de vendas. O hook procura `RUNBOOK-caixa.md` (kebab do subdiretório `Caixa`) e o `related_runbook:` do charter.

## 1. Objetivo

Leitura do caixa do dia: faturado, vendas por forma de pagamento, vendas por origem (balcão, oficina, online), estado do caixa do usuário, movimentos do turno e conferência física. A tela convive com `/cash-register/*` em Blade, preservado por decisão [W] de 2026-05-25.

## 2. O que esta tela NÃO faz (e o RUNBOOK não autoriza mudar)

- Abrir caixa, fechar caixa e "Imprimir Z" continuam indo para o Blade (`/cash-register/create`, `/cash-register/close-register/{id}`, `/cash-register/register-details`).
- A conferência física grava pelo `POST /cash-register/close-register` existente. Nenhuma rota de fechamento nova.
- Sangria/suprimento e histórico de caixas fechados ficam fora (charter, Non-Goals).

## 3. Props do controller

| Prop | Origem | Carga |
|---|---|---|
| `totalDia`, `countDia` | `transactions` venda `final` do dia, `business_id` explícito | imediata (2026-11-04) |
| `porFormaPagamento` | `transaction_payments` join `transactions`, sem `is_return` | imediata (2026-11-04) |
| `porOrigem` | `COALESCE(transactions.source,'balcao')` + até 10 refs de OS por origem | imediata (2026-11-04) |
| `caixaAberto`, `cashRegisterId` | `cash_registers` aberto do usuário no business | closure memoizada |
| `turno` | `CashRegisterUtil::getRegisterDetails` (o mesmo do Blade) | `Inertia::defer` |

A troca de data recarrega só as props do dia (`only:`), sem refazer `caixaAberto` nem `turno`.

## 4. Mudança em agregação = regra mestre de valor

Qualquer PR que toque o cálculo ou a forma de carga de `totalDia`, `countDia`, `porFormaPagamento`, `porOrigem` ou `turno` segue [`proibicoes.md` §Regra mestre](../../proibicoes.md):

1. prova por dois caminhos (payload JSON antes × depois, e soma manual sobre uma amostra biz=1);
2. tabela antes → depois no corpo do PR;
3. aprovação [W] antes do merge.

Os testes de contrato estão em `tests/Feature/Sells/SellsCaixaContratoTest.php` (UC-SCAIXA-01..08) e rodam no CT 100, tenant 98.

## 5. Mudança de layout

1. Medir os dois lados com a mesma sonda: `node scripts/design/protocolo.config.mjs` (fase 1) e `node scripts/design/design-diff.mjs --probe`, mesmo tema, 1280px.
2. Registrar o delta no inventário visual antes de editar a Page.
3. Não mexer nos botões de abrir, fechar e Imprimir Z.
4. Smoke biz=1 em produção com medida do DOM depois do deploy.
