---
id: dominios-patterns-readme
---

# Patterns reusáveis — Migration Factory

Patterns extraídos da migração-piloto **Delphi WR Comercial → Laravel oimpresso** (sessão 2026-05-09, Fases 0-6) que se aplicam a **qualquer migração de cliente legacy** pro oimpresso — Bling, Tiny, Sankhya, Microsiga, ERP nicho gráfico (Zênite/Mubisys/Alfa/Visua/Calcgraf/Calcme), etc.

Cada pattern tem nome canônico, contexto, problema, solução, exemplo concreto, e quando NÃO usar.

## Índice

| # | Pattern | Quando usa |
|---|---|---|
| 1 | **Strangler Fig + ACL + Brownfield AI** ([01](01-strangler-fig-acl-brownfield-ai.md)) | Sempre — receita-mãe ([ADR 0118](../../decisions/0118-segregacao-dominios-externos-clientes-legacy.md)) |
| 2 | **Bridge tables pra core UltimatePOS** ([02](02-bridge-tables-para-core.md)) | Quando entidade legacy mapeia pra `accounts`/`users`/`business`/`employees` (proibições Tier 0) |
| 3 | **UPSERT idempotente per-tenant** ([03](03-upsert-idempotente-multi-tenant.md)) | Sempre em migration de dados — re-run deve ser seguro |
| 4 | **Metadata JSON denormalized** ([04](04-metadata-json-denormalized.md)) | Quando schema legacy tem 30+ colunas e oimpresso só mapeia 10 chave |
| 5 | **Schema vivo manda, não reconstruído** ([05](05-schema-vivo-vs-reconstruido.md)) | Sempre — `SELECT *` + `.get(col, default)` é mais robusto que SELECT específico |
| 6 | **Pest test multi-tenant Tier 0** ([06](06-pest-test-multi-tenant.md)) | Antes de PR de qualquer migration que toca `business_id` |
| 7 | **Three-mode importer (dry-run/local/prod)** ([07](07-three-mode-importer.md)) | Em todo importer — isolamento de risco progressivo |

## Como adicionar pattern novo

1. Criar `<NN>-<slug>.md` na pasta
2. Atualizar a tabela acima
3. Linkar do README do sistema externo (ex: `dominios/wr-comercial/CONVENCOES.md`) onde aplicável
4. Sessão de adição vira session log em `memory/sessions/`

## Princípio editorial

Patterns aqui são **agnósticos de fonte** (Delphi/Bling/Tiny/CSV/etc). Convenções específicas de cada sistema vivem em `dominios/<sistema>/CONVENCOES.md`. Se um pattern menciona detalhe específico de um sistema, é só pra ilustração com exemplo concreto — a aplicabilidade é geral.
