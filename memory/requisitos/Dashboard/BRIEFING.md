# BRIEFING — Dashboard `/home`

> Landing page pós-login do oimpresso. Welcome banner + 4 KPI cards de operação (Total Sells / Net / Invoice Due / Total Expense) + filtro loja. **Persona Larissa [L]** (dona PME vestuário ROTA LIVRE biz=4) + qualquer user logado UPOS.

**Última atualização:** 2026-05-21 · **F6 Soft wrapper Inertia entregue** · Charts e widgets pluggable preservados via `?legacy=1`.

## Estado UI 2026-05-21 — Soft wrapper Inertia ✅

`/home` agora renderiza `resources/js/Pages/Home/Index.tsx` (AppShellV2 + 4 KPI cards + welcome). Carregamento p95 ≤ 800ms (vs ~3s da Blade legacy 1.4k LOC + assets jQuery DataTables Highcharts).

Fallback Blade preservado via `?legacy=1` — usuário que precisa de gráficos ECharts ou widgets pluggable (mecanismo Blade-only `moduleUtil->getModuleData('dashboard_widget')`) acessa pelo link discreto no rodapé.

## Telas em prod (1 canon)

| Rota | Fase | Nota | Funções |
|---|---|---|---|
| `/home` (Dashboard) | F6 Soft | 8/10 | Welcome + 4 KPI + filtro loja + link legacy |
| `/home?legacy=1` (Blade legacy) | preservado | 8/10 | Charts ECharts + widgets pluggable + AJAX endpoints |

## Funções (5/N)

Welcome banner · 4 KPI cards (Total Sells / Net / Invoice Due / Total Expense) · Filtro loja dropdown · Permission gate `dashboard.data` · Customer redirect (`user_customer` → Crm Dashboard)

## Tabelas DB

Reusa `transactions` + `business_locations` + `users` (core UltimatePOS). **Zero migration nova.**

## Métricas pós-F6 Soft (deploy 2026-05-21)

- p95 first-paint Inertia ~ 800ms (target) vs ~3s legacy
- 0 erros JS console no Inertia `/home` (validado smoke prod Chrome MCP)
- 4 errors console no `?legacy=1` (SyntaxError + CSRF) — **pré-existentes** Blade `home/index.blade.php:1641`, não causados pelo F6 Soft
- Blast radius baixo — fallback Blade ativo via `?legacy=1`
- Smoke prod 2026-05-21 validou `/home` + `/home?legacy=1` + `/sells` + `/financeiro/fluxo` — AppShellV2 + topnav intactos
- **Issue cosmético detectado** (não bloqueante): texto welcome banner com baixo contraste — classes Tailwind `primary-800/900` do gradient não renderizam escuro no React. Backlog ajuste cosmético PR separado.

## Backlog (post-F6)

- **US-DASH-002 — Charts ECharts em Inertia** — Rewrite Cockpit V2 wave (F1→F4 com protótipo Cowork)
- **US-DASH-003 — Widget registry pluggable React** — ADR nova obrigatória (mecanismo Blade-only `getModuleData('dashboard_widget')`)
- **US-DASH-004 — KPI defer com filtro datas + loja persistido** — hoje range default fixo (FY corrente)
- **US-DASH-005 — Cosmético gradient welcome banner** — classes `primary-800/900` não aplicam no React; usar fallback `from-indigo-800 to-indigo-900` ou shared `<PageHeader>` cockpit V2

## Refs

- [SPEC.md](SPEC.md) — US-DASH-001 entregue
- [RUNBOOK-home-index.md](RUNBOOK-home-index.md) — runbook MWART F6 Soft
- [Pages/Home/Index.charter.md](../../../resources/js/Pages/Home/Index.charter.md) — charter
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART
