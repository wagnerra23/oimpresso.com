# ADR ARQ-0002 (Essentials) · Permissões Spatie granulares por recurso

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Essentials tem recursos sensíveis (folhas de ponto, ausências, atestados, documentos de RH). Cada recurso precisa de nível de acesso diferente — admin vê tudo, colaborador só vê o próprio.

## Decisão

Usar **Spatie Permission** com granularidade por recurso + operação. Padrão:

```
essentials.{recurso}.{operacao}
```

Exemplos:
- `essentials.crud_leave_type` — gerencia tipos de ausência (só admin RH)
- `essentials.crud_all_leave` — vê ausências de todos
- `essentials.crud_own_leave` — só as próprias
- `essentials.approve_leave` — gestor aprova/rejeita
- `essentials.view_own_attendance` — vê próprio ponto
- `essentials.crud_all_attendance` — gerencia ponto de todos (admin)

Cada permissão vira uma regra `R-ESSE-00N` no SPEC.md com Gherkin do comportamento.

## Consequências

**Positivas:**
- UI renderiza botões/menus só se usuário tem permissão (evita 403 frustrante).
- Roles pré-configuradas (admin, gestor, colaborador) agrupam permissões.
- Auditoria: `$user->getAllPermissions()` mostra exatamente o que pode.

**Negativas:**
- Explosão de permissões (~15 pra Essentials). Mitigar com seeders bem feitos.
- Cache de permissões precisa ser flusheado após upgrade.

## Alternativas consideradas

- **Policies puras (sem Spatie)**: viável, mas UI fica repetida.
- **Gates fixos**: rejeitado — não escalam pra multi-empresa.
