---
slug: relatorios-runbook-tendencia
title: "Relatórios — Runbook do relatório Produtos em tendência"
type: runbook
module: Relatorios
tela: Relatorios/Tendencia/Index
owner: W
status: ativo
last_validated: "2026-10-07"
related_adrs:
  - '0093-multi-tenant-isolation-tier-0'
  - '0104-processo-mwart-canonico-unico-caminho'
---

# RUNBOOK — Relatório Produtos em tendência

> Thread `sistema/playbook/07` · design `prototipo-ui/cowork/Wagner/relatorios-data.jsx` (`trending_products`) ·
> 4º relatório da thread.

## 1. Objetivo

`GET /reports/trending-products?tela=nova` responde Inertia `Relatorios/Tendencia/Index` com o ranking de
`ProductUtil::getTrendingProducts` — a mesma lista que vira o gráfico da Blade. Sem `?tela=nova`, Blade.

## 2. Pré-condições

Permissão `trending_product_report.view` (a da Blade). Nada de schema.

## 3. Passo-a-passo (F1–F4)

1. **F1** — este RUNBOOK + charter + casos ao lado da Page.
2. **F2** — `tests/Feature/Relatorios/TendenciaReportPageTest.php`: ranking por três caminhos (o gráfico que a
   Blade recebe, as props da Page e a soma direta em `transaction_sell_lines`) e o isolamento 98 × 99.
3. **F3** — `getTrendingProducts` monta os filtros como sempre; com `tela=nova` aceita `start_date`/`end_date`
   em ISO e devolve a Page com a mesma `$products`.
4. **F4** — smoke em biz=1 com `?tela=nova`, comparando o ranking com o gráfico da Blade.

## 4. Divergências declaradas

- O protótipo desenha as colunas "Categoria" e "Total vendido (R$)", que a consulta não devolve: ficam de fora
  (a tela não inventa coluna nem valor).
- O filtro de subcategoria da Blade (carregado por AJAX a partir da categoria) fica para depois.
- Tabela em vez do gráfico de colunas da Blade (o protótipo tem gráfico + tabela; o gráfico vem depois).

## 5. Falta para o cutover (F5 — decisão [W])

Subcategoria, gráfico, aprovação do screenshot por [W], o smoke F4 e o aviso ao cliente.
