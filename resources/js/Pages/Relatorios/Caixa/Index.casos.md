---
id: resources-js-pages-relatorios-caixa-index-casos
casos: Caixa (registro) · /reports/register-report?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra quanto entrou em cada caixa; total diferente da Blade, ou caixa de outro negócio, é incidente.
owner: wagner
last_run: "2026-10-07"
---

# Casos de Uso & Aceite — Caixa (registro)

> Thread `sistema/playbook/07`. Derivados do `ReportController::getRegisterReport` (ramo `ajax()`: valores por forma de
> `TransactionUtil::registerReport` e a coluna `total`, que somava as 13 formas inline) e do `footerCallback` da Blade
> (soma por coluna da página), lidos em 2026-10-07 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/CaixaReportPageTest.php`](../../../../../tests/Feature/Relatorios/CaixaReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RCX-01 · Mesmos valores e mesmo total que a Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado um caixa com recebimentos de venda em dinheiro, cartão e uma forma personalizada (e uma devolução,
  que não entra) · Quando abro a tela nova e peço o JSON do DataTable da Blade · Então os valores por forma e o total
  são os mesmos nos dois, batem com a soma direta em `cash_register_transactions` · E o total da Blade continua
  igual à soma das formas (a conta saiu do `addColumn` e foi para `totalDoCaixa`).
- **Status: 🧪**

## UC-RCX-02 · Paginação na ordem da Blade e rodapé da página · `must`
- **Aceite:** Dado mais caixas do que cabem numa página · Quando abro a tela nova · Então ela traz 25, do mais antigo
  para o mais novo, e a página 2 traz o resto · E o rodapé soma só as linhas da página.
- **Status: 🧪**

## UC-RCX-03 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado um caixa do negócio 99 · Quando o 98 abre a tela · Então não o vê · E `usuarios` não traz usuário
  do 99 · Dado usuário sem `register_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## Backlog
- [BACKLOG] Ações da linha da Blade: ver caixa e fechar caixa.
