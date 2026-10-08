---
id: resources-js-pages-relatorios-comprasproduto-index-charter
page: /reports/product-purchase-report
component: resources/js/Pages/Relatorios/ComprasProduto/Index.tsx
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
  derived_screens: [Relatorios/ComprasProduto]
  divergence_from_blueprint: "sem o filtro por produto e sem o link da compra — ficam para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-compras-produto.md
---

# Page Charter — /reports/product-purchase-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto de cada produto entrou por compra, de qual fornecedor, em que compra e a que preço.

## Goals

- Tabela paginada no servidor (25 por página, ref. da compra decrescente, como a Blade).
- Valores e quantidades da mesma consulta do DataTable (`consultaComprasPorProduto`).
- Rodapé da página como o da Blade: subtotal somado, quantidade e ajustado por unidade.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor ou quantidade na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::getproductPurchaseReport` |
| R2 | Só compras do negócio da sessão e dos locais permitidos | `consultaComprasPorProduto` |
| R3 | Subtotal = (comprado − devolvido − ajustado) × preço com imposto | `consultaComprasPorProduto` (SQL) |

## Refs

RUNBOOK: [`RUNBOOK-compras-produto.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-compras-produto.md) · ADR 0093 · ADR 0104 · ADR 0358.
