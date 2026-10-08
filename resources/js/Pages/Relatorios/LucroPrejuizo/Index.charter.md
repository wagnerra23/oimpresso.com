---
id: resources-js-pages-relatorios-lucroprejuizo-index-charter
page: /reports/profit-loss
component: resources/js/Pages/Relatorios/LucroPrejuizo/Index.tsx
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
  derived_screens: [Relatorios/LucroPrejuizo]
  divergence_from_blueprint: "sem as abas de lucro por produto/categoria/etc. (PR seguinte)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-lucro-prejuizo.md
---

# Page Charter — /reports/profit-loss (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar se o negócio deu lucro no período: o que entrou, o que saiu, CMV, lucro bruto e lucro líquido.

## Goals

- Os mesmos números do partial da Blade (`dadosDeLucro`, via `getProfitLossDetails`) e o estoque pelo preço de venda.
- CMV pela mesma fórmula da Blade; lucro bruto e líquido vindos do servidor.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `profit_loss_report.view`, a mesma da Blade | `ReportController::getProfitLoss` |
| R2 | Só o negócio da sessão e os locais permitidos | `dadosDeLucro` (getProfitLossDetails) |
| R3 | CMV = estoque inicial − compras + estoque final (fórmula da Blade) | `telaLucro` |

## Refs

RUNBOOK: [`RUNBOOK-lucro-prejuizo.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-lucro-prejuizo.md) · ADR 0093 · ADR 0104 · ADR 0358.
