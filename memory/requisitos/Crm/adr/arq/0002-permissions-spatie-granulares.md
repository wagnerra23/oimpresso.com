# ADR ARQ-0002 (Crm) · Permissões Spatie granulares

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

CRM tem dados sensíveis (leads priorizados, histórico de contato, previsão de receita). Vendedor A não deve ver leads do vendedor B se política da empresa for "cada um cuida do seu".

## Decisão

Usar Spatie Permission com `crm.{recurso}.{operacao}`:
- `crm.leads.view_own/view_all/create/edit/delete`
- `crm.follow_ups.manage`
- `crm.reports.view`
- `crm.pipeline.manage`

Owner do lead é `user_id` do criador. `view_own` filtra por isso; `view_all` libera.

## Consequências

**Positivas:**
- Configurável: empresa com política aberta (todos veem tudo) vs fechada (cada um o seu).
- Relatórios gerenciais respeitam hierarquia.

**Negativas:**
- Query com scope dinâmico depende de `auth()->user()->can()` — cuidado com N+1.

## Alternativas consideradas

- **Model Observer com scope global**: viável, mais rígido.
