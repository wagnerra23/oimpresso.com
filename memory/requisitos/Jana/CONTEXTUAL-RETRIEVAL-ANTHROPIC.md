# Contextual Retrieval Anthropic — implementação oimpresso

> **GAP D3 #1 — Auditoria memoria-senior 2026-05-15** — fechamento +5pp (de 86 → 91).
> Status: implementado 2026-05-15, feature flag DESLIGADA por default em prod.
> Wagner liga após validação homolog (1 backfill `--dry-run` + 1 batch `--limit=50` real).

## O que é

[Anthropic Contextual Retrieval](https://www.anthropic.com/news/contextual-retrieval) — técnica publicada por Anthropic em 19/set/2024 que usa um LLM barato (Haiku 4.5) pra gerar **contexto curto (50-100 tokens) descrevendo cada chunk em relação ao documento de origem**, ANTES de embedding/BM25 indexá-lo.

Resultado clínico Anthropic (validado em múltiplos corpora):

| Técnica | Failed retrievals |
|---|---|
| Baseline (raw chunks) | 100% |
| Contextual Embeddings | -49% |
| Contextual Embeddings + Contextual BM25 + Reranking | -67% |

## Por que importa pro oimpresso

Auditoria memoria-senior 2026-05-15 identificou: oimpresso usa `MeilisearchDriver` hybrid (semantic+BM25) + reranker BGE/Cohere/RRF, MAS chunks indexados são **raw** — sem contexto gerado. Anthropic mostra que adicionar contexto descritivo elimina ~49% dos failed retrievals.

Score consolidação memoria-senior: 86 → 91 (+5pp, MAIOR gap do roadmap pra meta 98).

## Pipeline canônico (4 passos)

```
                    ┌──────────────────────────────────────┐
                    │ memory/decisions/0093-multi-tenant.md │
                    │ (doc completo, 8k tokens)             │
                    └──────────────┬────────────────────────┘
                                   │
                    ┌──────────────▼─────────────────────────┐
                    │ DocumentChunker::chunk()               │
                    │ (split em ~800 tokens por heading h2/h3│
                    │  fallback parágrafo)                   │
                    └──────────────┬─────────────────────────┘
                                   │ chunks [c1, c2, ..., cN]
                                   ▼
                    ┌────────────────────────────────────────┐
                    │ ContextualizerService::contextualize() │
                    │ Anthropic Haiku 4.5                    │
                    │   - system: doc COMPLETO + cache_ctrl  │
                    │   - user:   chunk específico           │
                    │ Output: 50-100 tokens descritivos      │
                    └──────────────┬─────────────────────────┘
                                   │
                                   ▼
                    ┌────────────────────────────────────────┐
                    │ mcp_memory_documents.contextual_context│
                    │ (TEXT — concat de N contextos por doc) │
                    └──────────────┬─────────────────────────┘
                                   │
                                   ▼
                    ┌────────────────────────────────────────┐
                    │ Meilisearch index (Scout)              │
                    │ toSearchableArray() PREPENDA o contexto│
                    │ ao content_md indexado                 │
                    │ → embedder/BM25 veem contexto+raw      │
                    └────────────────────────────────────────┘
```

## Arquivos canônicos

| Arquivo | Responsabilidade |
|---|---|
| `Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php` | Wrapper Anthropic Messages API com prompt caching |
| `Modules/Jana/Services/Memoria/Contextual/DocumentChunker.php` | Quebra markdown em chunks ~800 tokens (heading-aware) |
| `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php` | Hook `aplicarContextualRetrieval()` adicionado no sync git→DB |
| `Modules/Jana/Entities/Mcp/McpMemoryDocument.php` | Fillable+casts pra novas colunas + `toSearchableArray()` prepend |
| `Modules/Jana/Database/Migrations/2026_05_15_120000_add_contextual_context_to_mcp_memory_documents.php` | 3 colunas + índice |
| `Modules/Jana/Console/Commands/ContextualizeBackfillCommand.php` | Backfill `jana:contextualize-backfill` |
| `Modules/Jana/Config/config.php` | Seção `contextual_retrieval` (linhas finais) |

## Feature flag

`JANA_CONTEXTUAL_RETRIEVAL=false` (default) — service no-op completo. Backfill rejeita execução com mensagem amigável até flag estar `true`.

Ativação canônica (Wagner aprova em homolog):

```dotenv
# .env produção (após validação)
ANTHROPIC_API_KEY=sk-ant-xxx           # Vaultwarden tem
JANA_CONTEXTUAL_RETRIEVAL=true
JANA_CHEAP_MODEL=claude-haiku-4-5-20251001
JANA_CONTEXTUAL_MAX_CHUNK_CHARS=3200   # ~800 tokens
JANA_CONTEXTUAL_CONTEXT_MAX_TOKENS=100
JANA_CONTEXTUAL_MAX_DOC_CHARS=200000   # pula docs >50k tokens (rare)
```

## Custo esperado

### One-shot backfill inicial

- ~1.500 docs em `mcp_memory_documents` (snapshot 2026-05-15)
- Média ~8k tokens por doc (range 200—50k)
- 10 chunks/doc avg

| Item | Tokens | $/1M | USD |
|---|---|---|---|
| cache_write (1ª chamada por doc) | 8000 × 1500 = 12M | $1.25 | $15.00 |
| cache_read (chunks 2-10 reusam) | 8000 × 9 × 1500 = 108M | $0.10 | $10.80 |
| output | 100 × 10 × 1500 = 1.5M | $5.00 | $7.50 |
| **Total one-shot** | | | **~$33** |

BRL equivalente (câmbio 5.50): ~R$ [redacted Tier 0] single payment.

### Steady state (incremental)

- ~5 docs modificados/dia (commits + handoffs novos)
- 5 × $0.022 = $0.11/dia → ~$3.30/mês ≈ R$ [redacted Tier 0]/mês

### Eficiência cache

Sem prompt caching, custo seria 10× maior (~$330 one-shot) porque cada chunk re-enviaria o doc inteiro. Prompt caching ephemeral (5min TTL) garante que N chunks do mesmo doc paguem apenas 1 cache write + (N-1) cache reads.

## Comando backfill

```bash
# Preview (sem custo, estima)
php artisan jana:contextualize-backfill --dry-run --detail

# Smoke real (50 docs, observar log)
php artisan jana:contextualize-backfill --limit=50 --detail

# Batch produção
php artisan jana:contextualize-backfill --limit=500

# Re-contextualizar tudo (se mudou prompt/modelo)
php artisan jana:contextualize-backfill --force --limit=1500
```

**NOTA flags:** `--detail` (não `--verbose` — Symfony reserved, ver `.claude/rules/commands.md`).

## Métricas de validação

Após Wagner ativar em prod, coletar por **2 semanas**:

| Métrica | Antes (raw chunks) | Depois (contextual) | Target |
|---|---|---|---|
| Failed retrievals @ top-5 (RAGAS dataset) | baseline | -49% expected | Anthropic claim |
| Top-1 hit rate | baseline | +30% expected | — |
| Custo cumulativo mensal | $0 | < $5/mês | budget |
| Latência indexação por doc | ~200ms | +800-1200ms (1 Haiku call) | aceitável |

Tooling: `RagasBaselineCommand` (existente — `php artisan eval:ragas-baseline`) roda contra dataset antes/depois.

## Troubleshooting

### `ANTHROPIC_API_KEY ausente — pulando`

Service degrada graciosamente: retorna `''` (string vazia) e loga warning. Sync git→DB completa normal, apenas sem coluna contextual_context populada.

**Fix:** setar no .env (Hostinger painel ou CT 100 `/opt/oimpresso/.env`).

### Custo escalando além do esperado

Causa provável: TTL cache de 5min insuficiente (chunks do mesmo doc espaçados > 5min). Solução: rodar backfill em batches contíguos (sem sleep entre docs) ou aumentar TTL pra `1h` (custo write 2×, mas read mantém 0.1×).

### Doc > 200k chars pulado

Edge case raro (sessions consolidadas anuais, audits gigantes). Service detecta via `max_doc_chars` config e marca `contextual_indexed=false` permanente. Aceitável — esses docs já eram indexados como raw.

### Pest test falhando

Sempre rodar com `CONTEXTUAL_RETRIEVAL_FORCE_MOCK=true` ou `config()->set('copiloto.contextual_retrieval.force_mock', true)`. Mock determinístico não bate em rede.

## Evolução futura (não-incluído neste gap)

1. **Contextual BM25 separado de Contextual Embeddings** — Anthropic blog menciona que tratar os 2 sinais em buckets separados ganha mais ~5pp NDCG. Requer mudar Meilisearch index settings (split em 2 fields: `bm25_text`, `embedding_text`).
2. **Re-contextualização incremental** — quando ADR muda, re-contextualizar apenas os chunks afetados (não doc inteiro). Demanda hash por chunk em DB.
3. **Modelo cheaper que Haiku 4.5** — se Anthropic lançar Haiku 5 com custo menor, swap via `JANA_CHEAP_MODEL` env (zero código).
4. **Auto-aplicar em `MemoriaFato` (chat) e não só `McpMemoryDocument`** — gap considera só base de conhecimento. Estender pra memória conversacional adiciona ~$5/mês.

## Referências

- [Anthropic blog 2024-09-19: Introducing Contextual Retrieval](https://www.anthropic.com/news/contextual-retrieval)
- [Anthropic prompt caching docs](https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching)
- [ADR 0053 — MCP server governança como produto](../../decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md)
- [ADR 0067 — Sprint 8 retrieval](../../decisions/0067-sprint8-mcp-memory-document-searchable-retrieval.md)
- [Building Production RAG with Anthropic's Contextual Retrieval (Medium 2026)](https://medium.com/@reliabledataengineering/building-production-rag-with-anthropics-contextual-retrieval-complete-python-implementation-f8a436095860)
- [RAG Production Guide 2026 (Lushbinary)](https://lushbinary.com/blog/rag-retrieval-augmented-generation-production-guide/)

---

**Última atualização:** 2026-05-15 — implementação inicial (audit-implement-expert / GAP D3 #1).
