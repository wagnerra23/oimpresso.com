---
slug: relatorios-runbook-compras-produto
title: "Relatórios — Runbook do relatório Compras por produto"
type: runbook
module: Relatorios
tela: Relatorios/ComprasProduto/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Compras por produto

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`product_purchase_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/product-purchase-report?tela=nova` responde Inertia `Relatorios/ComprasProduto/Index`: uma linha por item de
compra (produto, fornecedor, compra, data, quantidade, ajustado, preço unitário, subtotal), da mesma consulta do DataTable
da Blade, 25 por página, e o rodapé da página como o da Blade. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/ComprasProdutoReportPageTest.php`: quantidade, ajustado e subtotal por três caminhos
   (o JSON do DataTable da Blade, as props da Page e a conta direta em `purchase_lines`), paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaComprasPorProduto()`, chamada pelos dois ramos. O ramo
   `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (ordem: ref. da compra, decrescente).

## 4. Divergências declaradas

- O filtro por produto (busca com autocompletar na Blade) fica para depois.
- A ref. da compra não abre a compra no modal (a Blade abre) — fica para depois.

## 5. Falta para o cutover (F5 — decisão [W])

O filtro por produto, o link da compra, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
