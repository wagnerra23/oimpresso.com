---
id: resources-js-pages-relatorios-comprasproduto-index-casos
casos: Compras por produto · /reports/product-purchase-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto entrou de cada produto e a que custo; número diferente da Blade, ou compra de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Compras por produto

> Thread `sistema/playbook/07`. Derivados do `ReportController::getproductPurchaseReport` (ramo `ajax()`: quantidade =
> comprado − devolvido; subtotal = (comprado − devolvido − ajustado) × preço com imposto; ordem pela ref. da compra,
> decrescente, no `report.js`) e do rodapé da Blade, lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/ComprasProdutoReportPageTest.php`](../../../../../tests/Feature/Relatorios/ComprasProdutoReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RCP-01 · Mesma quantidade e mesmo subtotal que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado um item de compra de 10 a 12 com imposto, 2 devolvidos e 1 ajustado · Quando abro a tela nova e peço o
  JSON do DataTable da Blade · Então quantidade, ajustado e subtotal são os mesmos nos dois e batem com a conta direta em
  `purchase_lines` (8 · 1 · 84).
- **Status: 🧪**

## UC-RCP-02 · Paginação pela ref. da compra e rodapé da página · `must`
- **Aceite:** Dado mais itens do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de ref. da
  compra decrescente, e a página 2 traz o resto · E o rodapé soma só as linhas da página.
- **Status: 🧪**

## UC-RCP-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma compra de um fornecedor do negócio 99 · Quando o 98 pede esse fornecedor · Então não vê nada · E
  `fornecedores` não traz o do 99 · Dado usuário sem `purchase_n_sell_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Filtro por produto (busca com autocompletar), como a Blade.
- [BACKLOG] Ref. da compra abrindo a compra, como a Blade.
