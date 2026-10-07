---
id: resources-js-pages-relatorios-despesas-index-casos
casos: Despesas · /reports/expense-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto o negócio gastou; total diferente da Blade ou despesa de outro negócio é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Despesas

> Thread `sistema/playbook/07`. Derivados do `ReportController::getExpenseReport` +
> `TransactionUtil::getExpenseReport` + `resources/views/report/expense_report.blade.php` (o `tfoot` soma as
> linhas), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/DespesasReportPageTest.php`](../../../../../tests/Feature/Relatorios/DespesasReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RDE-01 · Mesmo período, mesmas linhas e mesmo total que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado no período despesas em duas categorias, uma sem categoria e uma devolução de despesa ·
  Quando abro a tela nova e a Blade no mesmo período · Então as linhas e o total da Page são os mesmos que a
  Blade recebe e batem com a soma direta em `transactions` (devolução negativa) · E uma despesa fora do
  período não entra (controle).
- **Status: 🧪**

## UC-RDE-02 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado as mesmas despesas no negócio 99 · Quando o 98 abre a tela · Então linhas e total não mudam
  · E `categorias` e `locais` não trazem nada do 99 · Dado usuário sem `expense_report.view` · Então 403 na
  tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Gráfico por categoria, se [W] quiser manter o da Blade.
