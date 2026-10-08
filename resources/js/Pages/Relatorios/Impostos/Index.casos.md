---
id: resources-js-pages-relatorios-impostos-index-casos
casos: Relatório de impostos (abas) · /reports/tax-details?tela=nova
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: a tela mostra imposto por alíquota; valor diferente da Blade, ou transação de outro negócio, é incidente fiscal.
owner: wagner
last_run: "2026-10-08"
---

# Casos de Uso & Aceite — Relatório de impostos (abas)

> Thread `sistema/playbook/07`. Derivados do `ReportController::getTaxDetails` (por aba: entrada = compra `received`, saída
> = venda `final`, despesa; coluna por alíquota = Σ imposto do item × (quantidade − devolvido), quebrando o imposto
> composto na proporção das alíquotas que o compõem, + o imposto da própria transação com a mesma quebra) e do
> `tax_report.blade.php` (ordem: entrada e despesa por data crescente, saída por data decrescente; rodapé por coluna),
> lidos em 2026-10-08 — não do `Index.tsx`.
> Teste: [`tests/Feature/Relatorios/ImpostosReportPageTest.php`](../../../../../tests/Feature/Relatorios/ImpostosReportPageTest.php),
> lane `acessos-pest.yml` (MySQL).
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC · ⬜ não verificado · ❌ quebrou.

## UC-RIM-01 · Imposto por alíquota igual ao da Blade, com a quebra do composto · `must` `[T0]` `[valor]`
- **Aceite:** Dado uma compra com um item na alíquota de 10% (imposto 2 por unidade, 3 comprados, 1 devolvido), um item
  num imposto composto de 5% + 15% (imposto 4, 1 unidade) e imposto da própria compra de 5 na alíquota de 10% · Quando
  abro a aba entrada e peço o JSON do DataTable da Blade · Então as colunas são as mesmas nos dois: 10% = 9, 5% = 1,
  15% = 3, composto = 4.
- **Status: 🧪**

## UC-RIM-02 · Cada aba com o seu tipo de transação · `must`
- **Aceite:** Dado uma compra recebida, uma venda finalizada e uma despesa, todas com imposto · Quando troco de aba ·
  Então entrada mostra só a compra, saída só a venda e despesa só a despesa · E compra pendente e venda em rascunho não
  aparecem.
- **Status: 🧪**

## UC-RIM-03 · Paginação na ordem de cada aba · `must`
- **Aceite:** Dado mais compras do que cabem numa página · Quando abro a aba entrada · Então ela traz 25, da mais antiga
  para a mais recente, e a página 2 traz o resto · E o rodapé soma o total só das linhas da página.
- **Status: 🧪**

## UC-RIM-04 · Só o meu negócio, com a mesma permissão da Blade · `must` `[T0]`
- **Aceite:** Dado uma compra de um fornecedor do negócio 99 · Quando o 98 filtra por esse fornecedor · Então não vê nada
  · Dado usuário sem `tax_report.view` · Então 403 na tela nova e na Blade.
- **Status: 🧪**

## UC-RIM-05 · Resumo "saída menos entrada" igual ao da Blade · `must` `[T0]` `[valor]`
- **Aceite:** Dado uma compra com imposto 5 e um item com 2 de imposto (3 comprados, 1 devolvido), uma venda finalizada
  com imposto 12 e um item com 1 de imposto (2 vendidos), uma venda em rascunho e uma despesa com imposto 3 · Quando abro
  `/reports/tax-report?tela=nova` e peço o JSON do topo da Blade · Então a diferença é a mesma nos dois: 14 − 9 − 3 = 2.
- **Status: 🧪**

## Backlog
- [BACKLOG] Abas que outros módulos injetam na página de impostos, como a Blade.
