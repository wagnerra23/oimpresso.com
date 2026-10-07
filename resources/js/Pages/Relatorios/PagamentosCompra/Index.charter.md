---
id: resources-js-pages-relatorios-pagamentoscompra-index-charter
page: /reports/purchase-payment-report
component: resources/js/Pages/Relatorios/PagamentosCompra/Index.tsx
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
  derived_screens: [Relatorios/PagamentosCompra]
  divergence_from_blueprint: "sem as ações da linha (ver pagamento, documento, abrir compra) e sem o detalhe da forma (nº de cheque/cartão/conta) — ficam para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-pagamentos-compra.md
---

# Page Charter — /reports/purchase-payment-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Listar cada pagamento de compra do período, com fornecedor, forma e valor, filtrando por fornecedor e local.

## Goals

- Tabela paginada no servidor (25 por página, mais recente primeiro), na ordem da Blade.
- Linhas da mesma consulta do DataTable da Blade (`consultaPagamentosDeCompra`).
- Rodapé "Total desta página", como o da Blade, que soma só as linhas visíveis.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela: o total da página chega pronto do controller.
- ❌ Total do período no rodapé. A Blade soma só a página; mudar isso é decisão [W].

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `purchase_n_sell_report.view`, a mesma da Blade | `ReportController::purchasePaymentReport` |
| R2 | Só pagamentos do negócio da sessão e dos locais permitidos | `consultaPagamentosDeCompra` |
| R3 | Id da request entra como inteiro | `consultaPagamentosDeCompra` (#9008) |
| R4 | Sem período = todos os pagamentos | igual à Blade (campo de data nasce vazio) |

## Refs

RUNBOOK: [`RUNBOOK-pagamentos-compra.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-pagamentos-compra.md) · ADR 0093 · ADR 0104 · ADR 0358.
