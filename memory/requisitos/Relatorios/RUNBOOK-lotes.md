---
slug: relatorios-runbook-lotes
title: "Relatórios — Runbook do relatório de Lotes"
type: runbook
module: Relatorios
tela: Relatorios/Lotes/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório de Lotes

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`lot_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/lot-report?tela=nova` responde Inertia `Relatorios/Lotes/Index`: estoque, vendido e ajustado por lote,
da mesma consulta do DataTable da Blade, 25 por página, e o rodapé por unidade somando **só a página** (como o
`__sum_stock` da Blade). Sem `?tela=nova`, Blade. Regra mestre de VALOR **ou ESTOQUE**: nenhuma quantidade muda.

## 2. Pré-condições

Permissão `stock_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/LotesReportPageTest.php`: estoque, vendido e ajustado por três caminhos (o JSON
   do DataTable da Blade, as props da Page e a conta direta em `purchase_lines`/`transaction_sell_lines_purchase_lines`),
   paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaLotes()`, chamada pelos dois ramos (mesmo `(int)` do #9008
   no `location_id` que entra no SQL cru). O ramo `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (ordem: SKU, crescente).

## 4. Divergências declaradas

- Os filtros de subcategoria (carregado por AJAX na Blade) e "só produtos fabricados" (Manufacturing) ficam para depois.
- O "tempo até vencer" da Blade (texto relativo) vira só a data e a marca "vencido".

## 5. Falta para o cutover (F5 — decisão [W])

Os dois filtros, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
