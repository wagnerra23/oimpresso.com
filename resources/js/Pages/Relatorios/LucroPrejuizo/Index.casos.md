---
id: resources-js-pages-relatorios-lucroprejuizo-index-casos
casos: Lucro e prejuízo · /reports/profit-loss?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela diz se o negócio deu lucro; número diferente da Blade, ou movimento de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Lucro e prejuízo

> Thread `sistema/playbook/07`. Derivados do `ReportController::getProfitLoss` (números de `getProfitLossDetails`, com
> período padrão = ano fiscal), do `TransactionUtil::getProfitLossDetails` (lucro líquido = lucro bruto + arredondamento
> + recuperado + frete de venda + desconto de compra + despesa adicional de venda + desconto de devolução − recompensa −
> despesas − ajustes − fretes de transferência e de compra − despesa adicional de compra − desconto de venda) e do
> `net_gross_profit_report_details.blade.php` (CMV = estoque inicial − compras + estoque final), lidos em 2026-10-08 —
> não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/LucroPrejuizoReportPageTest.php`](../../../../../tests/Feature/Relatorios/LucroPrejuizoReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RLP-01 · Mesmos números que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado num local próprio uma venda finalizada de 100 (sem imposto) com frete de 10 e uma despesa de 30 ·
  Quando abro a tela nova e peço o HTML do partial da Blade · Então total de vendas, frete das vendas, total de despesas,
  lucro bruto e lucro líquido são os mesmos nos dois · E total de vendas 100, frete 10 e despesas 30 batem com a soma
  direta em `transactions` · E o lucro líquido = lucro bruto + 10 − 30.
- **Status: 🧪**

## UC-RLP-02 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma despesa do negócio 99 · Quando o 98 abre a tela nova no mesmo período · Então ela não entra no
  total de despesas · Dado usuário sem `profit_loss_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Abas de lucro por produto, categoria, marca, local, venda, data, cliente e dia, como a Blade.
- [BACKLOG] Decidir permissão no endpoint de estoque pelo preço de venda (achado no RUNBOOK §4).
