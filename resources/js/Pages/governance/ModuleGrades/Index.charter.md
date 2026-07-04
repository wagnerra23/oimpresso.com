---
page: /governance/module-grades
component: resources/js/Pages/governance/ModuleGrades/Index.tsx
related_us: [US-GOV-006]
route: /governance/module-grades
status: live
owner: wagner
adrs: [0153, 0154, 0155]
runbook: memory/requisitos/Governance/RUNBOOK-module-grades.md
---

# Charter — `/governance/module-grades` (Index)

## Mission

Permitir que Wagner (e time MCP futuro) abram **uma tela** e vejam a maturidade do projeto inteiro — 34 Modules com nota 0-100 normalizada + bucket de cor + breakdown das 9 dimensões (ADR 0155 v3) — em 5 segundos.

## Goals

1. Tabela ordenada por nota descendente (default)
2. Filter chips por bucket (Excelente/Bom/Médio/Crítico/Embrião)
3. Search por nome do módulo
4. KPI agregado: média projeto + distribuição buckets
5. Click row → drill-down `/governance/module-grades/{name}`
6. Performance: initial render <2s via `Inertia::defer` (Service faz I/O filesystem 1-2s × 34 módulos)
7. **ADR 0155 v3 — Colunas D6/D7/D8/D9 compactas** (Perf/LGPD/Sec/Obs) na tabela rank, só mostram score/max + tom de cor canônica (purple/pink/indigo/cyan). Render `—` quando módulo ainda não tem dimensão v3 avaliada (compat retroativa).
8. **Gate CI anti-regressão surfaced no rodapé** (Wave 2 ADR 0155 §"Gate CI"). Wagner e time MCP (Felipe/Maiara/Eliana/Luiz) entram em PR e enxergam que o gate existe sem precisar abrir GitHub Actions. Card sky discreto explica comportamento (cai nota → merge bloqueado, label `module-grades-allowed-regression` faz override) + 4 links externos (workflow + baseline JSON + RUNBOOK + ADR 0155). NÃO interrompe hierarquia da tabela — fica abaixo do KPI/tabela como nota de rodapé visual.

## Non-Goals

- ❌ Editar pesos da rubrica nesta tela (rubrica é ADR canônica — muda via ADR 0154 v2 append-only)
- ❌ Disparar Brain B aqui (custo $$$ + risco — limite MVP é gerar tasks via /show)
- ❌ Histórico 90d nesta tela (Fase B opcional v2)

## UX targets

- 5 chips de bucket com count + cor canônica (emerald/sky/amber/orange/red)
- Tabela com 9 colunas de dimensão score/max (D1-D5 padrão + D6-D9 ADR 0155 v3 compactas com tom canônico purple/pink/indigo/cyan)
- Click row destacado em hover sky-50
- Skeleton enquanto defer carrega
- Empty state quando filtro não bate
- Filter chips por bucket continuam iguais (não filtra por dimensão — drill-down é no /show)
- Banner gate CI rodapé Index — card sky compact (mt-4, border-sky-200, bg-sky-50/50), ícone Shield (Lucide, `w-4 h-4 text-sky-700`), 4 links externos com `target="_blank" rel="noreferrer"`, não interrompe hierarquia da tabela rank

## Anti-hooks

- ❌ NÃO eager-load grades ou kpis (Service é caro)
- ❌ NÃO permitir edição inline de notas (read-only)
- ❌ NÃO armazenar localStorage filter (refresh = volta padrão)
- ❌ NÃO mostrar evidence detalhada aqui (só no /show)
- ❌ NÃO adicionar interação no banner gate CI (read-only links externos apenas — sem botão, sem dialog, sem state)
- ❌ NÃO promover banner gate CI a posição acima da tabela (é nota de rodapé, não headline)
