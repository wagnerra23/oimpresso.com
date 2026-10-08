---
id: resources-js-pages-relatorios-validade-index-charter
page: /reports/stock-expiry
component: resources/js/Pages/Relatorios/Validade/Index.tsx
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
  derived_screens: [Relatorios/Validade]
  divergence_from_blueprint: "sem os filtros de subcategoria e só-fabricação; validade formatada"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-validade.md
---

# Page Charter — /reports/stock-expiry (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar o que ainda há em estoque de cada produto, por validade e lote, para saber o que vence primeiro.

## Goals

- Tabela paginada no servidor (25 por página, validade crescente, como a Blade).
- Saldo da mesma consulta do DataTable (`consultaValidade`).
- Rodapé da página como o da Blade: saldo somado por unidade.

## Non-Goals

- ❌ Calcular ou arredondar saldo na tela.
- ❌ Alterar validade ou tirar do estoque por esta tela (a Blade também não mostra essas ações).

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `stock_report.view`, a mesma da Blade | `ReportController::getStockExpiryReport` |
| R2 | Só compras do negócio da sessão, dos locais permitidos, de produto com controle de estoque | `consultaValidade` |
| R3 | Saldo = comprado − vendido − ajustado − devolvido, por variação × validade × lote; saldo zero fica fora | `consultaValidade` (SQL) |

## Refs

RUNBOOK: [`RUNBOOK-validade.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-validade.md) · ADR 0093 · ADR 0104 · ADR 0358.
