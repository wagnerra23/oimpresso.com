---
id: requisitos-dashboard-spec
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

> Módulo ressuscitado em 2026-05-21 pelo caminho Soft wrapper Inertia, em paridade visual com a Blade legacy `resources/views/home/index.blade.php`. Blade legado **removido em 2026-08-28** (`views/home/index.blade.php` + 8 partials + `home.js`); `?legacy=1` virou inerte.
>
> **Persona alvo:** Larissa @ ROTA LIVRE (biz=4, vestuário, monitor 1280px). Todo usuário pós-login cai aqui — blast radius alto.

## Objetivo (1 frase)

Servir como **landing page pós-login** do oimpresso — saudação, filtros globais (loja, datas), KPI cards de operação (Total Sells / Net / Invoice Due / Total Expense). Gráficos e abas de grade passaram a ser nativos da tela React em 2026-08; o atalho pro modo legacy foi removido junto com o Blade.

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
- ~~`?legacy=1` força a Blade legacy durante canário~~ — canário encerrado; Blade removido em 2026-08-28
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `session('user.business_id')` em todas queries
- Pest GUARD em `tests/Feature/Home/HomeIndexInertiaTest.php`

### US-DASH-002 — Charts ECharts em Inertia (backlog F1→F4 wave)

> **Entregue em 2026-08** (Rewrite Cockpit V2): dois gráficos nativos — vendas por dia e vendas por mês —
> em SVG puro (`Chart` do DS), sem lib de terceiro no `package.json`.

**Implementado em:** `resources/js/Components/shared/Chart.tsx` · `resources/js/Pages/Home/Index.tsx` · `app/Http/Controllers/HomeController.php` · verificado@a50da3c38f (2026-08-28)

### US-DASH-003 — Widget registry pluggable em React (backlog ADR nova)

> `$module_widgets = $this->moduleUtil->getModuleData('dashboard_widget')` era Blade-only e saiu com o Blade
> em 2026-08-28. **Medido no dia:** o ponto de extensão — método `dashboard_widget()` num
> `Modules\<X>\Http\Controllers\DataController` — tem **zero produtores nos 32 DataControllers**, então
> nada deixou de funcionar. Quando existir um produtor, o registry React exige ADR nova.

**Implementado em:** _pendente_ — backlog; ponto de extensão sem nenhum produtor (medido 2026-08-28), rewrite React exige ADR nova

## Non-Goals (anti-alucinação)

- ❌ NÃO substitui mecanismo `moduleUtil->getModuleData('dashboard_widget')` no Soft
- ❌ NÃO mexe em endpoints AJAX (`/home/get-totals`, `/home/product-stock-alert`, `/home/purchase-payment-dues`, `/home/sales-payment-dues`) — preservados
- ❌ NÃO mexe em `getCalendar` (rota `/calendar` preservada)
- ❌ NÃO mexe em customer dashboard (`Modules/Crm/Http/Controllers/DashboardController`)

## Histórico

| Data | Versão | Mudança |
|---|---|---|
| 2026-08-28 | 2.0.0 | **Blade legado removido** ([W]: "a versão blade vai ter que sumir"). Saem `index.blade.php` (1.436 ln), 8 partials órfãos, `public/js/home.js` + `dist/js/home.js`, `indexLegacy()`, `__chartOptions()`, ramo `?legacy=1` e o banner. Preservados: 4 endpoints AJAX, `/calendar`, customer redirect, os 2 modais de `views/home/`, e Highcharts/`vendor.js` (5 consumidores PHP). |
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
