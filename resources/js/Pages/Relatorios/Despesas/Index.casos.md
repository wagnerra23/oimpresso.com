---
id: resources-js-pages-relatorios-despesas-index-casos
casos: Despesas · /reports/expense-report
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

## UC-RDE-03 · O menu abre a tela nova; a Blade só com `?classico=1` · `must`
> Fonte: a regra de cutover D1 [W] 2026-10-07 (*por tela, React vira padrão, Blade só com `?classico=1`*), registrada
> na US-REL-002 do SPEC de Relatorios. O controller só confirma.
- **Persona:** usuário com `expense_report.view` que entra em Relatórios › Despesas pelo menu.
- **Aceite:** Dado o cutover pelo menu · Quando abro `/reports/expense-report` como o menu navega (sem `X-Inertia`) ·
  Então recebo a Page · E a visita Inertia (`X-Inertia` + `X-Requested-With`) também · E `?tela=nova` continua
  valendo · E com `?classico=1` recebo a Blade, cujo formulário de filtro leva `classico=1` para não cair na Page.
- **Teste:** `tests/Feature/Relatorios/DespesasReportPageTest.php` — `UC-RDE-03 cutover — o menu abre a Page;
  ?classico=1 abre a Blade, com o classico no formulário`. Lane `acessos-pest.yml` (MySQL).
- **Regressão que defende:** o menu voltar a abrir a Blade (cutover desfeito sem decisão), e o filtro da Blade em
  `?classico=1` jogar o usuário na Page porque o formulário perdeu o `classico`.
- **Status: ⬜** — o teste cita o UC, mas a lane ainda não rodou neste PR (2026-10-27); vira 🧪 com o run e as
  assertions do CI.

## Backlog
- [BACKLOG] Gráfico por categoria, se [W] quiser manter o da Blade.
