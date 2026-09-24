---
sessao: "_saida-06"
thread: "06 · Painel — Page"
dono: "[C]"
data: 2026-09-24
base_lida: wagnerra23/oimpresso.com@main 723d2b1e6
prefixo_tocado: "Pages/Essentials/Painel.{tsx,charter.md,casos.md} · DashboardController@hrmDashboard · contracts/essentials-painel.contract.json + FORA do prefixo, declarado abaixo"
---
# _saida-06

## Pedido literal
`/onda Hrm --thread 06` (thread "Painel", playbook em `prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/`).
Presença é do Ponto (D1) — não trazer para o HRM. RESIDUO-6 não decidida aqui.

## Feito
1. `criar-tela.mjs Essentials/Painel PT-04 --prototipo …/hrm-page.jsx --rota hrm/dashboard` carimbou
   o pacote (tsx · charter · casos · e2e · contrato).
2. `DashboardController@hrmDashboard` passa a `Inertia::render('Essentials/Painel')`, com `is_admin`
   eager e o conteúdo em `painel` (`Inertia::defer`). Só agregados que o método já calculava.
3. `Painel.tsx` (PT-04): KPIs como botão (`KpiCard onClick`) que levam à tela dona; 5 cards.
4. Charter e casos preenchidos a partir do playbook §B/§C; UC-PAINEL-00..03 provados por
   `Modules/Essentials/Tests/Feature/HrmPainelTest.php` (tenant 98 × 99, headers do navegador).

## Tabela card × agregado × (dado | —)

| card do protótipo | agregado no `@hrmDashboard` | na Page |
|---|---|---|
| KPI Colaboradores (admin) | `users` + `hrm_department` | dado |
| KPI Licenças pendentes | nenhum (o método só lê licenças **aprovadas**) | `—` + link `/hrm/leave` |
| KPI Presença hoje (admin) | `essentials_attendances` — cedido ao Ponto (D1) | `—` + link `/ponto` |
| KPI Folha 08/2026 | — (D2, thread 10 bloqueada) | fora |
| O que fazer primeiro | nenhum agregado de fila | 2 atalhos sem número (Ponto · Licenças) |
| Custo de folha por setor | — (D2) | fora |
| Minhas licenças | `users_leaves` (aprovadas, próximo mês) | dado |
| Minhas metas de venda | `sales_targets` do usuário | faixas gravadas; **realizado fora** |
| Próximos feriados | `todays_holidays` + `upcoming_holidays` | dado (hoje + próximo mês) |
| Presença de hoje (card) | D1 | fora — o KPI aponta para o Ponto |
| Colaboradores por setor (admin) | `users_by_dept` | dado |

## Não feito, e por quê
- **Realizado de vendas** (`target_achieved_this/last_month`): caminho de valor; a Metas o excluiu
  pela mesma razão (`Metas.charter.md` Non-Goals). As duas chamadas `getUserTotalSales` saíram do
  método porque só a Blade as lia. Relaciona-se com RESIDUO-5, que segue aberta.
- **Licenças da equipe hoje/próximas** (`todays_leaves`/`upcoming_leaves`, que a Blade mostrava):
  o protótipo não tem esse card. Não levei.
- **Remover a Blade `dashboard/hrm_dashboard.blade.php`:** é a thread 11.
- **T7 / smoke de tela em prod:** não verificável daqui; a lane `essentials-pest` é a prova do CI.
- **Nada rodado localmente** além de `php -l`, `tsc`, `pt-conformance`, `contrato:check` e `casos-coverage-guard`.

## Fora do prefixo (necessário para o pacote passar nos gates)
- `memory/requisitos/Essentials/RUNBOOK-painel.md` — o hook `block-mwart-violation` barra a Page sem RUNBOOK.
- `Modules/Essentials/Tests/Feature/HrmPainelTest.php` + 1 linha em `.github/workflows/essentials-pest.yml` — G-2 do casos-gate.
- `e2e/essentials-painel.spec.ts` — stub carimbado, mantido `fixme` (sem fixture autenticado).

## Descobertas
1. **O playbook diz PT-05 (dashboard); no gerador PT-05 é Kanban e o Dashboard é PT-04.** Usei PT-04.
2. `@hrmDashboard` não tem permission própria (só auth + pacote), como a Blade. O charter declara isso.
3. `phpstan-baseline.neon` tem uma entrada do `hrmDashboard` (`should return Response but returns View`)
   que deixa de casar. `reportUnmatchedIgnoredErrors: false`, então não quebra; a limpeza é de quem regera o baseline.
4. `e2e/essentials-tipos.spec.ts` (UC-TIPOS-00, `fixme`) clica num link do topnav Blade a partir de
   `/hrm/dashboard`. Com o Painel em Inertia esse link vem do AppShellV2 ("Tipos de Folga"); o teste
   precisará de ajuste quando sair do `fixme`.
5. O KPI "Licenças pendentes" só ganha número com um agregado novo — fica no backlog do casos.md.
