# ADR ARQ-0001 (Project) · Projetos com tasks hierárquicas (parent/child)

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Projetos reais raramente são flat — "entregar casa" tem sub-tarefas "fundação", "paredes", "telhado", e cada uma tem sub-tarefas. Forçar plain list quebra pra projetos >30 tasks.

## Decisão

`project_tasks` tem `parent_task_id` self-reference. Render tree no frontend via `useMemo` recursivo. Progresso calculado bottom-up (parent % = avg dos filhos).

## Consequências

**Positivas:**
- Representa realidade de projetos complexos.
- Progresso agregado é real (não manual).

**Negativas:**
- UI tree-view mais complexa que lista.
- N+1 possível se não carregar com `->with('children')`.

## Alternativas consideradas

- **Tags em vez de hierarquia**: rejeitado, perde noção de sub-projeto.
