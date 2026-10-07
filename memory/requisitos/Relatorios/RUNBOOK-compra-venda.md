---
slug: relatorios-runbook-compra-venda
title: "Relatórios — Runbook do relatório Compras e vendas"
type: runbook
module: Relatorios
tela: Relatorios/CompraVenda/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Compras e vendas

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-page.jsx` (`Resumo`) +
> `relatorios-data.jsx` (`purchase_sell`) · decisão D2 de [W] (Relatórios numa tela com grupos).
> Primeiro relatório da thread; o hub com grupos e os demais relatórios vêm em PRs próprios.

## 1. Objetivo

`GET /reports/purchase-sell?tela=nova` responde Inertia `Relatorios/CompraVenda/Index`. A Page busca os
totais no mesmo `GET /reports/purchase-sell` (ajax) que a Blade usa e só formata. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — baseline do backend: `tests/Feature/Relatorios/CompraVendaReportPageTest.php` prova os totais do
   JSON por dois caminhos (endpoint × soma direta das transações) e o isolamento 98 × 99.
3. **F3** — `ReportController::getPurchaseSell` devolve a Page quando `tela=nova`, **antes** do `ajax()`
   (a visita Inertia manda `X-Requested-With`). A Page busca o JSON sem `tela`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando os 10 números com a Blade no mesmo filtro.

## 4. Divergências do protótipo (declaradas)

- Filtro **cliente** do protótipo: o endpoint não filtra por cliente. Fica fora até haver backend.
- **Diferença a receber − a pagar** (`difference.due`): está na Blade e não no protótipo. Mantida.

## 5. Falta para o cutover (F5 — decisão [W])

Aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente. Este RUNBOOK não faz o cutover.
