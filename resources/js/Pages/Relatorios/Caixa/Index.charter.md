---
id: resources-js-pages-relatorios-caixa-index-charter
page: /reports/register-report
component: resources/js/Pages/Relatorios/Caixa/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/relatorios-page.jsx
owner: wagner
status: draft
last_validated: "2026-10-07"
parent_module: Relatorios
related_adrs: [66, 93, 104, 358]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/Wagner/relatorios-page.jsx"
  blueprint_screenshot_approval: "pendente [W]"
  derived_screens: [Relatorios/Caixa]
  divergence_from_blueprint: "sem as ações da linha (ver caixa, fechar caixa) — ficam para antes do cutover"
related_runbook: memory/requisitos/Relatorios/RUNBOOK-caixa.md
---

# Page Charter — /reports/register-report (DRAFT)

> Draft · thread `sistema/playbook/07` · responde em `?tela=nova` (sem ele, Blade) · casos: [`Index.casos.md`](./Index.casos.md).

## Mission

Listar cada abertura de caixa com operador, local, status e quanto entrou em cada forma de recebimento.

## Goals

- Tabela paginada no servidor (25 por página, na ordem da Blade: abertura crescente).
- Valores por forma e total do caixa da mesma consulta e da mesma conta da Blade (`registerReport` · `totalDoCaixa`).
- Rodapé por coluna somando só a página, como o `footerCallback` da Blade.

## Non-Goals

- ❌ Calcular, somar ou arredondar valor na tela: tudo chega pronto do controller.
- ❌ Formatar data no navegador: a hora vem do `format_date` da Blade, que carrega o deslocamento legado (ADR 0066).
- ❌ Fechar caixa por esta tela. É ação de escrita; fica na Blade até o cutover.

## Regras

| # | Regra | Onde |
|---|---|---|
| R1 | Ver exige `register_report.view`, a mesma da Blade | `ReportController::getRegisterReport` |
| R2 | Só caixas do negócio da sessão e dos locais permitidos | `TransactionUtil::registerReport` |
| R3 | Total do caixa = soma das 13 formas, na mesma ordem | `ReportController::totalDoCaixa` |
| R4 | Sem período = todos os caixas | igual à Blade |

## Refs

RUNBOOK: [`RUNBOOK-caixa.md`](../../../../../memory/requisitos/Relatorios/RUNBOOK-caixa.md) · ADR 0066 · ADR 0093 · ADR 0104 · ADR 0358.
