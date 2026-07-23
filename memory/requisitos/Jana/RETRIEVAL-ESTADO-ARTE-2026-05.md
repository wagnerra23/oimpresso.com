---
id: requisitos-jana-retrieval-estado-arte-2026-05
---

# Estado da arte — Retrieval híbrido PT-BR (mai/2026)

> Pesquisa profunda 2026-05-04 sobre embeddings multilingual + Meilisearch hybrid + rerankers.
> Contexto: nomic-embed-text se mostrou inútil para PT-BR (cosine ~0.97 uniforme).
> Foco: roadmap concreto pra superar baseline 0.700 RAGAS.

---

## Diagnóstico do problema atual

`nomic-embed-text:137M` falha em PT-BR **por design**, não por configuração. É treinado
predominantemente em inglês — projeta textos não-EN num cluster denso e indistinguível.
Cosine ~0.97 uniforme é o sintoma clássico.

---

## 1. Embeddings multilingual (ranking PT-BR real)

### #1 — `qwen3-embedding` (Alibaba/Qwen) — **RECOMENDAÇÃO PRINCIPAL**

| Variante | Params | MTEB Multilingual | VRAM | Ollama |
|---|---|---|---|---|
| `qwen3-embedding:0.6b` | 0.6B | ~65 (est.) | ~1.5GB | Oficial ✅ |
| `qwen3-embedding:4b` | 4B | ~68 (est.) | ~3.5GB | Oficial ✅ |
| `qwen3-embedding:8b` | 8B | **70.58** (#1 MTEB Jun/2025) | ~5GB Q4 | Oficial ✅ |

- 100+ idiomas com PT-BR explícito documentado
- Matryoshka nativo (32 a 4096 dims configurável)
- Registry oficial: `ollama.com/library/qwen3-embedding`
- **Sweet spot pro nosso caso (400 docs):** `qwen3-embedding:4b` — qualidade vs VRAM

```bash
# Comando recomendado:
ollama pull qwen3-embedding:4b
```

### #2 — `multilingual-e5-large-instruct` (Microsoft)

- 560M params · **#1 MMTEB Feb/2025** (ICLR — 131 tasks, 250+ línguas)
- PT-BR testado explicitamente no MMTEB
- ❌ Não está no Ollama registry oficial → precisa Hugging Face TEI server
- HuggingFace: `intfloat/multilingual-e5-large-instruct`

### #3 — `snowflake-arctic-embed2:568m` (Snowflake)

- 568M · Top MTEB-R · Ollama oficial ✅
- ⚠️ PT-BR **não benchmarkado oficialmente** (CLEF avalia EN/FR/ES/IT/DE)
- Risco médio para PT-BR

### Descartados pra PT-BR

| Modelo | Por quê |
|---|---|
| `nomic-embed-text` (atual) | Inglês puro. R@1 < 0.16 multilingual |
| `mxbai-embed-large` | Inglês puro |
| `bge-m3` | Foco CN/EN/KO. PT fraco |

---

## 2. Meilisearch hybrid — config recomendada PT-BR

### Stopwords PT-BR (passo 1 — alto impacto, baixo esforço)

```bash
curl -X PUT "$MEILI/indexes/mcp_memory_documents/settings/stop-words" \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '["a","ao","aos","aquela","aquelas","aquele","aqueles","aquilo","as","até","com","como","da","das","de","dela","delas","dele","deles","depois","do","dos","e","ela","elas","ele","eles","em","entre","era","eram","essa","essas","esse","esses","esta","estas","este","estes","eu","foi","for","forma","havia","isso","isto","já","lhe","lhes","mais","mas","me","mesmo","meu","meus","minha","minhas","muito","na","não","nas","nem","no","nos","nós","num","numa","o","os","ou","para","pela","pelas","pelo","pelos","por","qual","quando","que","quem","se","sem","ser","seu","seus","só","sua","suas","também","te","tem","têm","teu","teus","tua","tuas","um","uma","umas","uns","você","vocês"]'
```

Ganho esperado: +5-10% no BM25 standalone.

### Localized attributes (Meilisearch v1.10+)

```bash
curl -X PUT "$MEILI/indexes/mcp_memory_documents/settings/localized-attributes" \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '[{"locales": ["por"], "attributePatterns": ["*"]}]'
```

### Embedder Ollama nativo (qwen3)

```bash
curl -X PATCH "$MEILI/indexes/mcp_memory_documents/settings/embedders" \
  -H "Authorization: Bearer $KEY" -H "Content-Type: application/json" \
  -d '{
    "qwen3_local": {
      "source": "ollama",
      "url": "http://ollama-embedder:11434",
      "model": "qwen3-embedding:4b",
      "dimensions": 1024,
      "documentTemplate": "{{doc.title}}. {{doc.content_excerpt}}"
    }
  }'
```

### semantic_ratio recomendado pra corpus técnico PT-BR

- Default: **0.6** (60% semantic, 40% BM25)
- Queries muito técnicas/siglas: testar 0.4
- BM25 é necessário pra siglas, nomes de campo, termos exatos

---

## 3. Rerankers (etapa 2 — após embedding melhor)

### Status maio/2026

Suporte parcial no Ollama via namespace community (não library oficial).

| Modelo | Namespace Ollama | Tamanho | PT-BR | Status |
|---|---|---|---|---|
| `qwen3-reranker:0.6b` | `dengcao/Qwen3-Reranker-0.6B` | 0.6B | **Forte** ✅ | Community |
| `qwen3-reranker:4b` | `dengcao/Qwen3-Reranker-4B` | 4B | **Forte** ✅ | Community |
| `bge-reranker-v2-m3` | `qllama/bge-reranker-v2-m3` | 278M | Razoável | Community ⚠️ |
| `jina-reranker-v3` | — | 600M | Excelente (100+ langs) | Hugging Face TEI |

**Caveat Ollama:** rerankers usam endpoint `/api/embed` de forma não-padrão (cross-encoder
≠ embedding). Precisa wrapper custom pra parsear o score de relevância.

**Alternativa robusta:** Hugging Face TEI tem endpoint `/rerank` padronizado:
```bash
docker run --gpus all -p 8080:80 ghcr.io/huggingface/text-embeddings-inference \
  --model-id BAAI/bge-reranker-v2-m3
```

### Latência (top-30 docs, CPU)

- `jina-reranker-v2-base-multilingual` 278M: ~100-150ms
- `bge-reranker-v2-m3` 278M: ~150ms
- `qwen3-reranker:0.6b` 600M: ~80ms (GPU)

---

## 4. Pipeline recomendado pra ~400 docs PT-BR (100% local)

```
Query
  ├─[BM25]──► Meilisearch top-50 docs
  └─[Dense]──► qwen3-embedding:4b → Meilisearch vector top-50
                                          │
                                    RRF fusion (Meilisearch nativo)
                                          │
                                    Cross-encoder reranker (Qwen3 ou bge)
                                          │
                                    top-5 a 10 passages → LLM
```

### Por que esse pipeline supera ColBERT pra 400 docs

| Abordagem | 400 docs | Infra | PT-BR |
|---|---|---|---|
| **Bi-encoder + reranker** (recomendado) | Ideal — low latency, alta precisão | Ollama + TEI | Excelente |
| ColBERT / Late Interaction | Overkill — ganho marginal | ColBERT server separado | Requer modelo PT específico |
| Naive dense only | Bom se modelo for multilingual | Ollama apenas | Depende do modelo |
| **BM25 only** (atual) | RAGAS 0.700 — baseline | Meilisearch | Aceitável |

### Expectativa de ganho

| Configuração | RAGAS estimado |
|---|---|
| BM25 only (atual MySQL FT) | 0.700 |
| BM25 + qwen3 dense (semantic_ratio=0.6) | **0.80-0.84** |
| BM25 + dense + reranker | **0.85-0.90** |

73% dos erros em RAG são retrieval, não geração — investir aqui tem o maior ROI.

---

## Plano de ação faseado

### Fase A — Trocar embedder (impacto alto, esforço baixo)

```bash
# CT 100, container Ollama:
ollama pull qwen3-embedding:4b   # ou qwen3-embedding:0.6b se VRAM apertada

# Testar diferenciação cosine antes de seguir:
# Esperado: cosine 0.3-0.8 entre docs diferentes (não mais ~0.97 uniforme)
```

Reconfigurar embedder no Meilisearch e re-importar 383 docs.

### Fase B — Stopwords PT-BR + localizedAttributes (5min)

Aplicar via API. Ganho BM25 +5-10%.

### Fase C — Subir semantic_ratio gradualmente

Re-rodar `eval:ragas-baseline --semantic-ratio=X`:
- 0.0 (baseline atual): 0.700
- 0.4 (BM25 dominante): testar
- 0.6 (semantic dominante): testar
- 0.8 (semantic forte): testar

Escolher o ratio com melhor RAGAS médio.

### Fase D — Adicionar reranker (se Fase A-C não atingir 0.85)

```bash
ollama pull dengcao/Qwen3-Reranker-0.6B
# OU: docker run TEI com bge-reranker-v2-m3
```

Implementar em `retrieveKbContext()`: fetch top-50 → reranker → top-3.

---

## Resumo executivo

| Decisão | Escolha | Racional |
|---|---|---|
| **Embedding primário** | `qwen3-embedding:4b` | #1 MTEB multilingual, 100+ langs, PT-BR explícito |
| **Alternativa VRAM** | `qwen3-embedding:0.6b` | -3pt MTEB, 1.5GB VRAM |
| **semanticRatio inicial** | 0.6 | BM25 necessário pra siglas/termos técnicos |
| **Stopwords PT-BR** | Lista canônica via API | Ganho rápido +5-10% |
| **Reranker** | `dengcao/Qwen3-Reranker-0.6B` (Ollama) ou `bge-reranker-v2-m3` (TEI) | Mesma família embedding+reranker = ganho |
| **Descartar** | `nomic-embed-text`, ColBERT | Nomic é EN-only; ColBERT overkill 400 docs |

**Ação mínima com maior impacto:** `ollama pull qwen3-embedding:4b` + re-configurar Meilisearch embedder + re-importar 383 docs. Sozinho deve eliminar o cosine ~0.97 uniforme e provavelmente já superar 0.700.

---

## Fontes

- [Best Embedding Model for RAG 2026 — Milvus](https://milvus.io/blog/choose-embedding-model-rag-2026.md)
- [Ollama Embedding Models: Benchmarks — Morph](https://www.morphllm.com/ollama-embedding-models)
- [Best Embedding Models for RAG 2026 — PremAI](https://blog.premai.io/best-embedding-models-for-rag-2026-ranked-by-mteb-score-cost-and-self-hosting/)
- [MMTEB: Massive Multilingual Text Embedding Benchmark — arXiv](https://arxiv.org/abs/2502.13595)
- [qwen3-embedding — Ollama Library](https://ollama.com/library/qwen3-embedding)
- [Qwen3 Embedding & Reranker — Glukhov](https://www.glukhov.org/post/2025/06/qwen3-embedding-qwen3-reranker-on-ollama/)
- [Hybrid and semantic search — Meilisearch Docs](https://www.meilisearch.com/docs/capabilities/hybrid_search/overview)
- [RAG with Meilisearch — Meilisearch Blog](https://www.meilisearch.com/blog/mastering-rag)
- [BAAI/bge-reranker-v2-m3 — Hugging Face](https://huggingface.co/BAAI/bge-reranker-v2-m3)
- [dengcao/Qwen3-Reranker-4B — Ollama](https://ollama.com/dengcao/Qwen3-Reranker-4B)
