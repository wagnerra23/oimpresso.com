# Estado-da-arte — Bucket ai_central (Jana + KB) vs LLM Platforms 2026

**Data:** 2026-05-17 · **Agent:** `estado-da-arte` · **Wave:** W27
**Escopo:** Jana (nota interna 96/auto) + KB (nota interna 91/auto, IA-maturity 56,3) vs 5 plataformas LLM/observability (Vellum, LangSmith, Helicone, Braintrust, Mem0) + 4 vector DBs (Pinecone, Weaviate, Chroma, Mem0g) + 5 capacidades 2026 mainstream faltantes.

> Observação metodológica: as notas internas 96/91 são auto-medidas (Capterra-style ponderado interno) e não refletem benchmark cross-vendor independente. A IA-MATURITY-FICHA do KB (Wave 22) já pontua 56,3/100 sob critério mais duro vs Pinecone/Weaviate. Esta análise calibra os dois pra ground-truth de mercado 2026.

---

## Fase 1 — Estado-da-arte (5 plataformas + 4 vector DBs, sem contaminação oimpresso)

### LLM observability + evals + memory

| Plataforma | Mecanismo concreto | Por que é referência 2026 |
|---|---|---|
| **Vellum** | End-to-end orchestration + eval framework + production monitoring numa UI única. LLM-as-Judge custom em Python/TS. Retrieval (RAG) ingest+index+eval no mesmo workflow builder. SOC2/HIPAA cross-plan. | Plataforma "monorepo" pra times que não querem montar stack. Concorrente direto de Galileo. AWS Marketplace + BYOC enterprise. |
| **LangSmith** | Tree-of-runs trace estruturado (root + child por inner call). Insights Agent + Multi-turn Evals com "threads" como first-class. **LangSmith Engine (2026)**: AI layer que analisa traces e sugere fixes pra runs falhando/caras. Online evals em produção real-time. | Padrão de facto pra agentic AI 2026. Funciona com qualquer SDK (OpenAI, Anthropic, Vercel AI, LlamaIndex) — não trava em LangChain. |
| **Helicone** | **Rust-powered AI Gateway** (proxy reverse) entre app e todo provider LLM. P95 <5ms, 64MB RAM, 3k req/s em hardware modesto. Versioning de prompts + deploy via gateway sem code change. SOC2/GDPR. OSS. | Foco em **infra-perf** de observability (não eval). Trade-off: roteamento + cost tracking >> eval depth. Boa pra times que já têm eval externo. |
| **Braintrust** | Datasets + scorers (code/LLM/humano) + CI/CD nativo via GitHub Action que bloqueia merge se quality threshold cai. `autoevals` lib pré-built (factuality, relevance, safety). Tracing + production monitoring junto. | "Eval-first" — workflow gira em torno de scorecards reprodutíveis e CI gates. Diferente de Vellum (orchestration-first) e Helicone (proxy-first). |
| **Mem0 / Mem0g** | Memória long-term scalable: extração single-pass ADD-only, multi-signal retrieval (semântico + BM25 + entity), temporal reasoning time-aware. **Mem0g** adiciona graph-based memory (entities + relation triplets via LLM, conflict detection no update). **+26% LLM-as-Judge vs OpenAI raw**; Mem0g **+58% temporal vs OpenAI 21%**. | Único na lista focado em memória persistente de agentes, não observability. Paper arXiv 2504.19413 (validado). Mem0g = vector + graph nativo. |

### Vector DBs + memória estruturada

| Sistema | Hybrid | Rerank | Graph edges | Self-host | Latência (100M vec) | Custo/mês 1M vec |
|---|---|---|---|---|---|---|
| **Pinecone** | Sparse-dense (54ms p99) | Cohere/Voyage add-on | NÃO (vector only) | NÃO (SaaS; BYOC enterprise 2026) | Mantém recall sem tuning | ~US$70-300 |
| **Weaviate 1.35** | BM25+dense (44ms p99) — **melhor hybrid**, Object TTL 2026 | módulos | SIM (object refs nativo) | SIM (Docker/Cloud) | Mantém recall sem tuning | self-host livre |
| **Chroma 1.4.1 GA** | full-text + vector GA Cloud 2026 | externa | NÃO | SIM (embeddable) | Degrada >10M | self-host livre |
| **Mem0g** | via vector store backend | NÃO nativo | SIM via FalkorDB | SIM (OSS) | n/a | self-host livre |

### Práticas mainstream consolidadas 2026

- **HybridRAG** (vector + graph) é arquitetura padrão. Microsoft LazyGraphRAG cuts indexing cost a 0,1% do GraphRAG mantendo qualidade local-query. Hybrid GraphRAG **+8% factual correctness**, +7% context relevance vs RAG puro.
- **RAGAS golden dataset 50-200 pares** é o sweet spot (200-500 sintéticos). Targets prod: context_precision >0,8, faithfulness >0,8, answer_relevancy >0,75. Workflow padrão: RAGAS pra explorar/gerar → DeepEval pra CI → Langfuse/Patronus pra prod.
- **Drift detection 2 camadas**: estatística pra inputs (Evidently/Alibi Detect) + semântica pra outputs (reference-grade LLM judge avaliando relevance/accuracy/safety/alignment). Cosine similarity baseline com drop >2σ = embedding drift.
- **Reranker cross-encoder** (`BAAI/bge-reranker-v2-m3`, Cohere Rerank, Voyage) virou baseline. Top-50 hybrid → top-5 rerank → LLM. +15-25% nDCG@10.
- **Voice-native LLMs**: Mistral Voxtral (24B/3B, **+50% sobre Whisper-v3 multilingual**), Ultravox (fixie-ai), LiveKit Agents framework. Cascaded ASR→ReAct→TTS é mainstream; voice-native end-to-end começou em 2025.

---

## Fase 2 — Compara com o que Jana + KB têm

### Tabela comparativa por dimensão

| Dimensão | Estado-da-arte 2026 | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Tracing + observability** | LangSmith tree-of-runs + Insights Agent · Helicone proxy 5ms p95 · Langfuse | `LangfuseClient` 474 LOC integrado + `RetrievalSpan` OTel GenAI próprio. Sem AI Gateway proxy próprio (chama provider direto via laravel/ai SDK). | **Curta** — tracing equivalente, gateway não-prioridade |
| **Eval framework (RAGAS)** | RAGAS 50-200 golden + CI gate · Braintrust GitHub Action bloqueia PR | `RagasJudgeService` 285 LOC existe pra Jana. KB **não herdou ainda** (gap C12=2/10 IA-MATURITY). Sem golden set 200 (gap #5 BRIEFING Jana) | **Média** — código existe, golden set + CI gate faltam |
| **Hybrid search** | Weaviate BM25+dense 44ms · Pinecone sparse-dense 54ms | Meilisearch hybrid nativo (canon) com `nomic-embed-text` 768d / `bge-m3` 1024d via Ollama CT 100. **Sem weight slider UI** `semanticRatio`. | **Curta** — paridade backend, UI faltando |
| **Reranker cross-encoder** | BGE-reranker-v2-m3 baseline 2026 | `BgeReranker.php` 192 LOC + `RrfReranker.php` 112 LOC + `LlmRerankerAdapter` na Jana. **KB não usa ainda** (gap C3=3/10) | **Média Jana / Longa KB** |
| **HyDE query expansion** | Padrão pra queries curtas/ambíguas | `HydeQueryExpander.php` 119 LOC ativo na Jana | **Paridade** |
| **Memória long-term (Mem0-style)** | Mem0 ADD-only single-pass +26%, Mem0g +58% temporal | `MemoriaContrato` + `MeilisearchDriver` + `ProfileDistiller` + `ExtrairFatosAgent` + `NegativeCacheService` + 3 ângulos faturamento canônico ([ADR 0052](../decisions/0052-memoria-jana-3-angulos-faturamento.md)). **Sem extração ADD/UPDATE/DELETE/NOOP estilo Mem0** (gap C7=2,5/10 KB) | **Média** — fundação forte, ops Mem0-style faltam |
| **Knowledge graph edges** | Mem0g + FalkorDB add-on · Weaviate object refs | `kb_edges` tipadas 8 tipos (next-in-path, fix-of, supersedes, charter-of, references-data, ai-related, cross-link, related-by-tag) + `KbEdgeAutoDeriver`. **Único do lote com graph tipado nativo no schema** | **Supera mercado** (C4=9,5/10) |
| **Multi-modal (img+PDF+voz)** | Voxtral/Ultravox voice-native · CLIP/BGE-VL · Docling PDF | **GAP total** — só texto. Sem Whisper integration, sem captioning, sem PDF parse (C5=2/10 KB) | **Longa** |
| **Drift detection** | Statistical + semantic LLM judge · Evidently · cosine baseline | `procedure_drift` SQL check daily (5 checks Jana health-check), mas só schema DB. **Sem drift semântico em RAG outputs** nem "artigo desatualizado vs commit" (C6=5,5/10) | **Média** |
| **Citações + grounding** | LangSmith trace links · Vellum source attribution | Bridge `mcp_memory_documents` preserva `git_sha` → GitHub URL; UI tri-pane mostra source. (C10=9/10 KB) | **Paridade** |
| **PII redaction** | Vault/Logsentinel Databricks · Presidio · genérico mundial | `PiiRedactor.php` **125 LOC BR-specific** — CPF/CNPJ regex com placeholder `[REDACTED:CPF/CNPJ/EMAIL]`. Check daily `pii_leak_in_assistant_responses`. | **Supera mercado pt-BR** — concorrentes genéricos não cobrem CPF/CNPJ format |
| **Multi-tenant isolation** | n/a (concorrentes single-tenant ou row-level genérico) | `business_id` global scope Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)), Pest cross-tenant biz=1 vs biz=99 obrigatório, check daily | **Supera mercado** — vetor único defensável |
| **Semantic cache** | LangSmith caching · Vellum cost optimization | `SemanticCacheService` 255 LOC + `NegativeCacheService` (não-respostas) — diferencial menos comum | **Paridade ou supera** |
| **MCP exposure** | Anthropic MCP spec | `mcp.oimpresso.com` CT 100, 352+ docs sincronizados, FULLTEXT + Meilisearch hybrid, tabela `mcp_audit_log` triggers imutabilidade ([ADR 0053](../decisions/0053-mcp-server-governanca-como-produto.md)) | **Supera** — único ERP BR com MCP server canônico |
| **CI gate eval bloqueia PR** | Braintrust GitHub Action default | RAGAS Jana existe, mas **não bloqueia PR**. Workflow `governance-gate.yml` cobre ADR append-only, não eval RAG | **Média** |
| **Voice (Whisper/Voxtral)** | Baseline 2026 (Voxtral +50% Whisper) | **GAP total** — Larissa não tem entrada por voz | **Longa** |

### Onde Jana + KB SUPERAM o mercado (defendível)

1. **PiiRedactor BR-specific** — CPF/CNPJ format regex + placeholder tipado. Vault genérico/Presidio não cobre. Diferencial BR.
2. **Multi-tenant Tier 0** — `business_id` global scope + cross-tenant Pest obrigatório. Concorrentes single-tenant ou row-level genérico sem auditoria. Vetor único defensável.
3. **MCP server canônico CT 100** — `mcp.oimpresso.com` exposto com 352+ docs sincronizados via webhook GitHub. Único ERP BR com MCP server como produto.
4. **3 ângulos faturamento canônico** ([ADR 0052](../decisions/0052-memoria-jana-3-angulos-faturamento.md)) — contrato fixo Jana responde qualquer pergunta financeira sem alucinar. Concorrentes consultam doc estático.
5. **KB graph edges tipadas** (8 tipos) — `kb_edges` com semântica `supersedes`/`charter-of`/`references-data` etc. Mem0g só atinge com FalkorDB add-on; Pinecone/Chroma não suportam.
6. **Governança formal** — Constituição v2, 8 princípios duros, ADRs append-only com CI `governance-gate.yml`. Nenhum concorrente tem isso formalizado.
7. **ERP-nativo** — Jana lê transactions/contacts/products reais multi-tenant via `ContextSnapshotService` vs concorrentes consultando docs estáticos.

### Onde Jana + KB EMPATAM com o mercado

- Hybrid search (Meilisearch BM25+dense paridade Weaviate/Pinecone)
- Tracing OTel GenAI + Langfuse integrado
- HyDE expander + RrfReranker + BGE reranker (na Jana — KB ainda não)
- Semantic cache + negative cache
- Citações com `git_sha` → GitHub URL

### Onde Jana + KB ESTÃO ATRÁS (gap real)

- **RAGAS golden set + CI gate bloqueador** — código existe na Jana, falta dataset 200 + GitHub Action enforce
- **Reranker em KB** (gap KB C3=3/10) — Jana já tem, KB usa top-k Meilisearch direto pro LLM
- **Multi-modal** (gap C5=2/10) — só texto. Sem captioning de screenshots, sem PDF parse, sem voz
- **Memory ops Mem0-style** (gap C7=2,5/10) — sem extração ADD/UPDATE/DELETE/NOOP automática de fatos
- **Drift semântico em outputs RAG** (gap C6=5,5/10) — schema drift existe, semantic drift não
- **AI Gateway (Helicone-style)** — provider direto via laravel/ai SDK. Trade-off: simplicidade vs roteamento/fallback automático

---

## Fase 3 — Gaps rankeados (impacto × esforço IA-pair)

> Esforço calibrado por [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md): fator 10x sobre humano + margem 2x.

| # | Gap | Impacto | Esforço IA-pair | Pré-req? |
|---|---|---|---|---|
| **G1** | **RAGAS golden set 200 + CI gate bloqueador PR** | **Alto** — sem isso, otimização RAG é cega; impede regressão silenciosa | 1d (golden set curado + GitHub Action) | Nenhum — `RagasJudgeService` existe |
| **G2** | **BGE reranker no `KbRagService`** (port pattern Jana → KB) | **Alto** — nDCG@10 +15-25%; KB ainda usa top-k Meilisearch direto | 4-6h (port direto, BgeReranker.php existe) | G1 ideal (medir baseline antes) |
| **G3** | **Drift sentinel semântico em produção** (LLM-as-Judge online evals + cosine baseline) | **Alto** — detecta degradação prod antes do cliente; estado-da-arte 2026 mainstream | 1-2d | G1 (golden set referência) |
| **G4** | **Mem0-style memory ops** (`ExtrairFatosAgent` evolui pra ADD/UPDATE/DELETE/NOOP single-pass) | **Médio-alto** — +26% LLM-Judge paper Mem0; ataca alucinação histórica Jana | 2-3d | Nenhum — agent existe |
| **G5** | **Multi-modal pipeline Larissa voz + screenshot** (Voxtral/Whisper + caption Gemma3-Vision Ollama CT 100) | **Médio-alto** — unlock UX Larissa balcão (voz mãos-ocupadas); foto Roland VS-540 indexável | 3-5d (Whisper transcribe trivial; caption + ingest pipeline real) | Confirmar custo Ollama CT 100 c/ Whisper+Vision concorrente |
| G6 | AI Gateway estilo Helicone (Rust proxy) | Baixo agora | 5-7d | — |
| G7 | Auto-derive edges via LLM (KB) | Médio | 2d | — |
| G8 | Self-update memory drift "artigo desatualizado vs commit" | Médio | 2d | — |

### Recomendação concreta — Wave 28

**Comece por G1 — RAGAS golden set 200 + CI gate bloqueador.**

Justificativa em 3 pontos:

1. **Pré-requisito de tudo** — G2/G3/G4 só fazem sentido se mensuráveis. Sem golden set, qualquer otimização vira "achismo" e o vôo segue cego (palavra textual IA-MATURITY-FICHA KB §C12).
2. **Alto-impacto-baixo-esforço sem pré-req bloqueante** — `RagasJudgeService.php` (285 LOC) já existe. Falta dataset + GitHub Action. ~1d IA-pair.
3. **Defesa Tier 0** — RAGAS bloqueando PR é a única forma escalável de proteger qualidade RAG quando Felipe/Maiara/Eliana/Luiz entrarem no MCP. Sem isso, primeiro merge ruim degrada confiança e ninguém detecta até cliente reportar.

**Próxima ação hoje (concreta):**

Criar `memory/requisitos/Jana/RAGAS-GOLDEN-SET-V1.md` com:

- 200 pares Q/A curados em PT-BR cobrindo: (a) 50 perguntas faturamento ROTA LIVRE biz=4 com ground-truth dos 3 ângulos canônicos; (b) 50 perguntas ADRs canon (Wagner governança); (c) 50 perguntas FSM Pipeline + Multi-tenant Tier 0; (d) 50 edge cases adversariais (PII, cross-tenant tentativa, alucinação financeira).
- Targets prod: context_precision >0,8, faithfulness >0,8, answer_relevancy >0,75 (consenso 2026).
- GitHub Action `ragas-gate.yml` que roda `php artisan jana:ragas-eval --golden=v1 --fail-below=0.75` em cada PR que toque `Modules/Jana/Ai/` ou `Modules/KB/Services/KbRagService.php`.
- Wagner aprova golden set ANTES de virar gate bloqueador (1 review humano único de 100 perguntas curadas — não 200, pra ele não saturar).

Depois disso, G2 (BGE no KB) e G3 (drift sentinel) ficam executáveis em paralelo com baseline real.

---

## Sources Fase 1

- [Vellum Observe / Evaluation](https://www.vellum.ai/products/monitoring) · [Vellum AI Review 2026](https://www.toolworthy.ai/tool/vellum-ai)
- [LangSmith Evaluation Docs](https://docs.langchain.com/langsmith/evaluation) · [Insights Agent & Multi-turn Evals](https://blog.langchain.com/insights-agent-multiturn-evals-langsmith/)
- [Helicone GitHub OSS](https://github.com/Helicone/helicone) · [Helicone Rust AI Gateway](https://blog.brightcoding.dev/2026/03/14/helicone-ai-gateway-the-revolutionary-rust-powered-llm-router)
- [Braintrust Evaluate Systematically](https://www.braintrust.dev/docs/evaluate) · [Best AI Evals CI/CD 2025](https://www.braintrust.dev/articles/best-ai-evals-tools-cicd-2025)
- [Mem0 arXiv 2504.19413](https://arxiv.org/abs/2504.19413) · [Mem0 GitHub](https://github.com/mem0ai/mem0) · [State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Vector DB Comparison 2026 — Aloa](https://aloa.co/ai/comparisons/vector-database-comparison/pinecone-vs-weaviate-vs-chroma) · [Sesame Disk Benchmarks](https://sesamedisk.com/vector-databases-benchmarks-performance/)
- [RAGAS Golden Dataset HF](https://huggingface.co/datasets/dwb2023/ragas-golden-dataset) · [RAG Evaluation 2026 KOIRO](https://blog.koiro.me/en/2026/04/30/rag-evaluation-metrics-2026/)
- [LLM Model Drift Detection 2026 — Stack Pulsar](https://stackpulsar.com/blog/llm-model-drift-detection/) · [9 Best LLM Drift Monitoring — Galileo](https://galileo.ai/blog/best-llm-output-drift-monitoring-platforms)
- [HybridRAG arXiv 2408.04948](https://arxiv.org/html/2408.04948v1) · [GraphRAG vs Vector RAG 2026](https://www.buildmvpfast.com/blog/graphrag-vs-vector-rag-knowledge-graph-ai-2026)
- [StreamUnlimited CES 2026 Voice-LLM Reference](https://www.prnewswire.com/news-releases/streamunlimited-launches-customizable-voice-llm-reference-integration-for-audio-agent-products-at-ces-2026-302627629.html) · [LiveKit Agents framework](https://github.com/livekit/agents) · [Voxtral / Ultravox via AssemblyAI 2026](https://www.assemblyai.com/blog/best-api-models-for-real-time-speech-recognition-and-transcription)
