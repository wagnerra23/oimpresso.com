---
id: resources-js-pages-relatorios-contatos-index-charter
page: /reports/customer-supplier
component: resources/js/Pages/Relatorios/Contatos/Index.tsx
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
  derived_screens: [Relatorios/Contatos]
  divergence_from_blueprint: "nome do contato sem link para a ficha (a Blade abre em nova aba) — fica para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-contatos.md
---

# Page Charter — /reports/customer-supplier (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Um extrato por contato no período: quanto comprou, devolveu, quanto vendemos para ele e o que ainda está devido.

## Goals

- Tabela paginada no servidor (25 por página, nome crescente, como a Blade).
- Colunas da mesma consulta do DataTable (`consultaContatos`); devido pela mesma conta (`devidoDoContato`).
- Rodapé por coluna somando só a página, como a Blade.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela.
- ❌ Corrigir por conta própria a conta do devido. Ela ignora o desconto de razão de venda (defeito herdado, em
  produção); mudar isso muda o número exibido e é decisão [W]. Ver RUNBOOK §4.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `contacts_report.view`, a mesma da Blade | `ReportController::getCustomerSuppliers` |
| R2 | Só contatos e transações do negócio da sessão e dos locais permitidos | `consultaContatos` |
| R3 | Devido = mesma conta da Blade | `ReportController::devidoDoContato` |
| R4 | Período padrão = ano fiscal da sessão | igual à Blade |

## Refs

RUNBOOK: [`RUNBOOK-contatos.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-contatos.md) · ADR 0093 · ADR 0104 · ADR 0358.
