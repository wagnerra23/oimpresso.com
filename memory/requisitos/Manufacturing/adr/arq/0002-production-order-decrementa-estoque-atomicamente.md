# ADR ARQ-0002 (Manufacturing) · Production order decrementa estoque atomicamente

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Ordem de produção consome N insumos e gera produto final. Se der parcial (insumo X ok, Y falta), decremento fica inconsistente.

## Decisão

Criação/finalização de production order sempre em `DB::transaction()`. Primeiro checa disponibilidade de todos insumos (com lock pessimista `lockForUpdate`), depois decrementa todos, depois incrementa produto final. Falha parcial = rollback completo.

## Consequências

**Positivas:**
- Zero inconsistência de estoque.
- Detectar falta de insumo antes de commit.

**Negativas:**
- Lock pode serializar em alta concorrência — mitigado por short transactions.

## Alternativas consideradas

- **Eventual consistency com jobs**: rejeitado — estoque não aceita eventual.
