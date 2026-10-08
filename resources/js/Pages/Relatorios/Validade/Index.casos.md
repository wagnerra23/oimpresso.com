---
id: resources-js-pages-relatorios-validade-index-casos
casos: Validade de estoque · /reports/stock-expiry?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela diz quanto ainda há de cada lote e quando vence; saldo diferente da Blade, ou estoque de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Validade de estoque

> Thread `sistema/playbook/07`. Derivados do `ReportController::getStockExpiryReport` (ramo `ajax()`: só produto com
> controle de estoque; saldo = comprado − vendido − ajustado − devolvido, agrupado por variação × validade × lote; saldo
> zero fica fora; filtro "Ver estoque" = validade até a data da faixa; ordem pela validade, crescente, no `report.js`) e
> do rodapé da Blade, lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/ValidadeReportPageTest.php`](../../../../../tests/Feature/Relatorios/ValidadeReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RVL-01 · Mesmo saldo que a Blade · `must` `[T0]` `[estoque]`
- **Aceite:** Dado um lote comprado de 10, com 3 vendidos, 1 ajustado e 1 devolvido · Quando abro a tela nova e peço o
  JSON do DataTable da Blade · Então o saldo é 5 nos dois e bate com a conta direta em `purchase_lines` · E um lote já
  zerado não aparece.
- **Status: 🧪**

## UC-RVL-02 · Filtro de faixa de validade · `must`
- **Aceite:** Dado um lote que vence em 5 dias e outro em 2 anos · Quando escolho "vence em 1 semana" · Então só o
  primeiro aparece.
- **Status: 🧪**

## UC-RVL-03 · Paginação pela validade · `must`
- **Aceite:** Dado mais lotes do que cabem numa página · Quando abro a tela nova · Então ela traz 25, do que vence antes
  para o que vence depois, e a página 2 traz o resto · E o rodapé soma o saldo só das linhas da página.
- **Status: 🧪**

## UC-RVL-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado um lote do negócio 99 · Quando o 98 abre a tela nova · Então não o vê · Dado usuário sem
  `stock_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Filtros de subcategoria e "só produtos de fabricação", como a Blade.
