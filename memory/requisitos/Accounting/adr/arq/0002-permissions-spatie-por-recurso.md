# ADR ARQ-0002 (Accounting) · Permissões Spatie granulares por recurso

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Dados contábeis são sensíveis — contador vê tudo, gerente vê resumos, operador POS **não vê nada**. Sem permissões granulares, exposição acidental vira problema de compliance.

## Decisão

Usar Spatie Permission com padrão `accounting.{recurso}.{operacao}`:
- `accounting.chart_of_accounts.index/create/edit/delete`
- `accounting.journal_entries.index/create/edit/delete`
- `accounting.budget.index/create/edit/delete`
- `accounting.reconciliation.index/create`
- `accounting.reports.view`

Cada controller faz `$this->authorize('accounting.X.Y')` no início.

## Consequências

**Positivas:**
- Contador sênior tem role `accounting-admin` com tudo.
- Gestor tem `accounting-readonly` — só reports.
- Vendedor comum nunca acessa.

**Negativas:**
- 15+ permissões explodem seeder. Mitigação: agrupamento por role pré-definido.

## Alternativas consideradas

- **Role-based sem Spatie**: rejeitado, inflexível.
- **Gate puro**: rejeitado, perde UI hints.
