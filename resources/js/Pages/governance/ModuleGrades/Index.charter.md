---
page: governance/ModuleGrades/Index
route: /governance/module-grades
status: live
owner: [W]
adrs: [0153]
runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md
---

# Charter — `/governance/module-grades` (Index)

## Mission

Permitir que Wagner (e time MCP futuro) abram **uma tela** e vejam a maturidade do projeto inteiro — 34 Modules com nota 0-100 + bucket de cor — em 5 segundos.

## Goals

1. Tabela ordenada por nota descendente (default)
2. Filter chips por bucket (Excelente/Bom/Médio/Crítico/Embrião)
3. Search por nome do módulo
4. KPI agregado: média projeto + distribuição buckets
5. Click row → drill-down `/governance/module-grades/{name}`
6. Performance: initial render <2s via `Inertia::defer` (Service faz I/O filesystem 1-2s × 34 módulos)

## Non-Goals

- ❌ Editar pesos da rubrica nesta tela (rubrica é ADR canônica — muda via ADR 0154 v2 append-only)
- ❌ Disparar Brain B aqui (custo $$$ + risco — limite MVP é gerar tasks via /show)
- ❌ Histórico 90d nesta tela (Fase B opcional v2)

## UX targets

- 5 chips de bucket com count + cor canônica (emerald/sky/amber/orange/red)
- Tabela com 5 colunas de dimensão (D1-D5) score/max
- Click row destacado em hover sky-50
- Skeleton enquanto defer carrega
- Empty state quando filtro não bate

## Anti-hooks

- ❌ NÃO eager-load grades ou kpis (Service é caro)
- ❌ NÃO permitir edição inline de notas (read-only)
- ❌ NÃO armazenar localStorage filter (refresh = volta padrão)
- ❌ NÃO mostrar evidence detalhada aqui (só no /show)
