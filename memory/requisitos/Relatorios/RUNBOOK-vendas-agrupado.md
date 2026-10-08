---
slug: relatorios-runbook-vendas-agrupado
title: "Relatórios — Runbook do relatório Vendas por produto, aba Agrupado"
type: runbook
module: Relatorios
tela: Relatorios/VendasAgrupado/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Vendas por produto, aba "Agrupado"

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`product_sell_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/product-sell-grouped-report?tela=nova` responde Inertia `Relatorios/VendasAgrupado/Index`: uma linha por
variação por dia (produto, SKU, dia, estoque atual, quantidade vendida, subtotal), da mesma consulta do DataTable da aba
"Agrupado" da Blade, 25 por página, e o rodapé da página como o da Blade. Sem `?tela=nova`, o endpoint segue sendo só o
JSON do DataTable.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/VendasAgrupadoReportPageTest.php`: quantidade e subtotal agrupados por três caminhos
   (o JSON do DataTable da Blade, as props da Page e a conta direta em `transaction_sell_lines`), a paginação e o
   isolamento 98 × 99.
3. **F3** — a consulta (com o filtro de local do estoque atual, que ficava no topo do método) sai do ramo `ajax()` para
   `consultaVendasAgrupado()`. O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a aba da Blade (ordem: SKU, decrescente).

## 4. Divergências declaradas

- Tela própria, não aba: o hub de Relatórios (decisão D2 do [W]) ainda não existe.
- Período só por data, com o horário padrão da Blade (00:00 a 23:59). Filtro por produto fica para depois.
- A regra do rodapé de subtotal da Blade (só a linha cuja `parent_sell_line_id` agregada é nula) foi preservada como está.

## 5. Falta para o cutover (F5 — decisão [W])

O hub (D2), os itens do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
