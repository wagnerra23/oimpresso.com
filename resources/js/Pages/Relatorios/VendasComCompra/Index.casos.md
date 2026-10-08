---
id: resources-js-pages-relatorios-vendascomcompra-index-casos
casos: Vendas por produto, Detalhado com compra · /reports/product-sell-report-with-purchase?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela diz de qual compra saiu cada item vendido; quantidade diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Vendas por produto, Detalhado com compra

> Thread `sistema/playbook/07`. Derivados do `ReportController::getproductSellReportWithPurchase` (ramo `ajax()`: só venda
> `final`; uma linha por vínculo em `transaction_sell_lines_purchase_lines`; quantidade = a do vínculo; estoque inicial
> escrito no lugar da ref.; ordem pela data da venda, decrescente, no `report.js`), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/VendasComCompraReportPageTest.php`](../../../../../tests/Feature/Relatorios/VendasComCompraReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RVC-01 · Mesma quantidade que a Blade, uma linha por compra de origem · `must` `[T0]`
- **Aceite:** Dado um item vendido de 5 abastecido por duas compras (3 de uma, 2 de outra) · Quando abro a tela nova e peço
  o JSON do DataTable da Blade · Então as duas têm as mesmas 2 linhas, com 3 e 2, que batem com
  `transaction_sell_lines_purchase_lines` · E venda em rascunho não aparece.
- **Status: 🧪**

## UC-RVC-02 · Estoque inicial no lugar da ref. da compra · `must`
- **Aceite:** Dado um item vendido que saiu do estoque inicial · Quando abro a tela nova · Então a coluna da compra diz
  "Estoque inicial", como a Blade.
- **Status: 🧪**

## UC-RVC-03 · Paginação pela data da venda · `must`
- **Aceite:** Dado mais vínculos do que cabem numa página · Quando abro a tela nova · Então ela traz 25, da venda mais
  recente para a mais antiga, e a página 2 traz o resto.
- **Status: 🧪**

## UC-RVC-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma venda a um cliente do negócio 99 · Quando o 98 pede esse cliente · Então não vê nada · E
  `clientes` não traz o do 99 · Dado usuário sem `purchase_n_sell_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Virar aba do relatório Vendas por produto quando o hub de Relatórios (D2) existir.
- [BACKLOG] Filtro por produto e horário do período, como a Blade.
- [BACKLOG] Nº da venda abrindo a venda, como a Blade.
