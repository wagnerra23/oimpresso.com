---
id: resources-js-pages-relatorios-lotes-index-charter
page: /reports/lot-report
component: resources/js/Pages/Relatorios/Lotes/Index.tsx
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
  derived_screens: [Relatorios/Lotes]
  divergence_from_blueprint: "sem os filtros de subcategoria e só fabricados; validade sem o tempo relativo — ficam para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-lotes.md
---

# Page Charter — /reports/lot-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar, para cada lote de produto, quanto ainda há em estoque, quanto saiu por venda e por ajuste, e a validade.

## Goals

- Tabela paginada no servidor (25 por página, SKU crescente, como a Blade).
- Quantidades da mesma consulta do DataTable (`consultaLotes`).
- Rodapé por unidade somando só a página, como o `__sum_stock` da Blade.

## Non-Goals

- ❌ Calcular, somar ou arredondar quantidade na tela.
- ❌ Somar unidades diferentes numa conta só: o rodapé separa por unidade, como a Blade.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `stock_report.view`, a mesma da Blade | `ReportController::getLotReport` |
| R2 | Só produtos e compras do negócio da sessão e dos locais permitidos | `consultaLotes` |
| R3 | Id da request que entra no SQL cru é inteiro | `consultaLotes` (#9008) |

## Refs

RUNBOOK: [`RUNBOOK-lotes.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-lotes.md) · ADR 0093 · ADR 0104 · ADR 0358.
