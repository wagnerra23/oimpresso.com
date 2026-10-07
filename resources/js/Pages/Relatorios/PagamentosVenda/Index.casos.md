---
id: resources-js-pages-relatorios-pagamentosvenda-index-casos
casos: Pagamentos de venda · /reports/sell-payment-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto entrou de cada venda; valor ou sinal diferente da Blade, ou pagamento de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Pagamentos de venda

> Thread `sistema/playbook/07`. Derivados do `ReportController::sellPaymentReport` (ramo `ajax()` do DataTable: troco
> devolvido negativo em `editColumn('amount')`, ordem por `paid_on` desc em `public/js/report.js`) e do rodapé da
> Blade (`sum_table_col` da página), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/PagamentosVendaReportPageTest.php`](../../../../../tests/Feature/Relatorios/PagamentosVendaReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RPV-01 · Mesmos pagamentos e mesmo sinal que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado no período pagamentos de vendas de um cliente, um deles troco devolvido · Quando abro a tela nova
  e peço o JSON do DataTable da Blade com o mesmo filtro · Então os valores por pagamento são os mesmos (troco
  negativo), batem com a soma direta em `transaction_payments` · E o total da página é a soma das linhas exibidas.
- **Status: 🧪**

## UC-RPV-02 · Paginação no servidor, mais recente primeiro · `must`
- **Aceite:** Dado mais pagamentos do que cabem numa página · Quando abro a tela nova · Então ela traz 25 linhas, em
  ordem de `pago em` decrescente, e a página 2 traz o resto · E o total da página 1 é só das 25 da página 1.
- **Status: 🧪**

## UC-RPV-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado pagamentos do mesmo cliente-filtro no negócio 99 · Quando o 98 abre a tela · Então as linhas não
  mudam · E `clientes` e `locais` não trazem nada do 99 · Dado usuário sem `purchase_n_sell_report.view` · Então
  403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Ações da linha da Blade: ver pagamento, baixar documento, abrir a venda.
- [BACKLOG] Detalhe da forma de pagamento (nº do cheque, do cartão, da conta, nº da transação).
