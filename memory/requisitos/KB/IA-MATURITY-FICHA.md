# KB — IA-MATURITY-FICHA (Wave 22)

**Data:** 2026-05-16 · **Auditor:** Wave 22 agent · **Branch:** `claude/governance-wave-21-22-mega`
**Escopo:** maturidade IA do `Modules/KB/` (grafo conhecimento + RAG + embeddings) vs líderes 2026 — Pinecone, Weaviate, Chroma, Mem0/Mem0g, Backstage TechDocs.
**Persona:** Wagner (governança ADRs/sessions/charters) → Larissa (SOPs operacionais gráfica).
**Status base:** ADR 0150 ACEITA · ONDA 0+1+2+4+5(parcial) LIVE main (PR #934) · ONDA 3+6 em execução.

---

## 1. Capacidades P0 IA — auto-avaliação

| # | Capacidade | Peso | Estado oimpresso `Modules/KB/` | Nota 0-10 |
|---|---|---|---|---|
| C1 | Embedding pipeline (Ollama self-host CT 100) | P0 ×4 | Canon definido (ADR 0035 Camada A `laravel/ai` + Meilisearch hybrid embedder Ollama `nomic-embed-text` 768d / `bge-m3` 1024d). Bridge `mcp_memory_documents` indexa 352+ docs. `KbCorpusBuilder` agrega kb_nodes + bridge | 8,5 |
| C2 | Hybrid search (vector dense + BM25/keyword sparse + RRF) | P0 ×4 | Meilisearch nativo hybrid (BM25 + dense via embedder) — canon (proibições.md). Falta UI search com weight slider `semanticRatio` | 8,0 |
| C3 | Reranking cross-encoder (BGE/Cohere/Voyage) | P0 ×4 | **GAP** — nenhum reranker no pipeline. `KbRagService` retorna top-k Meilisearch direto pro LLM. Estado-da-arte 2026 = `BAAI/bge-reranker-v2-m3` após top-50 hybrid → top-5 final | 3,0 |
| C4 | Knowledge graph edges (entities-relationships) | P0 ×4 | **DIFERENCIAL** — `kb_edges` tipadas (next-in-path, fix-of, supersedes, charter-of, references-data, ai-related, cross-link, related-by-tag). `KbEdgeAutoDeriver` auto-detecta. Mem0g só atinge isso com FalkorDB add-on; Pinecone/Chroma nem suportam | 9,5 |
| C5 | Multi-modal embeddings (texto + imagem + PDF) | P0 ×4 | **GAP** — só texto. Sem captioning de screenshots/PDFs (gráfica precisa: catálogo Roland, prova impressa). Estado-da-arte: Docling + CLIP/BGE-VL | 2,0 |
| C6 | Versioning + TTL + drift detection (docs vs reality) | P1 ×2 | `kb_node_versions` append-only existe. Drift `procedure_drift` (Jana health-check) cobre DB. **GAP**: sem detector "artigo KB desatualizado vs commit recente" (auto-suggest re-review) | 5,5 |
| C7 | Self-update / memory ops (ADD/UPDATE/DELETE/NOOP Mem0-style) | P1 ×2 | **GAP** — sem extração de fatos automática. Mem0 mostra 26% LLM-as-Judge improvement vs raw chunks. Para Jana long-term seria útil; KB hoje é manual+bridge | 2,5 |
| C8 | Temporal reasoning (timeline-aware queries) | P1 ×2 | Bridge tem `git_sha` + `updated_at`, kb_nodes tem `updated_at`. **PARCIAL** — RAG não privilegia recência; Mem0g 58% vs OpenAI 21% justamente aqui | 4,5 |
| C9 | Mock mode + cost guardrails | P1 ×2 | Canon Tier 0: `<FEATURE>_FORCE_MOCK=true` pattern (H4 RAGAS). **GAP confirmar**: `KbRagService` tem mock mode? Custo IA tracking ADR 0094 §4? | 5,0 |
| C10 | Citações + grounding (links pra origem) | P0 ×4 | **FORTE** — bridge preserva git_sha → GitHub URL; ADRs/sessions têm anchor. UI mostra source no painel direito do tri-pane | 9,0 |
| C11 | Auto-derive edges (LLM extrai cross-link de texto) | P1 ×2 | `KbEdgeAutoDeriver` existe (ONDA 1) — base regex/heurística. Falta camada LLM "estes 2 nodes deveriam ter edge `supersedes`?" (ONDA 8 roadmap) | 5,5 |
| C12 | Eval framework (RAGAS faithfulness/relevancy) | P0 ×4 | **GAP** — Wave 21 H4 implementou RAGAS pra Jana; KB não herdou ainda. Sem mensuração de qualidade RAG = vôo cego | 2,0 |

**Cálculo ponderado** (P0=4, P1=2):
- P0 (C1, C2, C3, C4, C5, C10, C12): (8,5+8,0+3,0+9,5+2,0+9,0+2,0)×4 = 168,0
- P1 (C6, C7, C8, C9, C11): (5,5+2,5+4,5+5,0+5,5)×2 = 46,0
- Total = 214,0 / (7×4 + 5×2)×10 = 214,0 / 380 = **56,3 / 100**

## 2. Mini-comparativo (vs 5 líderes 2026)

| Sistema | Hybrid | Rerank | Graph edges | Self-host | Multi-modal | Custo/mês 1M vec | Status oimpresso |
|---|---|---|---|---|---|---|---|
| **Pinecone** serverless | Sparse-dense (54ms p99) | Cohere/Voyage add-on | NÃO (vector only) | NÃO (SaaS) | parcial (CLIP via custom) | ~US$70-300 | ❌ violaria ADR 0062 (sem SaaS crítico) |
| **Weaviate** OSS | BM25+dense (44ms p99) | módulos | SIM (object refs nativo) | SIM (Docker) | módulos img2vec/multi2vec | self-host livre | ⚠️ duplicaria stack (Meili já temos) |
| **Chroma** | full-text + vector GA | externa | NÃO | SIM (embeddable) | NÃO | self-host livre | ⚠️ degrada >10M (oimpresso terá ~1M) |
| **Mem0 / Mem0g** | via vector store backend | NÃO | SIM via FalkorDB | SIM (OSS) | NÃO | self-host livre | 🔍 inspiração pra C7 (memory ops ADD/UPD/DEL) |
| **Backstage TechDocs** | indexed search | NÃO | links manuais | SIM | NÃO | self-host livre | ❌ docs estáticos por service ≠ KB conversacional |
| **oimpresso KB (atual)** | Meilisearch hybrid (canon) | **gap** | **SIM tipadas (8 tipos)** | SIM (CT 100) | **gap** | embutido ERP | **56,3/100** |
| **oimpresso KB (pós-fix 5 gaps)** | hybrid + weight UI | BGE-reranker-v2-m3 | SIM + LLM auto-derive | SIM | Docling+CLIP | embutido ERP | **~82/100 estimado** |

## 3. Top 5 gaps priorizados (P0 / impacto×esforço)

**G1 — Reranker BGE no pipeline RAG (C3 → 3,0 → 9,0)**
Inserir `BAAI/bge-reranker-v2-m3` (self-host CT 100 via Ollama ou FastAPI sidecar) entre `KbRagService::retrieve()` top-50 e LLM final top-5. Esforço: 1-2d. Impacto: nDCG@10 +15-25% (literatura Hybrid+Rerank). **Custo:** ~50ms latência adicional, zero $ (self-host).

**G2 — RAGAS eval suite pra KB (C12 → 2,0 → 9,0)**
Port do pattern H4 (Jana RAGAS Wave 21) pra `Modules/KB/Tests/Feature/RagasKbEvalTest.php`. Metrics: faithfulness, answer_relevancy, context_precision. Mock mode `KB_RAGAS_FORCE_MOCK=true`. Esforço: 1d (replica pattern). Sem isso, otimização é cega.

**G3 — Multi-modal embeddings (C5 → 2,0 → 7,5)**
Pipeline Docling (PDF parser) + caption screenshots via `gemma3-vision` (Ollama CT 100) → texto enriquecido alimentado no embedder atual. Cobre catálogos Roland, provas impressas, manuais ComVis. Esforço: 3-4d. Impacto Larissa direto (gráfica é visual).

**G4 — Drift detector "artigo desatualizado" (C6 → 5,5 → 8,5)**
Job `KbDriftDetectionJob` (cron daily) cruza `kb_nodes.updated_at` vs `git log` dos paths referenciados (edges `references-code`) → flag `needs_review=true` + alerta curador. Inspirado em Backstage TechDocs CI. Esforço: 2d. Impacto: combate principal sintoma de KB morto (info errada > sem info).

**G5 — Memory ops Mem0-style + temporal reasoning (C7+C8 → 2,5/4,5 → 7,0/8,0)**
Service `KbMemoryOpsService` que ao indexar novo session log: (a) extrai fatos via LLM (ADD/UPDATE/DELETE/NOOP); (b) RAG privilegia `updated_at DESC` em queries com palavras temporais ("agora", "atual", "última versão"). Mem0g paper mostra 58% vs 21% em temporal. Esforço: 3d. Custo: ~R$ [redacted Tier 0] por session indexada (Gemini Flash).

---

## 4. Inviolabilidades respeitadas

- ✅ Ollama embedder CT 100 (não SaaS) preservado — todos gaps usam Ollama/sidecar self-host
- ✅ Meilisearch hybrid canônico mantido — reranker é POST-search, não substituto
- ✅ Multi-tenant Tier 0 ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)): todo novo artefato (jobs, services) escopa `business_id`
- ✅ Custo IA tracking ([ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4): G2+G5 declaram custo
- ✅ Mock mode obrigatório em tudo que chama LLM (G2, G5)
- ✅ PT-BR neste doc

## 5. Nota final & próximos passos

**Maturidade IA atual:** **56,3 / 100** — KB tem fundação canônica forte (hybrid Meili + grafo tipado + bridge git + citações), porém faltam camadas que viraram baseline 2026 (reranker, RAGAS eval, multi-modal, drift).

**Pós-execução G1-G5 (estimativa):** **~82 / 100** — líder de categoria ERP-com-KB-IA-grafo no Brasil, único entre comparados que une grafo tipado + hybrid + reranker + self-host + integração ERP nativa.

**Caminho recomendado:** abrir 5 PRs separados (G1 isolado em `KbRagService`, G2 em `Tests/Feature/`, G3 em novo `KbMultiModalIngestService`, G4 em `Jobs/`, G5 em novo `KbMemoryOpsService`) — paralelizáveis (zero overlap), todos respeitam áreas isoladas. Sequência sugerida: G2 primeiro (mede baseline) → G1 → mede ganho → G3+G4+G5 paralelos.

---

**Sources auditadas (Wave 22 WebSearch):**
- [Vector Database Comparison 2026 — Pinecone vs Weaviate vs Chroma](https://aloa.co/ai/comparisons/vector-database-comparison/pinecone-vs-weaviate-vs-chroma)
- [Hybrid Search and Re-Ranking in Production RAG — Towards Data Science](https://towardsdatascience.com/hybrid-search-and-re-ranking-in-production-rag/)
- [How to build a RAG system with Meilisearch](https://www.meilisearch.com/blog/how-to-build-rag)
- [State of AI Agent Memory 2026 — Mem0](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Mem0: Production-Ready AI Agents with Scalable Long-Term Memory (arXiv 2504.19413)](https://arxiv.org/pdf/2504.19413)
- [Graph-Based Memory Solutions for AI Context — Mem0 (Jan 2026)](https://mem0.ai/blog/graph-memory-solutions-ai-agents)
- [Optimizing RAG with Hybrid Search & Reranking — VectorHub Superlinked](https://superlinked.com/vectorhub/articles/optimizing-rag-with-hybrid-search-reranking)
