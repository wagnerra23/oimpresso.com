---
slug: relatorios-runbook-validade
title: "Relatórios — Runbook do relatório Validade de estoque"
type: runbook
module: Relatorios
tela: Relatorios/Validade/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Validade de estoque

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`stock_expiry_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/stock-expiry?tela=nova` responde Inertia `Relatorios/Validade/Index`: o saldo de cada variação por
validade e lote (produto, SKU, local, saldo, lote, validade, fabricação), da mesma consulta do DataTable da Blade, 25 por
página, e o rodapé da página como o da Blade. Sem `?tela=nova`, Blade. Só leitura — a Blade também não mostra as ações
(a coluna "edit" está comentada no `report.js`).

## 2. Pré-condições

Permissão `stock_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/ValidadeReportPageTest.php`: saldo por três caminhos (o JSON do DataTable da Blade,
   as props da Page e a conta direta em `purchase_lines`), o lote zerado fora, o filtro de faixa de validade, a paginação
   e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaValidade()`, chamada pelos dois ramos. O ramo `tela=nova` vem
   antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando com a Blade (ordem: validade, crescente).

## 4. Divergências declaradas

- A validade sai formatada (a Blade mostra a data crua do banco).
- Sem os filtros de subcategoria e "só produtos de fabricação" — ficam para depois.

## 5. Falta para o cutover (F5 — decisão [W])

Os itens do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
