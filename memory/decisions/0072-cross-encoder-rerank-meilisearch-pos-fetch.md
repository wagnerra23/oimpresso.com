---
slug: 0072-cross-encoder-rerank-meilisearch-pos-fetch
number: 0072
title: "Cross-encoder rerank pós-fetch Meilisearch (top-50 → top-3) — desbloqueia retrieval state-of-the-art"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-05
module: Copiloto
quarter: 2026-Q2
tags: [retrieval, reranker, meilisearch, copiloto, ragas, ollama]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0036, 0047, 0067, 0068]
pii: false
review_triggers:
  - "Meilisearch lançar reranker nativo (issue oficial #4592)"
  - "RAGAS médio ≥ 0.90 sustentado por 30d sem reranker"
  - "Latência total chat > 800ms p95 e reranker for o gargalo"
---

# ADR 0072 — Cross-encoder rerank pós-fetch Meilisearch

## Contexto

Sprint 9 (ADR 0068) chegou com `qwen3-embedding:0.6b` + stopwords PT-BR, mas RAGAS médio ainda fica abaixo do alvo 0.85 quando a pergunta exige discernimento semântico fino (ex.: "diferença entre faturamento bruto e líquido em mar/2026"). O problema não é mais o **embedding** (já é estado-da-arte open multilíngue), nem o **hybrid search** (`MeilisearchDriver` já combina BM25 + vector — ADR 0036). O problema é que o top-K do Meilisearch tem ruído léxico: documentos com keyword match alto mas baixa relevância semântica para a pergunta sobem.

Pesquisa OpenClaw `memory-lancedb-pro` (mai/2026) confirma o padrão da indústria: **cast wide net (40+ candidates) → cross-encoder rerank pelo top-3**. RAGAS reportado: +12 a +18 pontos sobre top-K bare. Custo: +100-200ms latência.

`US-COPI-087` já estava no SPEC (Sprint 9c, owner Wagner, p1, 6h estimado). `COPI-23` (MEM-MEM-WIRE Phase 2) está **blocked** explicitamente esperando reranker. Esta ADR formaliza a decisão pra desbloquear.

**Alternativas avaliadas:**
1. **Esperar reranker nativo Meilisearch** — issue #4592 aberta, sem ETA, último update jan/2026. ❌ inviável pro cycle 01.
2. **Cohere Rerank API** — top-tier mas cloud-only, custo R$/req, LGPD-flag (manda payload pra servidor estrangeiro). ❌ viola ADR 0059 (self-host equivalente) e ADR 0060 (tudo rede interna).
3. **`bge-reranker-v2-m3` via TEI (HuggingFace Text Embeddings Inference)** — multilíngue, MIT, GPU opcional. ✅ self-host pleno.
4. **`dengcao/Qwen3-Reranker-0.6B` via Ollama** — community port; menor footprint, CPU OK. ✅ encaixa stack atual (Ollama já em CT 100 pra embeddings ADR 0068).
5. **MonoT5 / ColBERT** — paper-grade mas footprint maior, dependência Python complica. ❌ adiciona stack runtime.

## Decisão

Implementar **`RerankerService` em `Modules/Copiloto/Services/Retrieval/`** que:

1. Recebe `query` + `top50` candidates do `MeilisearchDriver::hybridSearch()`.
2. Chama backend de rerank via HTTP (config `COPILOTO_RERANKER_BACKEND=ollama|tei`):
   - **Default: Ollama Qwen3-Reranker-0.6B** (CT 100, mesma instância dos embeddings) — menor latência rede, CPU OK.
   - **Fallback: TEI bge-reranker-v2-m3** se Wagner liberar GPU dedicada futuramente.
3. Retorna top-3 reordenados por score cross-encoder.
4. Feature flag **`COPILOTO_RERANKER_ENABLED=false` por default** até validação RAGAS ≥ 0.85 + latência p95 < 500ms total.
5. Métrica `gen_ai.reranker.latency_ms` exportada via OpenTelemetry (ADR 0050).

**Aplicação:** `EvalRagasBaselineCommand::retrieveKbContext()` (eval) + `ContextSnapshotService::buildKbContext()` (prod chat) — mesma assinatura, ative via flag.

## Justificativa

- **Por Ollama Qwen3-Reranker e não TEI**: Ollama já está em produção CT 100 pros embeddings (ADR 0068). Adicionar reranker pelo mesmo runtime evita 2º daemon. Trade-off: Qwen3-Reranker é community port (não tem benchmark MTEB oficial); mas o tamanho 0.6B cabe sem GPU e Wagner já validou Qwen3-Embedding 0.6B (ADR 0068 / US-COPI-083).
- **Por feature flag default-off**: Wagner explicitou no `RETRIEVAL-GOTCHAS.md` que retrieval em prod chat real-time não pode estourar 800ms p95. Reranker adiciona latência conhecida; precisa medir antes de ativar globalmente. Padrão: shadow run em eval primeiro, depois canário 10% de queries, depois geral.
- **Por top-50 → top-3 e não top-100 → top-5**: papers (BGE-M3, Qwen3-Reranker) mostram saturação em ~50 candidates pra queries curtas (média Larissa = 8 tokens). Custo rerank é O(N) na quantidade de pares; 100 dobra latência sem ganho mensurável.
- **Por self-host**: Cohere Rerank cloud teria latência similar mas viola governance (ADR 0059, ADR 0060) e adiciona custo R$/request — não cabe no orçamento de tokens do Copiloto.

**Quando reabrir:** se Meilisearch lançar reranker nativo (issue #4592), se RAGAS estabilizar ≥ 0.90 sem reranker, ou se latência p95 ficar inviável.

## Consequências

**Positivas:**
- Desbloqueia `COPI-23` (Phase 2 MEM-MEM-WIRE) e operacionaliza `US-COPI-087`.
- Recall semântico melhora sem mexer em embeddings (mudança isolada no pipeline).
- Mantém self-host pleno (CT 100 only) — sem cloud, sem custo R$/req, LGPD-aware.
- Stack uniformiza em Ollama (embeddings + rerank no mesmo daemon).
- Métricas OTel deixam Wagner medir trade-off real antes de ativar prod.

**Negativas / Trade-offs:**
- +100-200ms latência prevista no caminho crítico do chat (mensurar p95 real antes de ativar).
- Adiciona modelo Qwen3-Reranker-0.6B no CT 100 (~600MB RAM); CT 100 já tem headroom mas vira segundo modelo carregado.
- Qwen3-Reranker é community port — risco baixo (modelo open) mas sem suporte oficial Alibaba.

**Riscos mitigados:**
- Flag default-off + canário 10% impede regressão em massa.
- Métricas OTel + RAGAS gate de medição (US-COPI-081) detectam degradação automática.
- Fallback TEI documentado se Ollama community port der problema.

## Implementação — referência rápida

```
Modules/Copiloto/Services/Retrieval/
  RerankerService.php          # interface + driver Ollama/TEI
  Drivers/
    OllamaRerankerDriver.php
    TeiRerankerDriver.php
    NullRerankerDriver.php     # passthrough p/ testes
```

Config em `config/copiloto.php`:
```php
'reranker' => [
    'enabled' => env('COPILOTO_RERANKER_ENABLED', false),
    'backend' => env('COPILOTO_RERANKER_BACKEND', 'ollama'),
    'model' => env('COPILOTO_RERANKER_MODEL', 'dengcao/Qwen3-Reranker-0.6B'),
    'top_in' => 50,
    'top_out' => 3,
    'timeout_ms' => 400,
],
```

Tests Pest mínimos:
- `RerankerServiceTest` — passthrough quando flag off
- `OllamaRerankerDriverTest` — happy path + timeout + reorder correto
- Integration: `EvalRagasBaselineCommandTest` com/sem reranker, asserta RAGAS ↑

## Referências

- ADR 0036 — MeilisearchDriver hybrid + OpenAI embeddings
- ADR 0047 — Hot/Cold memory split (MEM-HOT-1)
- ADR 0067 — Sprint 8 mcp_memory_document searchable retrieval
- ADR 0068 — Sprint 9 retrieval ollama reranker strategy (esta ADR é o split execucional)
- US-COPI-087 — Sprint 9c (executa esta ADR)
- COPI-23 — MEM-MEM-WIRE Phase 2 (blocked, será unblocked após US-COPI-087)
- `memory/requisitos/Copiloto/RETRIEVAL-GOTCHAS.md` — armadilhas Sprint 9
- `memory/requisitos/Copiloto/RETRIEVAL-ESTADO-ARTE-2026-05.md` — survey mai/2026
- OpenClaw `memory-lancedb-pro` — referência industry de pattern wide-net + rerank
