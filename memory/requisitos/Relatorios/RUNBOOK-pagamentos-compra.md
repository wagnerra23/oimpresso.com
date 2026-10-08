---
slug: relatorios-runbook-pagamentos-compra
title: "Relatórios — Runbook do relatório Pagamentos de compra"
type: runbook
module: Relatorios
tela: Relatorios/PagamentosCompra/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Pagamentos de compra

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`purchase_payment_report`) ·
> 2º relatório com tabela paginada no servidor (reusa `Relatorios/_shared/Paginacao.tsx` do #9016).

## 1. Objetivo

`GET /reports/purchase-payment-report?tela=nova` responde Inertia `Relatorios/PagamentosCompra/Index`: os pagamentos
da mesma consulta do DataTable da Blade, 25 por página, e o total **da página** (como o rodapé da Blade, que soma
só as linhas visíveis). Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/PagamentosCompraReportPageTest.php`: linhas e total da página por três
   caminhos (o JSON do DataTable da Blade, as props da Page e a soma direta em `transaction_payments`),
   paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaPagamentosDeCompra()`, chamada pelos dois ramos, com os
   mesmos filtros e o mesmo `(int)` do #9008. O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (mesma ordem: pago em, desc).

## 4. Divergências declaradas

- Sem período escolhido mostra todos os pagamentos, como a Blade (o campo de data dela nasce vazio).
- As colunas de ação da Blade (ver pagamento, baixar documento, abrir a compra no modal) ficam para depois.
- A forma de pagamento mostra só o nome; o detalhe (nº do cheque, do cartão, da conta) fica para depois.

## 5. Falta para o cutover (F5 — decisão [W])

As ações da linha, o detalhe da forma, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
