---
id: resources-js-pages-relatorios-itensporatendente-index-charter
page: /reports/service-staff-line-orders
component: resources/js/Pages/Relatorios/ItensPorAtendente/Index.tsx
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
  derived_screens: [Relatorios/ItensPorAtendente]
  divergence_from_blueprint: "só a aba itens por atendente (a de pedidos vem do SellController)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-itens-por-atendente.md
---

# Page Charter — /reports/service-staff-line-orders (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, o JSON do DataTable da Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar o que cada atendente vendeu, item a item, e quanto isso somou.

## Goals

- Tabela paginada no servidor (25 por página, data da venda decrescente, como a Blade).
- Valores da mesma consulta do DataTable (`consultaItensPorAtendente`), com desconto e total pela conta da Blade.
- Rodapé da página como o da Blade.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `sales_representative.view`, a da página da Blade | `ReportController::serviceStaffLineOrders` (ramo `tela=nova`) |
| R2 | Só itens de vendas finalizadas do negócio da sessão com atendente | `consultaItensPorAtendente` |
| R3 | Desconto percentual = preço antes do desconto × % ÷ 100; total = preço com imposto × quantidade | `telaItensPorAtendente` |

## Refs

RUNBOOK: [`RUNBOOK-itens-por-atendente.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-itens-por-atendente.md) · ADR 0093 · ADR 0104 · ADR 0358.
