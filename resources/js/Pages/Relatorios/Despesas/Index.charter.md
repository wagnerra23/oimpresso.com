---
id: resources-js-pages-relatorios-despesas-index-charter
page: /reports/expense-report
component: resources/js/Pages/Relatorios/Despesas/Index.tsx
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
  derived_screens: [Relatorios/Despesas]
  divergence_from_blueprint: "sem o gráfico de colunas da Blade (o protótipo desenha só a tabela)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-despesas.md
---

# Page Charter — /reports/expense-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto o negócio gastou por categoria de despesa num período, local e categoria.

## Goals

- Tabela categoria × total, com o total do período no rodapé.
- Linhas e total vêm de `TransactionUtil::getExpenseReport`, a mesma consulta que a Blade recebe.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela. O total chega pronto do controller.
- ❌ Gráfico de colunas. A Blade tem; o protótipo não.
- ❌ Restringir aos locais permitidos do usuário. A Blade não restringe; mudar é decisão [W].

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `expense_report.view`, a mesma da Blade | `ReportController::getExpenseReport` |
| R2 | Linhas, categorias e locais só do negócio da sessão | `getExpenseReport` · `ExpenseCategory` · `BusinessLocation::forDropdown` |
| R3 | Devolução de despesa entra negativa | `TransactionUtil::getExpenseReport` (`expense_refund` × −1) |

## Refs

RUNBOOK: [`RUNBOOK-despesas.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-despesas.md) · ADR 0093 · ADR 0104 · ADR 0358.
