# Review Round 1 — ProjectMgmt/Activity/Index.tsx

**Tela:** `/project-mgmt/activity` · **Stories:** US-TR-205 · **Charter:** ❌ ausente
**Reviewer:** W31 bulk · **Data:** 2026-05-17 · **Modo:** análise estática (sem render)

## Resumo

Activity feed timeline com agrupamento por dia, KPIs (24h/7d/criadas/concluídas), filtros (tipo/autor/período/task) e auto-reload 30s. Visual limpo, padrão `AppShellV2`/`PageHeader`/`KpiGrid` consistente. PT-BR ok.

## Pontos fortes

- KPIs com `KpiCard` padronizado (Tier B `design-system`)
- Agrupamento `dayLabel()` cordial ("Hoje"/"Ontem"/"N dias atrás")
- `preserveScroll` + `preserveState` nos filtros (boa UX)
- Auto-reload `only:['events','kpis']` (partial reload — pattern correto)
- Empty state explícito (`<Card>` + `ActivityIcon` opacity-40)
- Timeline com border-l `border-muted pl-4` é elegante

## Riscos / gaps (top 5)

1. **R1 — Charter ausente** ([ADR 0104](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)). Edit/Write em `.tsx` SEM `Activity/Index.charter.md` ao lado bloqueia hook `block-mwart-violation.ps1`. **Ação:** criar charter F3.
2. **R2 — `Inertia::defer` ausente?** Não confere Controller, mas `events` pode ter eager-load + KPIs com `count()` agregado. Se Controller não usa `defer`, viola RUNBOOK [inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md) (Tier 0 2026-05-15).
3. **R3 — Auto-reload 30s sem controle de aba inativa.** `setInterval` continua rodando se aba esconde → desperdício de query. Adicionar `document.hidden` guard.
4. **R4 — Filtro `task` via `onBlur`** (não debounce, não Enter). Larissa em 1280px com mouse pode esquecer de tirar foco — UX confusa. Mudar pra debounce 350ms (pattern do Backlog `qDebounceRef`).
5. **R5 — `EVENT_ICON` / `EVENT_LABEL` Record<string, any>** sem fallback tipado pra novos eventos do MCP (`task_event_types` cresce). Hoje cai em `GitCommit` + label cru.

## Veredito round 1

Tela funcional + padronizada. **Pendências:** charter (R1, bloqueador MWART), defer audit backend (R2), polling-pause aba (R3), debounce task input (R4).

**Status:** APROVA com pendências P2/P3. Sem refactor estrutural.
