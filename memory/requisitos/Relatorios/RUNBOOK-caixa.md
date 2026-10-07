---
slug: relatorios-runbook-caixa
title: "Relatórios — Runbook do relatório de Caixa (registro)"
type: runbook
module: Relatorios
tela: Relatorios/Caixa/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0066-format-date-shift-3h-preservado-legacy-clientes'
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de Caixa (registro)

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`register_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/register-report?tela=nova` responde Inertia `Relatorios/Caixa/Index`: os caixas de
`TransactionUtil::registerReport` (a mesma consulta do DataTable da Blade), 25 por página, com o total por forma
de pagamento, o total do caixa e o rodapé por coluna somando **só a página** (como o `footerCallback` da Blade).
Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `register_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/CaixaReportPageTest.php`: valores por forma e total por três caminhos (o JSON
   do DataTable da Blade, as props da Page e a soma direta em `cash_register_transactions`), paginação, rodapé e
   o isolamento 98 × 99.
3. **F3** — a soma do total do caixa sai do `addColumn('total')` para `totalDoCaixa()`, usada pela coluna da Blade e
   pela tela nova (mesma ordem de soma). As datas vão formatadas pelo mesmo `format_date` da Blade (ADR 0066).
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (ordem: abertura, crescente).

## 4. Divergências declaradas

- Sem período escolhido mostra todos os caixas, como a Blade (o carregamento inicial dela não manda filtro).
- As ações da linha da Blade (ver caixa, fechar caixa) ficam para depois.

## 5. Falta para o cutover (F5 — decisão [W])

As ações da linha, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
