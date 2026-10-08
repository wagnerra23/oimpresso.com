---
slug: relatorios-runbook-vendas-com-compra
title: "Relatórios — Runbook do relatório Vendas por produto, aba Detalhado com compra"
type: runbook
module: Relatorios
tela: Relatorios/VendasComCompra/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Vendas por produto, aba "Detalhado com compra"

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`product_sell_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/product-sell-report-with-purchase?tela=nova` responde Inertia `Relatorios/VendasComCompra/Index`: uma linha
por vínculo entre o item vendido e o item de compra que o abasteceu (produto, cliente, venda, data, compra, lote,
fornecedor, quantidade), da mesma consulta do DataTable da aba "Detalhado com compra" da Blade, 25 por página. Sem
`?tela=nova`, o endpoint segue sendo só o JSON do DataTable da Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/VendasComCompraReportPageTest.php`: quantidade por três caminhos (o JSON do
   DataTable da Blade, as props da Page e a conta direta em `transaction_sell_lines_purchase_lines`), o estoque inicial,
   a paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaVendasComCompra()`, chamada pelos dois ramos. O ramo
   `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a aba da Blade (ordem: data da venda, decrescente).

## 4. Divergências declaradas

- Tela própria, não aba: o hub de Relatórios com grupos (decisão D2 do [W]) ainda não existe.
- Período só por data, com o horário padrão da Blade (00:00 a 23:59). Filtro por produto e link da venda ficam para depois.

## 5. Falta para o cutover (F5 — decisão [W])

O hub (D2), os itens do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
