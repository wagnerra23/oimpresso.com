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

## Backlog
- [BACKLOG] Resumo do topo (diferença de imposto, com o imposto de saída dos módulos) e abas de módulos, como a Blade.
