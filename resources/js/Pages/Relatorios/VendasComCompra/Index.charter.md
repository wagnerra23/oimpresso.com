---
id: resources-js-pages-relatorios-vendascomcompra-index-charter
page: /reports/product-sell-report-with-purchase
component: resources/js/Pages/Relatorios/VendasComCompra/Index.tsx
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
  derived_screens: [Relatorios/VendasComCompra]
  divergence_from_blueprint: "tela própria em vez de aba (hub D2 pendente); sem filtro por produto, horário e link da venda"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-vendas-com-compra.md
---

# Page Charter — /reports/product-sell-report-with-purchase (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, o JSON do DataTable da Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar, para cada item vendido, de qual compra (e lote, e fornecedor) saiu a quantidade.

## Goals

- Tabela paginada no servidor (25 por página, data da venda decrescente, como a aba "Detalhado com compra" da Blade).
- Quantidades da mesma consulta do DataTable (`consultaVendasComCompra`).

## Non-Goals

- ❌ Calcular, somar ou arredondar quantidade na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::getproductSellReportWithPurchase` |
| R2 | Só vendas finalizadas do negócio da sessão e dos locais permitidos | `consultaVendasComCompra` |
| R3 | Quantidade = `transaction_sell_lines_purchase_lines.quantity` do vínculo | `consultaVendasComCompra` (SQL) |
| R4 | Estoque inicial aparece como "Estoque inicial" no lugar da ref. da compra | `telaVendasComCompra` |

## Refs

RUNBOOK: [`RUNBOOK-vendas-com-compra.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-vendas-com-compra.md) · ADR 0093 · ADR 0104 · ADR 0358.
