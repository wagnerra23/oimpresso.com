---
module: Dashboard
status: ativo
phase: F6 Soft wrapper (entrega 2026-05-21)
parent_route: /home
version: "1.0.0"
owner: wagner
last_updated: "2026-05-21"
last_validated: "2026-05-21"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0101-tests-business-id-1-nunca-cliente
  - 0104-processo-mwart-canonico-unico-caminho
---

# SPEC — Dashboard (tela inicial pós-login `/home`)

> Módulo ressuscitado em 2026-05-21 pelo caminho Soft wrapper Inertia, em paridade visual com a Blade legacy `resources/views/home/index.blade.php`. Fallback Blade preservado via `?legacy=1`.
>
> **Persona alvo:** Larissa @ ROTA LIVRE (biz=4, vestuário, monitor 1280px). Todo usuário pós-login cai aqui — blast radius alto.

## Objetivo (1 frase)

Servir como **landing page pós-login** do oimpresso — saudação, filtros globais (loja, datas), KPI cards de operação (Total Sells / Net / Invoice Due / Total Expense), e atalho pro modo legacy quando o usuário precisar de gráficos e widgets pluggable de outros módulos.

## User Stories

### US-DASH-001 — Soft wrapper Inertia `/home` (F6 entrega 2026-05-21)

**Implementado em:** `app/Http/Controllers/HomeController.php` · `resources/js/Pages/Home/Index.tsx` · `tests/Feature/Home/HomeIndexInertiaTest.php` · verificado@8af585a (2026-07-02) — drift: a rota /home hoje redireciona 302 pra /ia/dashboard (Wagner 2026-05-22, sidebar v3 ADR 0180); a tela Home/Index segue viva em /dashboard-legacy

**Como** usuário logado no oimpresso
**quero** abrir `/home` em uma página Inertia React rápida
**pra** ver as KPIs principais do meu business sem esperar 1.4k linhas de Blade renderizarem.

**Critérios:**
- `/home` retorna `Inertia::render('Home/Index', [...])` por padrão
- 4 KPI cards (Total Sells / Net / Invoice Due / Total Expense) visíveis em ≤ 800ms
- Welcome banner ("Bem-vindo, {primeiro_nome}") preservado
- Permission gate `dashboard.data` mantido — sem permission, mostra shell minimal sem KPIs
- Customer redirect preservado (`user_customer` → `Crm/DashboardController`)
- `?legacy=1` força a Blade legacy (`view('home.index')`) durante canário
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `session('user.business_id')` em todas queries
- Pest GUARD em `tests/Feature/Home/HomeIndexInertiaTest.php`

### US-DASH-002 — Charts ECharts em Inertia (backlog F1→F4 wave)

> Não no escopo F6 Soft. Hoje, charts continuam exclusivos do legacy (`?legacy=1`). Backlog Rewrite Cockpit V2.

**Implementado em:** _pendente_ — backlog Rewrite Cockpit V2; charts ECharts seguem exclusivos do Blade legacy via ?legacy=1

### US-DASH-003 — Widget registry pluggable em React (backlog ADR nova)

> `$module_widgets = $this->moduleUtil->getModuleData('dashboard_widget')` é Blade-only. Soft preserva mecanismo via `?legacy=1`. Rewrite exigirá ADR nova pro registry React.

**Implementado em:** _pendente_ — backlog; registry de widgets é Blade-only (preservado via ?legacy=1), rewrite React exige ADR nova

## Non-Goals (anti-alucinação)

- ❌ NÃO substitui charts ECharts no Soft (preservados em `?legacy=1`)
- ❌ NÃO substitui mecanismo `moduleUtil->getModuleData('dashboard_widget')` no Soft
- ❌ NÃO mexe em endpoints AJAX (`/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues`) — preservados
- ❌ NÃO mexe em `getCalendar` (rota `/calendar` preservada)
- ❌ NÃO mexe em customer dashboard (`Modules/Crm/Http/Controllers/DashboardController`)

## Histórico

| Data | Versão | Mudança |
|---|---|---|
| 2026-05-21 | 1.0.0 | Ressuscitar módulo + US-DASH-001 Soft wrapper Inertia entregue (PR #1297). Charts e widgets pluggable preservados via `?legacy=1`. |
| 2026-04-22 | — | Stub `ausente_branch_atual` criado pelo `module:requirements`. Decisão de ressuscitar/deprecar pendente. |

## Referências

- [RUNBOOK-home-index.md](RUNBOOK-home-index.md) — runbook MWART F6 Soft
- [BRIEFING.md](BRIEFING.md) — 1-pager executivo
- [Pages/Home/Index.charter.md](../../../resources/js/Pages/Home/Index.charter.md) — contrato vivo da página
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — Processo MWART canônico
- PR [#1288 Caixa Soft wrapper](https://github.com/wagnerra23/oimpresso.com/pull/1288) — pattern precedente
- PR [#1297 Dashboard Soft wrapper](https://github.com/wagnerra23/oimpresso.com/pull/1297) — entrega US-DASH-001
