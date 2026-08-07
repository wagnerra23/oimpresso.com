---
page: /governance/dashboard
component: resources/js/Pages/governance/Dashboard.tsx
related_runbook: memory/requisitos/Governance/RUNBOOK-dashboard.md
owner: wagner
status: live
last_validated: "2026-05-09"
parent_module: Governance
related_adrs: [366, 275, 110, 79, 86, 94, 114]
tier: A
charter_version: 3
---

# Page Charter — `/governance/dashboard`

> **Status:** live. Página de referência viva do **Cockpit Pattern V2** com `<PageHeader>` + `<KpiCard>` shared (gold-standard de reuso de componentes).
> **v2 (2026-05-09):** estendida com seção "Saúde do ecossistema" — fechou a maior parte do escopo da US-COPI-098 do epic Cockpit Saúde sem precisar de tela nova.
> **v3 (2026-08-05):** absorveu a tela **Governança MCP** (`Jana/Admin/Governanca/Index`, `/ia/admin/governanca`) — [ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B/§D-C item 1, a **sobreposição #4** da matriz. Ganhou também a strip `<GovernancaSubNav>` e o `related_runbook` declarado.
>
> ⚠️ `last_validated` continua **2026-05-09** de propósito: é a data da última validação em tela, e a v3 ainda **não passou por smoke real**. Bumpar sem rodar seria carimbo.

---

## Mission

Painel único onde [W] opera governança em ~5min/dia: checks da Constituição, scorecard SDD, saúde do ecossistema, o que aguarda decisão nas últimas 24h — e, desde a v3, o **consumo cross-team do MCP server**. A pergunta que a tela responde é a do módulo: *"a regra está sendo cumprida?"* ([ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-A).

**Rota real:** `/governance/dashboard` (nome `governance.admin.dashboard.legacy`). A raiz `/governance` é `redirect('/ia', 302)` desde 2026-05-22 — o `page:` da v2 dizia `/governance` e estava desatualizado.

---

## Goals — Features (faz)

- AppShellV2 + topnav
- `<GovernancaSubNav active="dashboard" />` como primeiro filho — strip do módulo, lida de `shell.menu`
- `<PageHeader>` shared canônico (h1 + subtitle + ações)
- KpiGrid `Constituição` (cols=6) + `SDD` (cols=3, deferred) + `Saúde do ecossistema` (cols=3)
- `<KpiCard>` shared (NÃO inline custom) com tones semânticos (default/success/warning/danger/info)
- Audit highlights list + ADRs pendentes + narrativas Brain A 24h
- **Seção Governança MCP** (v3), visível só com `jana.mcp.usage.all`:
  - 4 KPIs: chamadas, taxa de sucesso, latência p95 (com p50/p99/máx), custo
  - filtro de período por query string `mcp_preset` / `mcp_de` / `mcp_ate` (whitelist server-side)
  - 3 abas in-page via `<SubNav variant="segmented">`: **Consumo** (série diária calls⇄custo em SVG),
    **Acesso / RBAC** (distribuição por resultado + negadas por código), **Usuários e tools** (top 10 cada)
  - partial reload `only: ['mcp','mcp_filters']` — trocar período não re-roda Constituição nem SDD
- Multi-tenant: as `mcp_*` são **cross-tenant por design** (exceção formal ao Tier 0, Constituição Art. 6+8, coberta por `CrossTenantPolicyTest`)
- Degradação graciosa: `failed_jobs` / `jana_mensagens` / `jana_health_narratives` / `mcp_sdd_scorecard_history` / `mcp_audit_log` ausentes → KPI "—" ou `<EmptyState>`, nunca erro

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição/configuração de checks (vão pra `governance/Policies`, `governance/Audit`)
- ❌ Trigger manual de checks (canon = `php artisan jana:health-check`, schedule daily 06:00 BRT)
- ❌ Histórico longo (mostra `audit_highlights.slice(0, 10)` — visão de topo)
- ❌ Alerta push (notifica via `storage/logs/laravel.log` ALERT — canon ADR 0094)
- ❌ **Mover o `GovernancaService`/as `Mcp*` pra `Modules/Governance`** — a [ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-C item 4 **não está autorizada**, e o destino declarado delas é o **Forja**. Aqui mudou só o consumidor.
- ❌ **Renomear `jana.mcp.usage.all`** — rename de permission é ADR + migration próprios, não efeito colateral de fusão de tela
- ❌ Atalho `/` focando o seletor de período (existia na tela de origem; num painel de 5 seções sequestraria a tecla para uma delas)
- ❌ `localStorage` do preset (a tela de origem só **escrevia**, nunca lia — era código morto; a fonte de verdade agora é a query string)
- ❌ Drill-down por chamada individual do MCP (isso é `governance/Audit`)

---

## UX Targets

- p95 first-paint < 800ms **das props eager** — `sdd` e `mcp` chegam deferred, sem segurar a pintura
- 0 erros JS console
- Cores semânticas Cockpit V2 via **token** (`bg-success` / `bg-warning` / `bg-destructive` / `stroke-primary`), nunca palette cru
- Atualização: refresh manual; trocar período recarrega só a seção MCP

---

## UX Anti-patterns

- ❌ Cor crua `bg-red-100`/`bg-emerald-500`/`stroke-amber-400` — canon = token semântico (`ds/no-raw-palette-color` conta como regressão no `lint:baseline:check`)
- ❌ KPIs inline com `<Card>` custom (canon = `<KpiCard>` shared)
- ❌ `href` no `<KpiCard>` — a prop não existe; os 2 usos atuais estão no baseline do tsc e não podem ganhar irmãos
- ❌ h1 inline (canon = `<PageHeader>` shared)
- ❌ `role="tablist"` hand-rolado (`ds/no-inline-tablist`) — switch in-page controlado = `<SubNav>`
- ❌ `<div className="flex …">` solto (`layout:check` / ADR 0253) — compor `<Inline>`/`<Grid>`
- ❌ `sessionStorage`

---

## Automation Anti-hooks

- ⛔ **Consumir a prop `mcp` fora do `<Deferred>`** — é o incidente literal de 2026-05-25 na tela de origem: o `Governanca/Index.tsx` desestruturava as 7 props direto e deu `TypeError undefined.find` **em prod**; o fix da época foi *remover* o defer. Aqui o defer volta **com** wrapper + null-guard. Quem desestruturar direto reabre o bug.
- ⛔ **Somar `governance.dashboard.view` ao gate da seção MCP** — a rota é gateada só por `auth`; a permission hoje decide apenas se a entry aparece na sidebar. Exigir as duas seria **mais restritivo que o estado anterior** e poderia esconder a seção de quem a enxergava ontem. O gate herdado é `jana.mcp.usage.all`, idêntico ao da tela de origem. Gatear a **rota** é decisão [W] à parte (mexe em `routes.php`).
- ⛔ **Duplicar a lista de ghosts na tela** — a fonte é `DataController::modifyAdminMenu`; tela nova entra lá e aparece aqui de graça.
- ⛔ **Inventar `business_id` nas `mcp_*`** — elas não têm a coluna, por design.

---

## Tests anti-regressão

- [tests/Feature/Design/CockpitPatternConformanceTest.php](../../../../tests/Feature/Design/CockpitPatternConformanceTest.php) — sistêmico (esta Page no canon target)
- [tests/Feature/Design/CockpitTypographyConformanceTest.php](../../../../tests/Feature/Design/CockpitTypographyConformanceTest.php) — tipografia

> **Lacuna honesta:** não há teste cobrindo o gate da seção MCP (com/sem `jana.mcp.usage.all`) nem o whitelist de `mcp_preset`. Prometer aqui um GUARD que não existe seria motivo de revogação do charter — fica registrado como pendência, não como cobertura.

---

## Refs

- [RUNBOOK-dashboard.md](../../../../memory/requisitos/Governance/RUNBOOK-dashboard.md) — receita + smoke desta tela
- [ADR 0366](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira dos 4 módulos; §D-B manda esta fusão
- [ADR 0275](../../../../memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) — card SDD (GT-G7)
- [ADR 0110](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit Pattern V2
- [ADR 0094](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição V2 (os checks)
- [ADR 0053](../../../../memory/decisions/0053-mcp-server-governanca-como-produto.md) — `mcp_audit_log` append-only + RBAC do MCP
- [ADR 0253](../../../../memory/decisions/0253-primitivos-layout.md) — primitivos de layout
- [DESIGN.md §16 Cockpit V2](../../../../DESIGN.md)
