# ADR 0001 · MySQL como banco principal (sem PostgreSQL)

- **Status**: accepted
- **Data**: 2026-04-22
- **Decisores**: Wagner, Claude

## Contexto

Discussão sobre adotar PostgreSQL por causa de recursos avançados (JSONB, full-text nativo, analytics). Avaliação externa (ChatGPT) sugeriu que PostgreSQL poderia ser benéfico pra stack "Evidence → IA → Graph → Action".

O sistema já roda em MySQL 8 (Laragon local + Hostinger produção) há anos, com 20+ módulos consolidados. Migrar exigiria reescrever seeds, migrations, drivers de cache/session, e impactaria deploy.

## Decisão

Manter **MySQL 8** como único banco relacional do projeto. MemCofre usa `docs_sources`, `docs_evidences`, `docs_requirements`, `docs_links` em MySQL sem exceção.

Busca semântica/similaridade, se/quando necessária, vem via **Meilisearch ou Scout+MySQL fulltext** — não via PostgreSQL.

## Consequências

**Positivas:**
- Zero disrupção no stack atual.
- Todos módulos compartilham o mesmo banco (sem joins cross-DB).
- Hostinger oferece MySQL gerenciado — Postgres exigiria upgrade de plano.

**Negativas:**
- JSONB do Postgres é superior ao JSON do MySQL pra queries complexas.
- Full-text do MySQL é inferior ao `tsvector` do Postgres — compensamos com Meilisearch opcional.

**Trade-off consciente**: performance bruta em cenários específicos vs. homogeneidade operacional e custo zero de migração.

## Alternativas consideradas

- **PostgreSQL puro**: descartado por custo de migração.
- **MySQL + PostgreSQL híbrido** (Postgres só pro MemCofre): descartado — 2 bancos = 2x operação.
- **MongoDB**: descartado — precisamos de joins/transações em Eloquent.
