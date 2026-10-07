---
slug: comissao-runbook-relatorio-comissao
title: "Comissão — Runbook do relatório de comissão por vendedor"
type: runbook
module: Comissao
tela: Report/SalesRepresentative/Index
owner: W
status: ativo
last_validated: "2026-10-06"
related_adrs:
  - '0104-processo-mwart-canonico-unico-caminho'
  - '0151-modules-comissao-feature-wish'
---

# RUNBOOK — Relatório de comissão por vendedor

> Thread `comissoes/playbook/02` · design `prototipo-ui/cowork/Wagner/comissoes-page.jsx` (alvo `comissoes`) · fronteira ADR 0151 (só legado).

## 1. Objetivo

`GET /reports/sales-representative-report?tela=nova` responde Inertia `Report/SalesRepresentative/Index` com o
resumo (vendas − devoluções, comissão, despesas). Os números vêm dos endpoints de sempre
`/reports/sales-representative-total-{sell,expense,commission}`; a tela não calcula. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `sales_representative.view` (a da Blade). Base da comissão = `pos_settings.cmmsn_calculation_type`.

## 3. Passo-a-passo

1. `ReportController::getSalesRepresentativeReport` devolve Inertia quando `tela=nova`.
2. A Page busca os três endpoints com `X-Requested-With` (exigem `ajax()`); comissão só com vendedor escolhido.

## 4. Falta para o cutover

As 4 abas de listagem da Blade e a aprovação do screenshot por [W].
