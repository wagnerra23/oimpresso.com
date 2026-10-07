---
id: resources-js-pages-report-salesrepresentative-index-casos
casos: Comissão por vendedor · /reports/sales-representative-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto o vendedor recebe; número diferente do relatório antigo ou comissão de outro negócio é incidente.
owner: wagner
last_run: "2026-10-06"
---

# Casos de Uso & Aceite — Comissão por vendedor

> Thread `comissoes/playbook/02`. Derivados da ficha da thread e do `ReportController` (linhas 1221–1360) +
> `public/js/report.js` (`salesRepresentativeTotal*`), medidos em 2026-10-06 — não do `Index.tsx`.
> Teste: [`tests/Feature/Users/SalesRepresentativeReportPageTest.php`](../../../../../tests/Feature/Users/SalesRepresentativeReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-COM-03 · Mesmo filtro, mesmo total que o relatório antigo · `must` `[T0]` `[valor]`
- **Aceite:** Dado vendedor (5%), período e local, com uma venda de 2 × 150 · Quando peço
  `/reports/sales-representative-total-commission` · Então `total_sales_with_commission = 300` e
  `total_commission = 15`, igual à conta refeita no teste · E outro vendedor no mesmo filtro dá 0 (controle
  positivo) · E a Page `Report/SalesRepresentative/Index` não recebe total nenhum: mostra o JSON dos mesmos
  três endpoints que a Blade usa.
- **Status: 🧪**

## UC-COM-04 · Mesma permissão da Blade, vendedores só do meu negócio · `must` `[T0]`
- **Medido antes:** a Blade e os três endpoints exigem só `sales_representative.view`, e o filtro de vendedor
  lista todos os usuários do negócio — ela **não** trava no usuário logado. A tela mantém (travar é decisão [W]).
- **Aceite:** Dado `sales_representative.view` · Então `vendedores` traz usuário do meu negócio e não traz o
  de outro negócio · Dado usuário sem a permissão · Então 403, na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] As 4 abas de listagem da Blade (vendas, vendas com comissão, despesas, pagamentos com comissão) — PR seguinte, antes do cutover.
- [BACKLOG] Travar o vendedor no usuário logado quando ele não for gestor — decisão [W] (a Blade não faz).
