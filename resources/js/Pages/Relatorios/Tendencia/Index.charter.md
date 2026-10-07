---
id: resources-js-pages-relatorios-tendencia-index-charter
page: /reports/trending-products
component: resources/js/Pages/Relatorios/Tendencia/Index.tsx
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
  derived_screens: [Relatorios/Tendencia]
  divergence_from_blueprint: "sem as colunas Categoria e Total vendido (R$), que a consulta não devolve; tabela no lugar do gráfico; sem o filtro de subcategoria"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-tendencia.md
---

# Page Charter — /reports/trending-products (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar os produtos que mais saíram num período, filtrando por local, categoria, marca, unidade e tipo.

## Goals

- Ranking produto · SKU · quantidade vendida (já descontada a devolução), na ordem da consulta.
- O ranking vem de `ProductUtil::getTrendingProducts`, a mesma lista do gráfico da Blade.

## Non-Goals

- ❌ Calcular, somar ou reordenar na tela.
- ❌ Colunas Categoria e Total vendido (R$): o protótipo desenha, a consulta não devolve.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `trending_product_report.view`, a mesma da Blade | `ReportController::getTrendingProducts` |
| R2 | Só vendas do negócio da sessão e dos locais permitidos | `ProductUtil::getTrendingProducts` |
| R3 | Sem período = todas as vendas, e no máximo 5 produtos se o limite não vier | `getTrendingProducts` (igual à Blade) |

## Refs

RUNBOOK: [`RUNBOOK-tendencia.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-tendencia.md) · ADR 0093 · ADR 0104 · ADR 0358.
