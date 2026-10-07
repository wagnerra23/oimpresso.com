---
slug: relatorios-runbook-despesas
title: "Relatórios — Runbook do relatório de Despesas"
type: runbook
module: Relatorios
tela: Relatorios/Despesas/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de Despesas

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`expense_report`,
> tabela categoria × total) · 2º relatório da thread (o 1º foi Compras e vendas, #8926).

## 1. Objetivo

`GET /reports/expense-report?tela=nova` responde Inertia `Relatorios/Despesas/Index` com as linhas de
`TransactionUtil::getExpenseReport` (a mesma consulta que a Blade recebe) e o total = soma das linhas, como o
`tfoot` da Blade. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `expense_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/DespesasReportPageTest.php`: linhas e total por três caminhos (o que a
   Blade recebe em `viewData`, as props da Page e a soma direta em `transactions`) e o isolamento 98 × 99.
3. **F3** — `getExpenseReport` monta os filtros como sempre; com `tela=nova` aceita `start_date`/`end_date` em
   ISO (o `date_range` da Blade vem no formato do negócio) e devolve a Page.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando linhas e total com a Blade no mesmo período.

## 4. Divergências declaradas

- O gráfico de colunas da Blade não entra: o protótipo desenha só a tabela.
- Linha sem categoria aparece como "Outros", igual à Blade (`report.others`).

## 5. Falta para o cutover (F5 — decisão [W])

Aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
