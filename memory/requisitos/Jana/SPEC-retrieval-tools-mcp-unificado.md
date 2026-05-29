---
title: "SPEC — Unificar retrieval das tools MCP no pipeline bom (gap #2)"
type: spec
status: proposed
authority: tecnico
lifecycle: ativo
module: Jana
decided_at: 2026-05-29
relevancia_meta: 75
related_adrs: [0036, 0053, 0056, 0067, 0093, 0094, 0106, 0232]
fonte: [memory/sessions/2026-05-29-arte-indexacao-priorizacao-retrieval-memoria-agentes.md, memory/decisions/proposals/jana-mcp-search-tools-pipeline-bom.md]
---

# SPEC — Unificar retrieval das tools MCP no pipeline bom

> **Origem:** estado-da-arte 2026 ([doc](../../sessions/2026-05-29-arte-indexacao-priorizacao-retrieval-memoria-agentes.md)) + proposta [jana-mcp-search-tools-pipeline-bom](../../decisions/proposals/jana-mcp-search-tools-pipeline-bom.md). Estimates recalibrados ADR 0106 (10×).

## Problema (uma frase)

O chat tem retrieval estado-da-arte (`MeilisearchDriver`: hybrid+HyDE+RRF+time-decay+Peso Real+reranker), mas as tools MCP que **o time usa todo dia** (`decisions-search`/`memoria-search`/`kb-answer`) rodam em **MySQL FULLTEXT cru** sobre `mcp_memory_documents` (que **não tem embeddings**). O `kb-answer` é o pior caso: gasta LLM sintetizando sobre um recall que pode nem ter trazido o doc certo.

## Não-objetivos (Tier 0 — o que NÃO fazer)

- ❌ **NÃO** reusar `MemoriaContrato::buscar(business, user)` do chat — ele filtra `user_id`, e a memória do MCP é **business-level** (não por usuário). Erro já cometido e revertido (#1922 fechado).
- ❌ **NÃO** alterar o filtro multi-tenant das tools. O `business_id` / `acessiveisPara($user)` que cada tool já aplica é **Tier 0 IRREVOGÁVEL** (ADR 0093) — preservar **byte-a-byte**. Vazar tenant = P0.
- ❌ **NÃO** ligar default sem validar recall@5 com golden set.
- ❌ **NÃO** ir pra GraphRAG/entity-linking agora (consenso 2026: só ao saturar hybrid; não saturamos).

## User stories (recalibradas ADR 0106)

### US-RET-001 — Embeddings no corpus do time (`mcp_memory_documents`) · P0 · ~3-5h
- Configurar embedder (`text-embedding-3-small` ou `qwen3_local`, alinhar com o índice do chat) no índice Scout de `McpMemoryDocument`.
- `toSearchableArray` + `shouldBeSearchable` revisados; backfill via `scout:import`.
- **Pré-req de tudo abaixo** — sem semântica no corpus, hybrid não tem o que recuperar. Sem bloqueio (Meilisearch já roda — ADR 0058).
- **Aceite:** `McpMemoryDocument::search()` retorna por similaridade semântica; smoke cross-tenant (biz=1 vs biz=99) não vaza.

### US-RET-002 — Retriever business-scoped reutilizável · P0 · ~2-3h
- Novo método/serviço de retrieval hybrid sobre `mcp_memory_documents` que recebe **`businessId` (sem `userId`)** + filtros de tipo/lifecycle, reaproveitando a lógica de hybrid+RRF+rerank+decay do `MeilisearchDriver` (extrair o que é genérico; NÃO copiar o filtro user).
- Multi-tenant: filtro `business_id` + `acessiveisPara` idêntico ao atual das tools.
- **Aceite:** Pest — mesma query, mesmo escopo de tenant que o FULLTEXT atual; recall ≥ baseline no golden set.

### US-RET-003 — Rotear as 3 tools pelo retriever, flag + fallback · P1 · ~2-4h
- `decisions-search` (`buscarTexto`), `memoria-search` (FULLTEXT) e `kb-answer` (`buscarFontes`) passam a usar US-RET-002.
- Flag `JANA_MCP_SEARCH_PIPELINE` (default OFF) por tool; fallback gracioso pro FULLTEXT em erro/vazio (ADR 0036/0056).
- **Aceite:** flag OFF = comportamento atual byte-a-byte; flag ON = recall melhor; cross-tenant intacto.

### US-RET-004 — Gate golden-set recall@5 antes de default-ON · P1 · ~3h
- Golden set ~20 queries reais do time (ADRs/SPECs/sessions/handoffs).
- `copiloto:eval` compara pipeline ON vs FULLTEXT; só vira default se recall não regredir.
- **Aceite:** relatório recall@5 ON ≥ baseline; Wagner aprova flip.

## Sequência

```
US-RET-001 (embeddings) ──→ US-RET-002 (retriever business-scoped) ──→ US-RET-003 (rotear tools, flag OFF)
                                                                              │
                                                              US-RET-004 (golden set) → Wagner liga default
```

## Multi-tenant (Tier 0 — checklist obrigatório por PR)

- [ ] `business_id` filtrado no retriever (sem `user_id`).
- [ ] `acessiveisPara($user)` / permissões Spatie preservadas em cada tool.
- [ ] Pest cross-tenant biz=1 vs biz=99 verde antes de merge.

## Esforço total

~10-15h IA-pair (ADR 0106). Infra R$0 (Meilisearch já roda); custo LLM marginal (embeddings backfill ~1×, eval daily).

## Métrica de sucesso

recall@5 das tools MCP ≥ recall do chat (hoje 0.84) · zero regressão cross-tenant · `kb-answer` deixa de sintetizar sobre recall FULLTEXT.
