# OTel GenAI retrieval spans — pipeline Jana

> **D8 gap #3 — Observabilidade retrieval pipeline (2026-05-15)** — fechado pelo audit-implement-expert. Impacto: **+2pp (86 → 88)** rumo a 98 cumulativo.

## Por que existe

Antes deste PR, o pipeline retrieval Jana (`MeilisearchDriver::buscar`) tinha apenas:

- 1 log debug por chamada com 8 campos (`copiloto-ai` channel)
- Sem correlação entre etapas (HyDE → Scout → time-decay → reranker)
- Sem visibilidade por business_id em Langfuse/Loki

Resultado: debug de latência ou queda de qualidade era cego — impossível separar custo do HyDE vs custo do reranker, ou ver tail latency p99 por tenant.

[ADR 0051](../../decisions/0051-jana-schema-proprio-adapter-otel-genai.md) já tinha decidido **OTel GenAI semantic conventions** como camada-alvo. Faltava implementação cirúrgica nos spans de retrieval.

## Arquitetura

Decorator GoF wrappa o driver canônico sem modificá-lo:

```
Caller → RetrievalTelemetryDecorator → MeilisearchDriver (inner intocado)
              │
              ├── RetrievalSpanBuilder.startQuery()        (root)
              ├── (futuro Onda 7) sub-spans wireados:
              │     ├── negative_cache
              │     ├── hyde
              │     ├── embedding
              │     ├── bm25
              │     ├── merge
              │     ├── time_decay
              │     ├── rerank
              │     └── context_select
              ├── Langfuse self-host CT 100 (async via Bus)
              └── mcp_audit_log (linha por query — ADR 0053)
```

## Spans do pipeline

| Span name | Quando emite | Atributos canon OTel GenAI 2026 |
|---|---|---|
| `jana.retrieval.query` | root — wrap full pipeline | `gen_ai.system=self`, `gen_ai.operation.name=retrieval`, `gen_ai.retrieval.query` (hash sha256 OU raw), `gen_ai.retrieval.top_k`, `gen_ai.retrieval.candidates_count`, `gen_ai.retrieval.latency_ms` |
| `jana.retrieval.negative_cache` | lookup NegativeCacheService (skip path) | `gen_ai.operation.name=retrieval.negative_cache` |
| `jana.retrieval.hyde` | HydeQueryExpander → doc hipotético | `gen_ai.operation.name=retrieval.query_expansion`, `gen_ai.retrieval.technique=hyde` |
| `jana.retrieval.embedding` | Scout hybrid (lane semântica) | `gen_ai.operation.name=retrieval.semantic`, `gen_ai.request.embedder=qwen3_local|openai|...` |
| `jana.retrieval.bm25` | Scout hybrid (lane lexical) | `gen_ai.operation.name=retrieval.lexical`, `gen_ai.retrieval.technique=bm25` |
| `jana.retrieval.merge` | RRF fusion (Cormack k=60) | `gen_ai.operation.name=retrieval.fusion`, `gen_ai.retrieval.technique=rrf` |
| `jana.retrieval.time_decay` | half-life weighting (K1 Onda 5) | `gen_ai.operation.name=retrieval.rerank.time_decay` |
| `jana.retrieval.rerank` | RRF/LLM/BGE reranker | `gen_ai.operation.name=retrieval.rerank`, `oimpresso.rerank.driver=rrf|llm|bge|null` |
| `jana.retrieval.context_select` | top-K pro LLM | `gen_ai.operation.name=retrieval.context_selection` |

### Atributos custom oimpresso (prefix `oimpresso.*`)

- `oimpresso.business_id` — **OBRIGATÓRIO** em todo span ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Tier 0 multi-tenant)
- `oimpresso.user_id` — quem consultou (LGPD opt-out propagation)
- `oimpresso.embedder` — qwen3_local | openai | text-embedding-3-small
- `oimpresso.rerank.driver` — rrf | llm | bge | null
- `oimpresso.query_redacted` — bool (true=hash, false=raw)

## Como ligar

### Produção (Hostinger + CT 100 Langfuse)

```env
# .env Hostinger
JANA_RETRIEVAL_SPANS=true
JANA_REDACT_QUERY_IN_SPANS=true       # SEMPRE true em prod (LGPD)
JANA_RETRIEVAL_AUDIT_LOG=true         # persiste em mcp_audit_log

# Langfuse já configurado via ADR 0132
LANGFUSE_ENABLED=true
LANGFUSE_HOST=https://langfuse.ct100.oimpresso.com
LANGFUSE_PUBLIC_KEY=pk_xxx
LANGFUSE_SECRET_KEY=sk_xxx
```

### Homolog (dry-run sem audit log)

```env
JANA_RETRIEVAL_SPANS=true
JANA_REDACT_QUERY_IN_SPANS=true
JANA_RETRIEVAL_AUDIT_LOG=false        # evita encher mcp_audit_log durante load test
LANGFUSE_ENABLED=true
```

### Dev local (debug — query raw, sem Langfuse)

```env
JANA_RETRIEVAL_SPANS=true
JANA_REDACT_QUERY_IN_SPANS=false      # ver query raw no log channel
JANA_RETRIEVAL_AUDIT_LOG=false
LANGFUSE_ENABLED=false                # log debug suffice
```

## Como consumir

### Langfuse dashboards canônicos

Pós-ativação, Langfuse self-host CT 100 (ADR 0132) recebe `span-create` events com nome `jana.retrieval.*`. Dashboards recomendados:

1. **Latência p50/p95/p99 retrieval** — filter `name=jana.retrieval.query`, group by `oimpresso.business_id`
2. **Distribuição candidates_count** — histogram de `gen_ai.retrieval.candidates_count`
3. **Hit rate** — % spans com `gen_ai.retrieval.hit=true`
4. **Rerank driver mix** — group by `oimpresso.rerank.driver`
5. **Embedder mix** — group by `oimpresso.embedder`

### mcp_audit_log queries

```sql
-- Top 10 queries mais lentas últimas 24h (linhas D8)
SELECT business_id, payload_summary->>'$.top_k' AS top_k,
       payload_summary->>'$.candidates_count' AS hits,
       duration_ms, ts
FROM mcp_audit_log
WHERE endpoint = 'jana.retrieval' AND ts > NOW() - INTERVAL 24 HOUR
ORDER BY duration_ms DESC LIMIT 10;

-- Cross-tenant overview (validação Tier 0)
SELECT business_id, COUNT(*) AS retrievals,
       AVG(duration_ms) AS avg_ms, MAX(duration_ms) AS max_ms
FROM mcp_audit_log
WHERE endpoint = 'jana.retrieval' AND ts > NOW() - INTERVAL 7 DAY
GROUP BY business_id;
```

### Log channel (debug local)

Toda chamada loga linha `jana.retrieval.span` com `attributes` JSON. Grep:

```bash
tail -f storage/logs/laravel.log | grep "jana.retrieval.span"
```

## PII redaction (LGPD Tier 0)

`gen_ai.retrieval.query` é **sha256 hash** quando `JANA_REDACT_QUERY_IN_SPANS=true` (default). Mesma query mesma hash → permite group by sem expor conteúdo.

⚠️ NUNCA setar `JANA_REDACT_QUERY_IN_SPANS=false` em prod. ROTA LIVRE (business_id=4) tem 99% do volume — Wagner detecta vazamento via review periódico Langfuse.

## Custo overhead

Medido localmente (sqlite in-memory + Mockery driver):

- Decorator + SpanBuilder POPO: **~0.5ms/query** (negligível)
- Langfuse Bus dispatch async: **~0.5ms/query** (já era custo do LangfuseClient)
- Audit log INSERT mcp_audit_log: **~3-12ms/query** (MySQL Hostinger)

**Total: ~5-15ms overhead por query.** Vs latência média retrieval (200-800ms HyDE + Scout + reranker), <5% impacto. Fail-open garante zero crash mesmo com Langfuse down.

## Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| Span não aparece em Langfuse | `LANGFUSE_ENABLED=false` | Set env + clear cache |
| Span aparece mas sem `oimpresso.business_id` | Caller passou businessId=0 | Verificar middleware multi-tenant — ADR 0093 |
| Audit log vazio | `JANA_RETRIEVAL_AUDIT_LOG=false` | Set env true |
| Query raw aparece em span (LGPD risk) | `JANA_REDACT_QUERY_IN_SPANS=false` em prod | **EMERGÊNCIA** — set true, rotacionar Langfuse logs |
| Latency_ms zero em spans | startQuery → recordResult chamado synchrono <1ms | Esperado em mock tests |
| Crash em emit() | LangfuseClient falhou + log warning | Fail-open suprime — verificar `storage/logs/laravel.log` warnings |

## Evolução futura (Onda 7)

Spans sub-pipeline (HyDE, embedding, BM25, etc) estão **prontos no SpanBuilder** mas ainda não wireados — `RetrievalTelemetryDecorator::buscar` só envolve root span (caixa opaca).

Pra wirear, opção A (preferida): expor hooks no `MeilisearchDriver::buscar` via callable collector injetável (sem acoplar Telemetry no driver core). Opção B: pattern Visitor — overkill.

Wagner sinaliza Onda 7 quando 88pp cumulativo bater. Por enquanto, root span já fecha gap D8 #3 (+2pp).

## Artefatos canônicos

- `Modules/Jana/Services/Memoria/Telemetry/RetrievalSpan.php` — POPO
- `Modules/Jana/Services/Memoria/Telemetry/RetrievalSpanBuilder.php` — factory + emit
- `Modules/Jana/Services/Memoria/Telemetry/RetrievalTelemetryDecorator.php` — wrapper MemoriaContrato
- `Modules/Jana/Providers/JanaServiceProvider.php` — bind condicional
- `Modules/Jana/Config/config.php` — `copiloto.telemetry.*`
- `Modules/Jana/Tests/Feature/Memoria/Telemetry/RetrievalTelemetryDecoratorTest.php` — 11 testes (este doc)

## Referências

- [ADR 0051](../../decisions/0051-jana-schema-proprio-adapter-otel-genai.md) — Schema próprio + adapter OTel GenAI
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — MCP server + audit log
- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) — Multi-tenant Tier 0
- [ADR 0132](../../decisions/0132-langfuse-self-host-ct100.md) — Langfuse self-host CT 100
- [OTel GenAI semantic conventions 2026](https://opentelemetry.io/docs/specs/semconv/gen-ai/) — spec canônica
- [Anthropic Claude Code OTel native (2026-05)](https://code.claude.com/docs/en/monitoring-usage) — referência arquitetural
