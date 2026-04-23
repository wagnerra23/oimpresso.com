# ADR ARQ-0002 (Project) · Time tracking append-only como o Ponto

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Quando colaborador registra horas trabalhadas em projeto, esse dado alimenta faturamento de cliente. Permitir edit/delete abre porta pra fraude.

## Decisão

`project_time_logs` é append-only. Para corrigir, cria novo registro tipo `correction` apontando pro original. Igual `ponto_marcacoes` (ver PontoWr2 adr/arq/0001).

## Consequências

**Positivas:**
- Histórico auditável.
- Cliente pode ver todas entradas (incluindo correções).

**Negativas:**
- Mais registros.

## Alternativas consideradas

- **Soft delete**: rejeitado, mesmo argumento do Ponto.
