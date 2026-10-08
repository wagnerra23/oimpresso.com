---
id: resources-js-pages-relatorios-impostos-index-charter
page: /reports/tax-details
component: resources/js/Pages/Relatorios/Impostos/Index.tsx
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
  derived_screens: [Relatorios/Impostos]
  divergence_from_blueprint: "abas como seletor; resumo de diferença de imposto e abas de módulos ficam para o PR seguinte"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-impostos.md
---

# Page Charter — /reports/tax-details (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, o JSON dos DataTables da Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto de cada imposto entrou (compras), saiu (vendas) e foi pago em despesas, transação por transação.

## Goals

- Uma aba por vez (entrada, saída, despesa), paginada no servidor (25 por página, na ordem de cada DataTable da Blade).
- Imposto por alíquota pela MESMA conta da coluna da Blade (`impostoPorAliquota`), inclusive a quebra do imposto composto.
- Rodapé da página como o da Blade: total e cada alíquota somados.

## Non-Goals

- ❌ Calcular, somar ou arredondar imposto na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `tax_report.view`, a mesma da Blade | `ReportController::getTaxDetails` |
| R2 | Só transações do negócio da sessão e dos locais permitidos | `consultaImpostos` |
| R3 | Imposto da alíquota = Σ imposto do item × quantidade líquida (com quebra do composto) + imposto da transação (com quebra) | `impostoPorAliquota` |

## Refs

RUNBOOK: [`RUNBOOK-impostos.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-impostos.md) · ADR 0093 · ADR 0104 · ADR 0358.
