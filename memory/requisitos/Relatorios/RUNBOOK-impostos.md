---
slug: relatorios-runbook-impostos
title: "Relatórios — Runbook do relatório de impostos (abas entrada, saída e despesa)"
type: runbook
module: Relatorios
tela: Relatorios/Impostos/Index
owner: W
status: ativo
last_validated: "2026-10-08"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de impostos (abas)

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`tax_report`) · tabela paginada
> no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/tax-details?tela=nova&tipo=purchase|sell|expense` responde Inertia `Relatorios/Impostos/Index`: as linhas de
uma aba do relatório de impostos (entrada = compras recebidas, saída = vendas finalizadas, despesa), com uma coluna por
alíquota, 25 por página, e o rodapé da página como o da Blade. Sem `?tela=nova`, o endpoint segue sendo só o JSON dos
DataTables da página `/reports/tax-report`.

## 2. Pré-condições

Permissão `tax_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/ImpostosReportPageTest.php`: imposto por alíquota (inclusive a quebra do imposto
   composto) por três caminhos (o JSON do DataTable da Blade, as props da Page e a conta à mão), as três abas, a
   paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai de `getTaxDetails()` para `consultaImpostos()`, a montagem dos impostos compostos para
   `gruposDeImposto()` e a conta da coluna `tax_{id}` (que era uma closure do DataTable) para `impostoPorAliquota()`.
   O DataTable e a tela nova chamam as três. O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando cada aba com a Blade (período padrão: ano fiscal).

## 4. Divergências declaradas

- O resumo do topo da Blade (diferença de imposto, incluindo o imposto de saída que outros módulos somam) e as abas que
  outros módulos injetam ficam para o PR seguinte.
- Abas viram um seletor na mesma tela (a Blade usa abas Bootstrap).

## 5. Falta para o cutover (F5 — decisão [W])

O resumo e as abas de módulos (§4), aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
