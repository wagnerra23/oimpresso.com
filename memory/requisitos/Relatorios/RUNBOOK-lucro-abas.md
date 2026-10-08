---
slug: relatorios-runbook-lucro-abas
title: "Relatórios — Runbook das abas de lucro (por produto, categoria, marca, local, venda, data, cliente e dia)"
type: runbook
module: Relatorios
tela: Relatorios/LucroAbas/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Abas de lucro

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`profit_loss`) · tabela paginada
> no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/get-profit/{aba}?tela=nova` responde Inertia `Relatorios/LucroAbas/Index`: o lucro bruto agrupado pela aba
escolhida (produto, categoria, marca, local, venda, data, cliente ou dia da semana), da mesma consulta dos DataTables da
página de lucro e prejuízo, 25 por página, com o rodapé da página. Sem `?tela=nova`, o endpoint segue sendo o JSON dos
DataTables (e o partial da aba "por dia").

## 2. Pré-condições

Permissão `profit_loss_report.view` (a da página; o endpoint exige desde o #9040). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/LucroAbasReportPageTest.php`: lucro por três caminhos (o JSON do DataTable da Blade,
   as props da Page e a conta à mão), o desconto da venda na aba "por venda", a aba "por dia" e o isolamento 98 × 99.
3. **F3** — a consulta sai de `getProfit()` para `consultaLucro()` e a conta do desconto da venda para `lucroDaLinha()`.
   O DataTable, a aba "por dia" e a tela nova chamam os mesmos métodos.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando cada aba com a Blade (período padrão: ano fiscal).

## 4. Divergências declaradas

- Tela própria com seletor de aba (a Blade usa abas na página de lucro e prejuízo); o hub de Relatórios (D2) ainda não existe.

## 5. Falta para o cutover (F5 — decisão [W])

Aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
