---
id: resources-js-pages-report-salesrepresentative-index-charter
page: /reports/sales-representative-report
component: resources/js/Pages/Report/SalesRepresentative/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/comissoes-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-06"
parent_module: Comissao
related_adrs: [93, 104, 151, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/comissoes-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Report/SalesRepresentative]
  divergence_from_blueprint: "sem aviso, toggle faturadas/a receber, regra por agente e fechamento (sem backend; ADR 0151 e D-COM-2); no protótipo a tela fica sob Usuários, aqui segue em Relatórios"
related_runbook: memory/requisitos/Comissao/RUNBOOK-relatorio-comissao.md
---

# Page Charter — /reports/sales-representative-report (DRAFT)

> Draft · thread `comissoes/playbook/02` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar, para um período, local e vendedor, quanto foi vendido, devolvido, gasto e quanto de comissão o vendedor tem.

## Goals

- Resumo: vendas − devoluções = total, comissão (com o percentual e a base) e despesas.
- Os números são os dos endpoints `getSalesRepresentativeTotal{Sell,Expense,Commission}`, sem cálculo na tela.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela. Ela só formata o que o endpoint devolve.
- ❌ Mudar a base da comissão (faturada × recebida). É `pos_settings.cmmsn_calculation_type`; mudar exige ADR (D-COM-2).
- ❌ Travar o vendedor no usuário logado. A Blade não trava; mudar é decisão [W].

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `sales_representative.view`, a mesma da Blade e dos endpoints | `getSalesRepresentativeReport` |
| R2 | Vendedores e locais só do negócio da sessão | `User::allUsersDropdown` · `BusinessLocation::forDropdown` |
| R3 | Comissão só é pedida com vendedor escolhido | `Index.tsx` (igual ao `report.js`) |

## Refs

RUNBOOK: [`RUNBOOK-relatorio-comissao.md`](../../../../../memory/requisitos/Comissao/RUNBOOK-relatorio-comissao.md) · ADR 0093 · ADR 0104 · ADR 0151 · ADR 0358.
