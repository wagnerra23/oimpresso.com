---
id: resources-js-pages-relatorios-gruposclientes-index-casos
casos: Grupos de clientes · /reports/customer-group?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto cada grupo comprou; número diferente da Blade ou venda de outro negócio é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Grupos de clientes

> Thread `sistema/playbook/07`. Derivados do `ReportController::getCustomerGroup` (ramo `ajax()` do DataTable) e de
> `resources/views/report/customer_group.blade.php`, lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/GruposClientesReportPageTest.php`](../../../../../tests/Feature/Relatorios/GruposClientesReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RGC-01 · Mesmo período, mesmas linhas que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado no período vendas finais em dois grupos e uma venda em rascunho · Quando abro a tela nova e
  peço o JSON do DataTable da Blade no mesmo período · Então as linhas (grupo → total) são as mesmas e batem com
  a soma direta em `transactions` · E o rascunho não entra (controle).
- **Status: 🧪**

## UC-RGC-02 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado as mesmas vendas no negócio 99 · Quando o 98 abre a tela · Então as linhas não mudam · E
  `grupos` e `locais` não trazem nada do 99 · Dado usuário sem `contacts_report.view` · Então 403 na tela nova e
  na Blade.
- **Status: 🧪**
