---
id: resources-js-pages-relatorios-lucroabas-index-casos
casos: Abas de lucro · /reports/get-profit/{aba}?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra de onde vem o lucro; valor diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Abas de lucro

> Thread `sistema/playbook/07`. Derivados do `ReportController::getProfit` (lucro bruto do item = (quantidade do vínculo −
> devolvida) × (preço de venda com imposto − preço de compra com imposto); na aba "por venda" o editColumn desconta o
> desconto da venda — percentual sobre o total sem imposto, ou fixo; aba "por dia" agrupa pelo dia da semana) e do
> `profit_loss.blade.php` (ordem pela coluna da aba, crescente; rodapé somando a página), lidos em 2026-10-08 — não do
> `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/LucroAbasReportPageTest.php`](../../../../../tests/Feature/Relatorios/LucroAbasReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RLA-01 · Lucro por produto igual ao da Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado num local próprio uma venda de 3 unidades a 50, compradas a 30 · Quando abro a aba "por produto" e peço
  o JSON do DataTable da Blade · Então o lucro é o mesmo nos dois e bate com a conta à mão: 3 × (50 − 30) = 60.
- **Status: 🧪**

## UC-RLA-02 · Aba "por venda" com o desconto da venda · `must` `[T0]` `[valor]`
- **Aceite:** Dado essa venda com desconto fixo de 10 e outra igual com desconto de 10% sobre o total sem imposto de 150
  · Quando abro a aba "por venda" e peço o JSON da Blade · Então os lucros são os mesmos nos dois: 60 − 10 = 50 e
  60 − 15 = 45.
- **Status: 🧪**

## UC-RLA-03 · Aba "por dia" com os sete dias · `must`
- **Aceite:** Dado a venda numa segunda-feira · Quando abro a aba "por dia" · Então vêm os sete dias, segunda com 60 e os
  outros com zero.
- **Status: 🧪**

## UC-RLA-04 · Só o meu negócio · `must` `[T0]`
- **Aceite:** Dado uma venda do negócio 99 · Quando o 98 pede o local dela · Então não vê nada (a permissão do endpoint é
  coberta pelo `LucroAbasSegurancaTest`).
- **Status: 🧪**
