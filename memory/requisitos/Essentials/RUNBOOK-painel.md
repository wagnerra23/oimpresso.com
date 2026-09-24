---
title: "RUNBOOK — Painel do HRM (Blade → Inertia · thread 06 do playbook hrm)"
module: Essentials
tela: Essentials/Painel
owner: W
status: ativo
last_validated: "2026-09-24"
preconditions:
  - "Rota /hrm/dashboard (name hrmDashboard), menu nav_hrm e ghost do DataController JÁ existem — nenhum alcance novo"
  - "Thread 09 entregue: presença cedida ao Ponto (ADR 0014, emenda 2026-09-05/2026-09-24)"
steps:
  - "F1 PLAN — este documento"
  - "F2 BACKEND BASELINE — HrmPainelTest (tenant 98 vs adversário 99) no mesmo PR da Page"
  - "F3 FRONTEND — Inertia::render('Essentials/Painel') + Painel.tsx"
  - "F4 QA — lane essentials-pest (MySQL real), nunca local"
  - "F5 CUTOVER — a Blade dashboard/hrm_dashboard deixa de ser renderizada; a remoção do arquivo é a thread 11"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0358-doutrina-de-teste-tenant-98-supersede-0101
  - 0014-essentials-pontowr2-integracao
---

# RUNBOOK — Painel do HRM (`/hrm/dashboard`)

> Thread **06** do playbook `prototipo-ui/cowork/Wagner/cowork-inbox/hrm/playbook/`. Alvo de
> layout: `prototipo-ui/cowork/Wagner/hrm-page.jsx` (`Painel`). Irmã golden: `Essentials/Metas`.

| Rota | Blade legado | Component Inertia | Controller |
|---|---|---|---|
| `/hrm/dashboard` | `dashboard/hrm_dashboard.blade.php` | `Essentials/Painel.tsx` | `DashboardController@hrmDashboard` |

## Regra de dado

O método **já** calculava estes agregados para a Blade; a Page recebe os mesmos, numa prop
adiada (`painel`, `Inertia::defer`). **Nenhuma query nova para encher card.**

| card do protótipo | agregado | na Page |
|---|---|---|
| KPI Colaboradores (admin) | `users` do business + `hrm_department` | número + setores |
| KPI Licenças pendentes | — (o método só lê licenças **aprovadas**) | `—` + link Licenças |
| KPI Presença de hoje (admin) | `essentials_attendances` — cedido ao Ponto (D1) | `—` + link `/ponto` |
| KPI Folha | folha bloqueada (D2, ADR própria) | fora |
| O que fazer primeiro | nenhum agregado de fila | 2 atalhos (Ponto · Licenças), sem número |
| Custo de folha por setor | D2 | fora |
| Minhas licenças | licenças aprovadas do usuário no próximo mês | lista |
| Minhas metas de venda | `essentials_user_sales_targets` do usuário | faixas gravadas; **realizado fora** (caminho de valor, como na Metas) |
| Próximos feriados | feriados do próximo mês, filtrados por localidade permitida | lista |
| Colaboradores por setor (admin) | `users` agrupado por `essentials_department_id` | lista |

## Pegadinhas

- O cliente Inertia manda `X-Requested-With` em toda visita: o teste usa os headers do
  navegador (§5 2026-09-08). Este método não tem ramo `ajax()`, então não há desvio.
- `getUserSalesTargets` (DataTables admin) **não** é tocado — é consumido pela Blade da Metas.
