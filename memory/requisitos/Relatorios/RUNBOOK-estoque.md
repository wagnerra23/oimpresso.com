---
slug: relatorios-runbook-estoque
title: "Relatórios — Runbook do relatório de estoque"
type: runbook
module: Relatorios
tela: Relatorios/Estoque/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de estoque

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`stock_report`) · tabela paginada
> no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/stock-report?tela=nova` responde Inertia `Relatorios/Estoque/Index`: uma linha por variação × local (SKU,
produto, variação, categoria, local, preço de venda, estoque, valor pela compra, valor pela venda, lucro potencial,
vendido, transferido, ajustado), das mesmas linhas de `ProductUtil::getProductStockDetails` que o DataTable usa, 25 por
página, e o rodapé da página. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `stock_report.view` (a da Blade). O preço de venda só com `access_default_selling_price`; as colunas de valor só
com `view_product_stock_value` — as mesmas regras da Blade. Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/EstoqueReportPageTest.php`: estoque e valores por três caminhos (o JSON do DataTable
   da Blade, as props da Page e a conta à mão), as permissões das colunas, a paginação e o isolamento 98 × 99.
3. **F3** — os filtros saem do ramo `ajax()` para `filtrosDoEstoque()` e as contas das colunas de valor para
   `valorDoEstoquePorVenda()` e `lucroPotencialDoEstoque()`. O DataTable e a tela nova chamam os mesmos métodos.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a Blade (ordem: SKU, crescente).

## 4. Divergências declaradas e achados

- **Fechado em 2026-10-27:** o resumo do topo (estoque final pela compra e pela venda, lucro potencial, margem) vem de
  `resumoDoEstoque()`, a conta que o JSON da Blade (`/reports/get-stock-value`) também usa, adiado e só com
  `view_product_stock_value`; e os filtros de subcategoria e "só fabricação" entraram (UC-RES-06..08). Antes desta data
  eles ficavam para o PR seguinte.
- **Colunas de valor no JSON (corrigido em 2026-10-08):** o JSON do DataTable (`/reports/stock-report` ajax) devolvia as
  colunas de valor (pela compra, pela venda, lucro potencial) a qualquer usuário com `stock_report.view`; a Blade só as
  escondia na tela (`@can('view_product_stock_value')`). Agora vêm `null` sem `view_product_stock_value`; quem tem a
  permissão recebe os mesmos números. Teste: UC-RES-05.

## 5. Falta para o cutover (F5 — decisão [W])

Aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente. O resumo e os filtros do §4 fecharam em 2026-10-27;
pela regra D1 [W] 2026-10-07, o cutover pelo menu segue o molde do `getPurchaseSell`.
