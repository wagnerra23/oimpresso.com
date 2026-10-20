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
> UC-FLX-18 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php:71`: *"abre o rolo como lote e registra
> em que OS cada fração foi usada"*, estado `parcial`). Esta tela é a dona porque é a que já mostra o estoque por lote
> (`purchase_lines.lot_number`, ligado em `business.enable_lot_number`).
>
> **O que já está provado** fica no caso da baixa, sem repetir aqui: a OS grava de que lote saiu cada fração —
> [UC-PCMAT-02](../../../../../Modules/Producao/Resources/js/Pages/Producao/Apontamento/Index.casos.md) (3 un baixadas
> saem 2 do lote A e 1 do B, conferido por `transaction_sell_lines_purchase_lines` com `production_purchase` e por
> `purchase_lines.mfg_quantity_used`), e `pcp_consumos` liga essa baixa à OS (`ordem_id` → `transaction_sell_line_id`),
> com teste em `ProducaoMaterialContratoTest`. O lote é escolhido sozinho pelo método da empresa (FIFO/LIFO,
> `TransactionUtil::mapPurchaseSell`).

- [BACKLOG] Abrir um lote e ver cada OS que usou fração dele: número da OS, etapa, quantidade, data e quem apontou.
- [BACKLOG] Saldo do rolo = recebido − devolvido − soma das frações, e a soma das frações listadas igual ao que está em
  `transaction_sell_lines_purchase_lines` para aquele lote (dois caminhos no teste).
- [BACKLOG] Separar a fração usada em OS da venda. Lido na consulta (`consultaLotes`), sem teste: a baixa da OS é uma
  `production_sell` com `sell_line_id`, então desconta o estoque do lote mas soma na coluna "vendido".
- [BACKLOG] Escolher o rolo na baixa da OS (`lot_no_line_id`, que o `mapPurchaseSell` já aceita e a baixa não passa);
  lote de outro produto ou sem saldo recusa.
- [BACKLOG] Lote de outra empresa responde 404 no rastreio; sem permissão de ver compras, 403.

## Backlog
- [BACKLOG] Filtros de subcategoria e "só produtos fabricados" (Manufacturing), como a Blade.
- [BACKLOG] Tempo relativo até o vencimento, como a Blade.
