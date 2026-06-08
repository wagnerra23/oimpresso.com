# ADR ARQ-0003 (EvolutionAgent) · Memória vetorial no MySQL atual

- **Status**: accepted
- **Data**: 2026-04-26
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Vizra ADK precisa persistir:
- Mensagens de sessão (`vizra_messages`)
- Traces de execução (`vizra_traces`)
- Memory chunks com embeddings (`vizra_memory_chunks`)
- Resultados de eval (`vizra_evaluations`)

Wagner: "pode ser db no mesmo banco."

Opções avaliadas:
- MySQL atual (`oimpresso`) — mesmo onde rodam UltimatePOS, Modules
- DB MySQL separado (`oimpresso_evolution`)
- SQLite + sqlite-vss (vector extension)
- Postgres + pgvector
- Vector store dedicado (Qdrant, Pinecone, Weaviate)

## Decisão

**Tabelas `vizra_*` no MySQL atual** do `oimpresso`.

Embeddings: armazenados como **BLOB** (vetor binário) ou **JSON** (array float). Busca via:
- Fase 1: cosine similarity em PHP (top-K em RAM, OK até ~10k chunks).
- Fase 2 (se gargalo): MySQL 8.0+ tem `VECTOR` type nativo (5.7 não tem) — migrar.
- Fase 3 (se gargalo): considerar sqlite-vss em sidecar local.

Backup: já incluído no backup MySQL atual (`mysqldump` que Wagner roda).

## Consequências

**Positivas:**
- Zero infra nova; zero custo.
- Backup unificado.
- Transação ACID disponível (insert chunk + update metadata atomicamente).
- Acesso via Eloquent já configurado (Vizra usa modelos).

**Negativas:**
- Cosine similarity em PHP é lento pra >10k chunks. `memory/` hoje tem ~50 arquivos × ~10 chunks = 500 chunks → tranquilo. Reavaliar em 6 meses.
- Backup do banco fica maior (embeddings ~3KB cada × 500 = ~1.5MB extra; trivial).
- Não usa pgvector (mais rápido), mas Wagner não usa Postgres.

**ROI estimado**: ~3× (zero setup vs configurar pgvector ou Qdrant).

## Limites a monitorar

| Métrica | Hoje | Limite pra mover |
|---|---|---|
| Total de chunks | ~500 | >10.000 |
| Latência média de query | <200ms | >1s |
| Tamanho da tabela | <5MB | >500MB |

Quando ultrapassar, abrir ADR de migração pra MySQL VECTOR (8.0+) ou sidecar sqlite-vss.

## Alternativas consideradas

| Alt | Motivo de rejeição |
|---|---|
| DB MySQL separado | Sem ganho real; só dor de manutenção (2 backups, 2 migrations). |
| SQLite + sqlite-vss | Dois bancos = dor. Vale só se vier a ser >10k chunks. |
| Postgres + pgvector | Wagner não usa Postgres em prod. Migração custa caro. |
| Qdrant/Pinecone managed | Custo mensal + dependência externa pra dado interno. Overkill. |

## Links

- [SPEC §6 Memória 3 tiers](../../SPEC.md#6-memória--estratégia-3-tiers)
- [ADR ARQ-0001 Vizra ADK como base](0001-vizra-adk-como-base.md)
