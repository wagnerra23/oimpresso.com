---
id: resources-js-pages-relatorios-estoque-index-casos
casos: Relatório de estoque · /reports/stock-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela diz quanto há e quanto vale o estoque; número diferente da Blade, ou estoque de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Relatório de estoque

> Thread `sistema/playbook/07`. Derivados do `ReportController::getStockReport` (linhas de
> `ProductUtil::getProductStockDetails`; valor pela venda = estoque × preço do grupo, ou de venda; lucro potencial = valor
> pela venda − valor pela compra; preço só com `access_default_selling_price`) e do `stock_report_table.blade.php`
> (colunas de valor só com `view_product_stock_value`; ordem pelo SKU, crescente; rodapé somando a página), lidos em
> 2026-10-08 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/EstoqueReportPageTest.php`](../../../../../tests/Feature/Relatorios/EstoqueReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RES-01 · Estoque e valores iguais aos da Blade · `must` `[T0]` `[estoque]` `[valor]`
- **Aceite:** Dado num local próprio um produto com estoque 7, preço de venda 20, comprado 7 a 12 e 2 vendidos · Quando
  abro a tela nova e peço o JSON do DataTable da Blade · Então estoque, valor pela compra, valor pela venda, lucro
  potencial e vendido são os mesmos nos dois e batem com a conta à mão: 7 · 84 · 140 · 56 · 2.
- **Status: 🧪**

## UC-RES-02 · Preço e valores só com a permissão, como na Blade · `must` `[T0]`
- **Aceite:** Dado usuário sem `view_product_stock_value` · Quando abro a tela nova · Então as colunas de valor não vêm ·
  Dado usuário sem `access_default_selling_price` · Então o preço de venda não vem.
- **Status: 🧪**

## UC-RES-03 · Paginação pelo SKU · `must`
- **Aceite:** Dado mais produtos do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de SKU,
  e a página 2 traz o resto.
- **Status: 🧪**

## UC-RES-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado estoque num local do negócio 99 · Quando o 98 pede esse local · Então não vê nada · Dado usuário sem
  `stock_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Resumo do topo (estoque final pela compra e pela venda, lucro potencial, margem) e filtros de subcategoria e
  "só fabricação", como a Blade.
- [BACKLOG] Decidir se o JSON do DataTable deve esconder as colunas de valor sem `view_product_stock_value` (achado no
  RUNBOOK §4).
