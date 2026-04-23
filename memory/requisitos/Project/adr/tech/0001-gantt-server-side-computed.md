# ADR TECH-0001 (Project) · Gantt calculado server-side

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: tech

## Contexto

Gantt mostra cronograma com dependências. Calcular data-fim propagando no browser (JS) escala mal com 500+ tasks.

## Decisão

`GanttService::compute($projectId)` no backend: topological sort, propaga start/end baseado em deps + duração. Frontend só renderiza.

## Consequências

**Positivas:**
- Client leve — só render SVG/canvas.
- Cache possível (invalida só se task mudar).

**Negativas:**
- Drag-drop na UI exige round-trip pra recalcular.

## Alternativas consideradas

- **Frontend-only**: rejeitado pra projetos grandes.
- **Lib dedicada (DHTMLX Gantt)**: pesada + licença.
