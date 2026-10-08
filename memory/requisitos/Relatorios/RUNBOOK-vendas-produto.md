---
slug: relatorios-runbook-vendas-produto
title: "Relatórios — Runbook do relatório Vendas por produto"
type: runbook
module: Relatorios
tela: Relatorios/VendasProduto/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Vendas por produto

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`product_sell_report`) ·
> tabela paginada no servidor (`Relatorios/_shared/Paginacao.tsx`).

## 1. Objetivo

`GET /reports/product-sell-report?tela=nova` responde Inertia `Relatorios/VendasProduto/Index`: uma linha por item de
venda finalizada (produto, cliente, venda, data, quantidade, preço, desconto, imposto, subtotal, forma de pagamento), da
mesma consulta do DataTable da aba "Detalhado" da Blade, 25 por página, e o rodapé da página como o da Blade. Sem
`?tela=nova`, Blade.

## 2. Pré-condições

Permissão `purchase_n_sell_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/VendasProdutoReportPageTest.php`: quantidade e subtotal por três caminhos (o JSON do
   DataTable da Blade, as props da Page e a conta direta em `transaction_sell_lines`), o rodapé sem a linha filha de
   combo, a paginação e o isolamento 98 × 99.
3. **F3** — a consulta sai do ramo `ajax()` para `consultaVendasPorProduto()`, chamada pelos dois ramos. O ramo
   `tela=nova` vem antes do `ajax()`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando a 1ª página com a Blade (ordem: nº da venda, decrescente).

## 4. Divergências declaradas

- Só a aba "Detalhado". As abas "Detalhado com compra" e "Agrupado" (outros métodos) ficam para depois.
- Período por data; o horário fica no padrão da Blade (00:00 a 23:59). Os campos de horário da Blade ficam para depois.
- Filtro por produto, colunas de campo personalizado e o link da venda ficam para depois.

## 5. Falta para o cutover (F5 — decisão [W])

As outras abas, os itens do §4, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
