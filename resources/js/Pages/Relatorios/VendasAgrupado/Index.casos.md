---
id: resources-js-pages-relatorios-vendasagrupado-index-casos
casos: Vendas por produto, Agrupado · /reports/product-sell-grouped-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela soma o que saiu de cada produto por dia; número diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Vendas por produto, Agrupado

> Thread `sistema/playbook/07`. Derivados do `ReportController::getproductSellGroupedReport` (ramo `ajax()`: só venda
> `final`; agrupa por variação e dia; quantidade = Σ (vendido − devolvido); subtotal = Σ quantidade × preço com imposto;
> estoque atual = Σ `variation_location_details.qty_available`; ordem pelo SKU, decrescente, no `report.js`) e do rodapé
> da aba "Agrupado" da Blade, lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/VendasAgrupadoReportPageTest.php`](../../../../../tests/Feature/Relatorios/VendasAgrupadoReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RVA-01 · Soma do dia igual à da Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado duas vendas finalizadas do mesmo produto no mesmo dia (3 a 10 e 2 a 10, com 1 devolvido) e uma em
  rascunho · Quando abro a tela nova e peço o JSON do DataTable da Blade · Então há uma linha só, com quantidade 4 e
  subtotal 40 nos dois, que batem com a conta direta em `transaction_sell_lines`.
- **Status: 🧪**

## UC-RVA-02 · Um dia por linha · `must`
- **Aceite:** Dado o mesmo produto vendido em dois dias · Quando abro a tela nova · Então há duas linhas, uma por dia, do
  dia mais recente para o mais antigo (a Blade ordena só pelo SKU; o dia é o desempate da tela nova).
- **Status: 🧪**

## UC-RVA-03 · Paginação pelo SKU · `must`
- **Aceite:** Dado mais linhas do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de SKU
  decrescente, e a página 2 traz o resto.
- **Status: 🧪**

## UC-RVA-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma venda a um cliente do negócio 99 · Quando o 98 pede esse cliente · Então não vê nada · E
  `clientes` não traz o do 99 · Dado usuário sem `purchase_n_sell_report.view` · Então 403 na tela nova e no endpoint da
  Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Virar aba do relatório Vendas por produto quando o hub de Relatórios (D2) existir.
- [BACKLOG] Filtro por produto e horário do período, como a Blade.
