---
id: resources-js-pages-relatorios-itensporatendente-index-casos
casos: Equipe de serviço, itens por atendente · /reports/service-staff-line-orders?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra o que cada atendente vendeu; valor diferente da Blade, ou venda de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Equipe de serviço, itens por atendente

> Thread `sistema/playbook/07`. Derivados do `ReportController::serviceStaffLineOrders` (só venda `final`, só item com
> atendente; desconto percentual = preço antes do desconto × % ÷ 100; total = preço com imposto × quantidade) e do
> `service_staff_report.blade.php` (ordem pela data, decrescente; período padrão = mês corrente; rodapé por coluna),
> lidos em 2026-10-08 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/ItensPorAtendenteReportPageTest.php`](../../../../../tests/Feature/Relatorios/ItensPorAtendenteReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RIA-01 · Mesmo desconto e mesmo total que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado um item de 3 unidades, preço antes do desconto 50 com 10% de desconto, preço com imposto 47 · Quando
  abro a tela nova e peço o JSON do DataTable da Blade · Então quantidade 3, desconto 5 e total 141 nos dois, que batem
  com a conta direta em `transaction_sell_lines` · E item sem atendente ou de venda em rascunho não aparece.
- **Status: 🧪**

## UC-RIA-02 · Paginação pela data · `must`
- **Aceite:** Dado mais itens do que cabem numa página · Quando abro a tela nova · Então ela traz 25, do mais recente
  para o mais antigo, e a página 2 traz o resto · E o rodapé soma o total só das linhas da página.
- **Status: 🧪**

## UC-RIA-03 · Só o meu negócio, com a permissão da página da Blade · `must` `[T0]`
- **Aceite:** Dado um item vendido por atendente do negócio 99 · Quando o 98 filtra por esse atendente · Então não vê
  nada · Dado usuário sem `sales_representative.view` · Então 403 na tela nova e na página da Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Aba "pedidos por atendente" (vem do `SellController`, fora do `ReportController`).
- [BACKLOG] Decidir permissão e locais permitidos no endpoint JSON (achado no RUNBOOK §4).
