---
id: resources-js-pages-relatorios-lucroabas-index-charter
page: /reports/get-profit
component: resources/js/Pages/Relatorios/LucroAbas/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/relatorios-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-08"
parent_module: Relatorios
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/relatorios-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Relatorios/LucroAbas]
  divergence_from_blueprint: "seletor de aba em vez de abas na página de lucro (hub D2 pendente)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-lucro-abas.md
---

# Page Charter — /reports/get-profit/{aba} (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, o JSON dos DataTables da Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar de onde vem o lucro bruto: por produto, categoria, marca, local, venda, data, cliente ou dia da semana.

## Goals

- Uma aba por vez, paginada no servidor (25 por página, pela coluna da aba, crescente, como a Blade).
- Lucro pela mesma consulta (`consultaLucro`) e, na aba "por venda", com o desconto da venda (`lucroDaLinha`).
- Rodapé da página com o lucro somado.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `profit_loss_report.view` | `ReportController::getProfit` |
| R2 | Só vendas finalizadas do negócio da sessão e dos locais permitidos | `consultaLucro` |
| R3 | Na aba "por venda", lucro = lucro bruto − desconto da venda (percentual sobre o total sem imposto, ou fixo) | `lucroDaLinha` |

## Refs

RUNBOOK: [`RUNBOOK-lucro-abas.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-lucro-abas.md) · ADR 0093 · ADR 0104 · ADR 0358.
