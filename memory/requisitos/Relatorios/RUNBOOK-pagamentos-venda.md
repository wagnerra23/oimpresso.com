---
slug: relatorios-runbook-pagamentos-venda
title: "Relatórios — Runbook do relatório Pagamentos de venda"
type: runbook
module: Relatorios
tela: Relatorios/PagamentosVenda/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Pagamentos de venda

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`sell_payment_report`) ·
> 1º relatório com tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/sell-payment-report?tela=nova` responde Inertia `Relatorios/PagamentosVenda/Index`: os pagamentos
da mesma consulta do DataTable da Blade, 25 por página, e o total **da página** (como o rodapé da Blade, que soma
só as linhas visíveis). Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/PagamentosVendaReportPageTest.php`: linhas e total da página por três
   caminhos (o JSON do DataTable da Blade, as props da Page e a soma direta em `transaction_payments`), troco
   negativo, paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaPagamentosDeVenda()`, chamada pelos dois ramos, com os
   mesmos filtros e o mesmo `(int)` do #9008. O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (mesma ordem: pago em, desc).

## 4. Divergências declaradas

- Sem período escolhido mostra todos os pagamentos, como a Blade (o campo de data dela nasce vazio).
- As colunas de ação da Blade (ver pagamento, baixar documento, abrir a venda no modal) ficam para depois.
- A forma de pagamento mostra só o nome; o detalhe (nº do cheque, do cartão, da conta) fica para depois.

## 5. Falta para o cutover (F5 — decisão [W])

As ações da linha, o detalhe da forma, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
