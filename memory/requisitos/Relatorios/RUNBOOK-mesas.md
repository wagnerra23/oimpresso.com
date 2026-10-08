---
slug: relatorios-runbook-mesas
title: "Relatórios — Runbook do relatório por mesa"
type: runbook
module: Relatorios
tela: Relatorios/Mesas/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório por mesa

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`table_report`) · tabela
> paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/table-report?tela=nova` responde Inertia `Relatorios/Mesas/Index`: o total vendido por mesa (vendas
finalizadas) no período, da mesma consulta do DataTable da Blade, 25 por página. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/MesasReportPageTest.php`: total por mesa por três caminhos (o JSON do DataTable da
   Blade, as props da Page e a soma direta em `transactions`), a paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaMesas()`, chamada pelos dois ramos. O ramo `tela=nova` vem
   antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a Blade (período padrão: mês corrente; ordem: nome da mesa).

## 4. Divergências declaradas e achados

- **Achado (não corrigido):** a consulta não filtra pelos locais permitidos do usuário — só pelo local escolhido. Um
  usuário restrito a um local vê o total de mesas de todos os locais do negócio. O isolamento entre negócios
  (`business_id`) está certo. Preservado como a Blade; correção é decisão [W].

## 5. Falta para o cutover (F5 — decisão [W])

Decisão sobre o achado do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
