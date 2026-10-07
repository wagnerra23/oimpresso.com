---
id: resources-js-pages-relatorios-tendencia-index-casos
casos: Produtos em tendência · /reports/trending-products?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: o ranking orienta compra e estoque; ordem ou quantidade diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Produtos em tendência

> Thread `sistema/playbook/07`. Derivados do `ReportController::getTrendingProducts` + `ProductUtil::getTrendingProducts`
> (quantidade vendida − devolvida, só venda `final`, limite 5 por padrão), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/TendenciaReportPageTest.php`](../../../../../tests/Feature/Relatorios/TendenciaReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RTE-01 · Mesmo período, mesmo ranking que o gráfico da Blade · `must` `[T0]`
- **Aceite:** Dado no período vendas finais de dois produtos (uma com devolução parcial) e uma venda em rascunho ·
  Quando abro a tela nova e a Blade no mesmo período · Então os produtos, a ordem e as quantidades da Page são os do
  gráfico da Blade e batem com a soma direta em `transaction_sell_lines` (quantidade − devolvida) · E o rascunho não
  entra (controle).
- **Status: 🧪**

## UC-RTE-02 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado vendas do mesmo período no negócio 99 · Quando o 98 abre a tela · Então o ranking não muda · E
  `locais` não traz local do 99 · Dado usuário sem `trending_product_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Filtro de subcategoria (na Blade carrega por AJAX a partir da categoria).
- [BACKLOG] Gráfico de colunas, como a Blade e o protótipo.
- [BACKLOG] Colunas "Categoria" e "Total vendido (R$)" do protótipo — exigem a consulta (`ProductUtil::getTrendingProducts`) devolver categoria e valor; hoje devolve só produto, SKU, unidade e quantidade.
