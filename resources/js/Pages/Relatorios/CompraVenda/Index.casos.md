---
id: resources-js-pages-relatorios-compravenda-index-casos
casos: Compras e vendas · /reports/purchase-sell?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto o negócio comprou, vendeu e deve; número diferente da Blade ou de outro negócio é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Compras e vendas

> Thread `sistema/playbook/07`. Derivados do `ReportController::getPurchaseSell` + `TransactionUtil`
> (`getPurchaseTotals`, `getSellTotals`, `getTransactionTotals`) + `public/js/report.js`
> (`updatePurchaseSell`), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/CompraVendaReportPageTest.php`](../../../../../tests/Feature/Relatorios/CompraVendaReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RCV-01 · Mesmo filtro, mesmos totais que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado no período uma compra, uma venda final, uma devolução de compra e uma de venda, com
  pagamentos parciais · Quando peço `/reports/purchase-sell` (ajax) · Então os 10 números (4 de compras,
  4 de vendas, as 2 diferenças) batem com a soma refeita direto em `transactions`/`transaction_payments`
  · E uma venda em rascunho no mesmo período não entra (controle) · E a Page não recebe total nenhum:
  mostra o JSON do mesmo endpoint que a Blade usa.
- **Status: 🧪**

## UC-RCV-02 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado as mesmas transações também no negócio 99 · Quando o 98 pede o JSON · Então os totais
  não mudam · E `locais` da Page não traz local do 99 · Dado usuário sem `purchase_n_sell_report.view` ·
  Então 403 na tela nova, no JSON e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Filtro por cliente, como no protótipo — exige o endpoint filtrar por cliente.
- [BACKLOG] Totais limitados aos locais permitidos do usuário — a Blade não limita; decisão [W].
