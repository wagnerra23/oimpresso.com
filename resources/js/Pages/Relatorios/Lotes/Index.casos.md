---
id: resources-js-pages-relatorios-lotes-index-casos
casos: Lotes · /reports/lot-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra o estoque de cada lote; quantidade diferente da Blade, ou lote de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Lotes

> Thread `sistema/playbook/07`. Derivados do `ReportController::getLotReport` (ramo `ajax()`: estoque = comprado − devolvido
> do lote − baixas; vendido e ajustado pelas baixas de `transaction_sell_lines_purchase_lines`) e do rodapé da Blade
> (`__sum_stock` por unidade), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/LotesReportPageTest.php`](../../../../../tests/Feature/Relatorios/LotesReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RLT-01 · Mesmas quantidades que a Blade · `must` `[T0]` `[estoque]`
- **Aceite:** Dado um lote comprado com 100, com baixa por venda de 30 (5 devolvidos) e baixa por ajuste de 10 · Quando
  abro a tela nova e peço o JSON do DataTable da Blade · Então estoque, vendido e ajustado são os mesmos nos dois e batem
  com a conta direta nas tabelas de compra e baixa (65 · 25 · 10).
- **Status: 🧪**

## UC-RLT-02 · Paginação por SKU e rodapé da página · `must`
- **Aceite:** Dado mais lotes do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de SKU e
  lote, e a página 2 traz o resto · E o rodapé soma, por unidade, só as linhas da página.
- **Status: 🧪**

## UC-RLT-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado um lote do negócio 99 · Quando o 98 abre a tela no local do 99 · Então não o vê · E `locais` não traz
  o local do 99 · Dado usuário sem `stock_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Rolo fracionado (US-COM-025 · UC-FLX-18)

> **Âncora:** US-COM-025 em [`memory/requisitos/Compras/SPEC.md`](../../../../../memory/requisitos/Compras/SPEC.md) e o
> UC-FLX-18 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:71`). Por enquanto é só o JSON
> `GET /producao/lotes/{id}/rastreio`, do Modules/Producao: as frações por OS vêm de `pcp_consumos`, tabela de lá.
> Teste: `Modules/Producao/Tests/Feature/ProducaoRoloRastreioContratoTest.php`, lane `verticais-pest.yml` (MySQL).
> A escolha do rolo na baixa é o UC-PCMAT-08, no caso do apontamento. Antes do JSON, a OS já gravava de que lote saiu
> cada fração ([UC-PCMAT-02](../../../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md),
> `ProducaoMaterialContratoTest`); o rastreio só lê esse vínculo.

## UC-RLT-04 · O rolo mostra em que OS cada fração foi usada · `must` `[estoque]`
- **Aceite:** Dado o lote B com 5 un e duas baixas da mesma OS que tiraram 1 e 2 dele · Quando abro o rastreio do lote B ·
  Então vejo as duas frações (1 e 2) com a OS, a etapa, quem apontou e quando · E o saldo é 2 (5 − 1 − 2) · E a soma das
  frações (3) é a mesma do vínculo lote × baixa das `production_sell` · E o lote do outro local continua com 100 e nenhuma fração.
- **Status: 🧪**

## UC-RLT-05 · Só quem vê compras, só a minha empresa · `must` `[T0]`
- **Aceite:** Dado um usuário com `purchase.view` · Quando abre o rastreio de um lote da empresa · Então recebe o saldo e as
  frações · Dado o operador sem `purchase.view` · Então 403 · Dado um lote da empresa 99 · Quando a 98 pede o rastreio dele ·
  Então 404.
- **Status: 🧪**

- [BACKLOG] Separar a fração usada em OS da venda. Lido na consulta (`consultaLotes`), sem teste: a baixa da OS é uma
  `production_sell` com `sell_line_id`, então desconta o estoque do lote mas soma na coluna "vendido".
- [BACKLOG] Mostrar o rastreio do rolo nesta tela (hoje só o JSON do UC-RLT-04).

## Backlog
- [BACKLOG] Filtros de subcategoria e "só produtos fabricados" (Manufacturing), como a Blade.
- [BACKLOG] Tempo relativo até o vencimento, como a Blade.
