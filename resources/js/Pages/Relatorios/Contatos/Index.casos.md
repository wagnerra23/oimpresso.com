---
id: resources-js-pages-relatorios-contatos-index-casos
casos: Clientes e fornecedores · /reports/customer-supplier?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto cada contato deve; valor diferente da Blade, ou contato de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Clientes e fornecedores

> Thread `sistema/playbook/07`. Derivados do `ReportController::getCustomerSuppliers` (ramo `ajax()`: somas por tipo de
> transação e a coluna `due`) e do rodapé da Blade (`sum_table_col` da página), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/ContatosReportPageTest.php`](../../../../../tests/Feature/Relatorios/ContatosReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RCT-01 · Mesmas colunas e mesmo devido que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado um cliente com venda final, pagamento parcial e devolução de venda no período · Quando abro a tela
  nova e peço o JSON do DataTable da Blade · Então vendas, devoluções e devido são os mesmos nos dois e batem com a
  soma direta em `transactions`/`transaction_payments` · E o devido segue a conta que está em produção (o desconto de
  razão de venda não entra — ver RUNBOOK §4).
- **Status: 🧪**

## UC-RCT-02 · Paginação por nome e rodapé da página · `must`
- **Aceite:** Dado mais contatos com movimento do que cabem numa página · Quando abro a tela nova · Então ela traz 25,
  em ordem de nome, e a página 2 traz o resto · E o rodapé soma só as linhas da página.
- **Status: 🧪**

## UC-RCT-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado um contato com movimento no negócio 99 · Quando o 98 abre a tela · Então não o vê · E `contatos`
  não traz o do 99 · Dado usuário sem `contacts_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Nome do contato como link para a ficha, como a Blade.
- [BACKLOG] Decisão [W]: incluir o desconto de razão de venda no devido (corrige o número; precisa de antes→depois).
