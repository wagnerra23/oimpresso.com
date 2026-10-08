---
id: resources-js-pages-relatorios-vendasproduto-index-charter
page: /reports/product-sell-report
component: resources/js/Pages/Relatorios/VendasProduto/Index.tsx
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
  derived_screens: [Relatorios/VendasProduto]
  divergence_from_blueprint: "só a aba Detalhado, sem filtro por produto, horário, campos personalizados e link da venda — ficam para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-vendas-produto.md
---

# Page Charter — /reports/product-sell-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto de cada produto saiu por venda, para qual cliente, em que venda, a que preço e como foi pago.

## Goals

- Tabela paginada no servidor (25 por página, nº da venda decrescente, como a aba "Detalhado" da Blade).
- Valores e quantidades da mesma consulta do DataTable (`consultaVendasPorProduto`).
- Rodapé da página como o da Blade: subtotal e quantidade por unidade sem as linhas filhas de combo; imposto por taxa.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor ou quantidade na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::getproductSellReport` |
| R2 | Só vendas finalizadas do negócio da sessão e dos locais permitidos | `consultaVendasPorProduto` |
| R3 | Subtotal = (vendido − devolvido) × preço com imposto | `consultaVendasPorProduto` (SQL) |
| R4 | Linha filha de combo não entra no rodapé de quantidade e subtotal | `telaVendasPorProduto` |

## Refs

RUNBOOK: [`RUNBOOK-vendas-produto.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-vendas-produto.md) · ADR 0093 · ADR 0104 · ADR 0358.
