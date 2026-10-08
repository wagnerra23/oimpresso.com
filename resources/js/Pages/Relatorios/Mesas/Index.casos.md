---
id: resources-js-pages-relatorios-mesas-index-casos
casos: Relatório por mesa · /reports/table-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto cada mesa vendeu; total diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Relatório por mesa

> Thread `sistema/playbook/07`. Derivados do `ReportController::getTableReport` (ramo `ajax()`: só venda `final`; total =
> Σ `final_total` por mesa; período por data da venda) e do `table_report.blade.php` (período padrão = mês corrente;
> ordem pela coluna da mesa), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/MesasReportPageTest.php`](../../../../../tests/Feature/Relatorios/MesasReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RME-01 · Mesmo total por mesa que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado uma mesa com duas vendas finalizadas no período (100 e 50), uma em rascunho e uma fora do período ·
  Quando abro a tela nova e peço o JSON do DataTable da Blade · Então o total é 150 nos dois e bate com a soma direta
  em `transactions`.
- **Status: 🧪**

## UC-RME-02 · Paginação pelo nome da mesa · `must`
- **Aceite:** Dado mais mesas do que cabem numa página · Quando abro a tela nova · Então ela traz 25, em ordem de nome,
  e a página 2 traz o resto.
- **Status: 🧪**

## UC-RME-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma venda numa mesa do negócio 99 · Quando o 98 pede o local dela · Então não vê nada · Dado usuário
  sem `purchase_n_sell_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Decidir se o relatório deve respeitar os locais permitidos do usuário (achado no RUNBOOK §4).
