---
id: resources-js-pages-relatorios-compravenda-index-charter
page: /reports/purchase-sell
component: resources/js/Pages/Relatorios/CompraVenda/Index.tsx
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
  derived_screens: [Relatorios/CompraVenda]
  divergence_from_blueprint: "sem filtro de cliente (o endpoint não filtra por cliente); com a Diferença a receber − a pagar, que a Blade mostra e o protótipo não"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-compra-venda.md
---

# Page Charter — /reports/purchase-sell (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar, para um período e um local, quanto foi comprado e vendido, as devoluções, o que ficou a pagar e a receber, e a diferença entre vendas e compras.

## Goals

- Dois painéis (Compras · Vendas) com 4 linhas cada e as duas diferenças da Blade.
- Os números são os do JSON de `GET /reports/purchase-sell` (ajax), sem cálculo na tela.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela. Ela só formata o que o endpoint devolve.
- ❌ Filtro por cliente. O protótipo o desenha, mas o endpoint não filtra por cliente.
- ❌ Restringir os totais aos locais permitidos do usuário. A Blade não restringe (os totais não recebem `permitted_locations`); mudar é decisão [W].

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade e do JSON | `ReportController::getPurchaseSell` |
| R2 | Totais e locais só do negócio da sessão | `TransactionUtil::get{Purchase,Sell,Transaction}Totals` · `BusinessLocation::forDropdown` |
| R3 | A visita `?tela=nova` é tratada antes do `ajax()` | `getPurchaseSell` (Inertia manda `X-Requested-With`) |

## Refs

RUNBOOK: [`RUNBOOK-compra-venda.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-compra-venda.md) · ADR 0093 · ADR 0104 · ADR 0358.
