---
id: resources-js-pages-relatorios-estoque-index-charter
page: /reports/stock-report
component: resources/js/Pages/Relatorios/Estoque/Index.tsx
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
  derived_screens: [Relatorios/Estoque]
  divergence_from_blueprint: "sem o resumo do topo e os filtros de subcategoria e só-fabricação (PR seguinte)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-estoque.md
---

# Page Charter — /reports/stock-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto há de cada produto em cada local, quanto vale e quanto saiu.

## Goals

- Tabela paginada no servidor (25 por página, SKU crescente, como a Blade).
- Linhas de `getProductStockDetails` e valores pelas mesmas contas da Blade (`valorDoEstoquePorVenda`, `lucroPotencialDoEstoque`).
- Rodapé da página com as somas.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor ou estoque na tela.
- ❌ Mostrar preço ou valor do estoque a quem não tem a permissão (a Blade também não mostra).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `stock_report.view`, a mesma da Blade | `ReportController::getStockReport` |
| R2 | Preço de venda só com `access_default_selling_price`; colunas de valor só com `view_product_stock_value` | `telaEstoque` |
| R3 | Valor pela venda = estoque × preço do grupo (ou de venda); lucro potencial = valor pela venda − valor pela compra | `valorDoEstoquePorVenda` · `lucroPotencialDoEstoque` |

## Refs

RUNBOOK: [`RUNBOOK-estoque.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-estoque.md) · ADR 0093 · ADR 0104 · ADR 0358.
