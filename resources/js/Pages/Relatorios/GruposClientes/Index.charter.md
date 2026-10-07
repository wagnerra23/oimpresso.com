---
id: resources-js-pages-relatorios-gruposclientes-index-charter
page: /reports/customer-group
component: resources/js/Pages/Relatorios/GruposClientes/Index.tsx
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
  derived_screens: [Relatorios/GruposClientes]
  divergence_from_blueprint: "venda sem grupo aparece como 'Sem grupo' (na Blade a célula fica vazia)"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-grupos-clientes.md
---

# Page Charter — /reports/customer-group (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Mostrar quanto cada grupo de clientes comprou num período, por local e grupo.

## Goals

- Tabela grupo × total vendido (vendas finais).
- As linhas vêm de `consultaGrupoDeClientes()`, a mesma consulta do DataTable da Blade.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.
- ❌ Total no rodapé. A Blade não tem; a tela não inventa número.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `contacts_report.view`, a mesma da Blade | `ReportController::getCustomerGroup` |
| R2 | Linhas, grupos e locais só do negócio da sessão e dos locais permitidos | `consultaGrupoDeClientes` · `CustomerGroup::forDropdown` · `BusinessLocation::forDropdown` |
| R3 | Só venda `final` entra | `consultaGrupoDeClientes` |

## Refs

RUNBOOK: [`RUNBOOK-grupos-clientes.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-grupos-clientes.md) · ADR 0093 · ADR 0104 · ADR 0358.
