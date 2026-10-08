---
id: resources-js-pages-relatorios-vendasproduto-index-casos
casos: Vendas por produto · /reports/product-sell-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto saiu de cada produto e por quanto; número diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Vendas por produto

> Thread `sistema/playbook/07`. Derivados do `ReportController::getproductSellReport` (ramo `ajax()`: só venda `final`;
> quantidade = vendido − devolvido; subtotal = quantidade × preço com imposto; ordem pelo nº da venda, decrescente, no
> `report.js`) e do rodapé da aba "Detalhado" da Blade (`sum_table_col` e `__sum_stock` ignoram a linha filha de combo),
> lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/VendasProdutoReportPageTest.php`](../../../../../tests/Feature/Relatorios/VendasProdutoReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RVP-01 · Mesma quantidade e mesmo subtotal que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado um item de venda finalizada de 5 a 20 com imposto, 1 devolvido · Quando abro a tela nova e peço o JSON
  do DataTable da Blade · Então quantidade e subtotal são os mesmos nos dois e batem com a conta direta em
  `transaction_sell_lines` (4 · 80) · E venda em rascunho não aparece.
- **Status: 🧪**

## UC-RVP-02 · Rodapé da página sem a linha filha de combo · `must` `[valor]`
- **Aceite:** Dado um combo (linha mãe 2 a 30 e linha filha 2 a 10) · Quando abro a tela nova · Então as duas linhas
  aparecem · E o rodapé soma só a mãe: quantidade 2, subtotal 60.
- **Status: 🧪**

## UC-RVP-03 · Paginação pelo nº da venda · `must`
- **Aceite:** Dado mais itens do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de nº da
  venda decrescente, e a página 2 traz o resto.
- **Status: 🧪**

## UC-RVP-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma venda a um cliente do negócio 99 · Quando o 98 pede esse cliente · Então não vê nada · E
  `clientes` não traz o do 99 · Dado usuário sem `purchase_n_sell_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Abas "Detalhado com compra" e "Agrupado", como a Blade.
- [BACKLOG] Filtro por produto, horário do período e colunas de campo personalizado, como a Blade.
- [BACKLOG] Nº da venda abrindo a venda, como a Blade.
