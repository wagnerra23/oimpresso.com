---
id: resources-js-pages-relatorios-mesas-index-charter
page: /reports/table-report
component: resources/js/Pages/Relatorios/Mesas/Index.tsx
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
  derived_screens: [Relatorios/Mesas]
  divergence_from_blueprint: "nenhuma de conteúdo — tabela paginada no servidor"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-mesas.md
---

# Page Charter — /reports/table-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto cada mesa vendeu no período.

## Goals

- Tabela paginada no servidor (25 por página, nome da mesa crescente, como a Blade).
- Total da mesma consulta do DataTable (`consultaMesas`); período padrão = mês corrente.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::getTableReport` |
| R2 | Só vendas finalizadas do negócio da sessão | `consultaMesas` |
| R3 | Total = soma do `final_total` das vendas da mesa no período | `consultaMesas` (SQL) |

## Refs

RUNBOOK: [`RUNBOOK-mesas.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-mesas.md) · ADR 0093 · ADR 0104 · ADR 0358.
