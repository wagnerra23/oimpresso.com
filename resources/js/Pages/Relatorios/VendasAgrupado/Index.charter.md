---
id: resources-js-pages-relatorios-vendasagrupado-index-charter
page: /reports/product-sell-grouped-report
component: resources/js/Pages/Relatorios/VendasAgrupado/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/relatorios-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-07"
parent_module: Relatorios
related_adrs: [93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/relatorios-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Relatorios/VendasAgrupado]
  divergence_from_blueprint: "tela própria em vez de aba (hub D2 pendente); sem filtro por produto e horário"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-vendas-agrupado.md
---

# Page Charter — /reports/product-sell-grouped-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, o JSON do DataTable da Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto de cada produto saiu por dia, quanto isso somou e quanto ainda há em estoque.

## Goals

- Tabela paginada no servidor (25 por página, SKU decrescente, como a aba "Agrupado" da Blade).
- Quantidades e valores da mesma consulta do DataTable (`consultaVendasAgrupado`).
- Rodapé da página como o da Blade: quantidade por unidade e subtotal.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor ou quantidade na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::getproductSellGroupedReport` |
| R2 | Só vendas finalizadas do negócio da sessão e dos locais permitidos | `consultaVendasAgrupado` |
| R3 | Uma linha por variação por dia; subtotal = Σ (vendido − devolvido) × preço com imposto | `consultaVendasAgrupado` (SQL) |
| R4 | Estoque atual vazio quando o produto não controla estoque | `telaVendasAgrupado` |

## Refs

RUNBOOK: [`RUNBOOK-vendas-agrupado.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-vendas-agrupado.md) · ADR 0093 · ADR 0104 · ADR 0358.
