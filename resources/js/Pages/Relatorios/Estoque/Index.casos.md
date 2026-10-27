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

## UC-RES-05 · O JSON da Blade também esconde os valores sem a permissão · `must` `[T0]`
- **Aceite:** Dado usuário com `view_product_stock_value` · Quando pede o JSON do DataTable · Então vêm valor pela compra,
  pela venda e lucro potencial (84 · 140 · 56) · Dado usuário sem a permissão · Então as três colunas vêm vazias.
- **Status: 🧪**

## UC-RES-06 · O resumo do topo é o da Blade · `must` `[T0]` `[estoque]` `[valor]`
> Derivado de `ReportController::getStockValue` (`/reports/get-stock-value`, chamado por `get_stock_value()` em
> `public/js/report.js`) e do bloco `@can('view_product_stock_value')` de `stock_report.blade.php`, lidos em 2026-10-27.
- **Aceite:** Dado num local próprio um produto com estoque 7, comprado ontem 7 a 12 e preço de venda 20 · Quando abro a
  tela nova e peço o JSON do topo da Blade · Então estoque final pela compra, pela venda, lucro potencial e margem são os
  mesmos nos dois e batem com a conta à mão: 84 · 140 · 56 · 40% · Dado usuário sem `view_product_stock_value` · Então o
  resumo não vem.
- **Status: 🧪**

## UC-RES-07 · Filtro de subcategoria como o da Blade · `must` `[T0]`
- **Aceite:** Dado dois produtos da mesma categoria em subcategorias diferentes · Quando escolho a categoria e uma
  subcategoria · Então a tela traz só o produto dela, o mesmo que o JSON do DataTable da Blade traz com o mesmo filtro · E
  o select lista as subcategorias daquela categoria, nunca uma do negócio 99 · Dado subcategoria sem categoria · Então ela
  é ignorada, como na Blade (o select dela depende da categoria).
- **Status: 🧪**

## UC-RES-08 · "Só produtos de fabricação" segue o Manufacturing · `must`
- **Aceite:** Dado o Manufacturing desligado · Então o filtro não aparece e, pedido pela URL, é ignorado (ele junta
  `mfg_recipes`) · Dado o Manufacturing ligado · Quando marco o filtro · Então a tela traz os mesmos produtos que o JSON do
  DataTable da Blade com `only_mfg_products=1`.
- **Status: 🧪**

## Backlog
- [BACKLOG] Cutover pelo menu (D1 [W] 2026-10-07), agora que o §4 do RUNBOOK fechou.
