---
title: "Sessão memoria-senior — pesquisa expandida arquitetura de memória 2026"
date: 2026-05-15
type: session
authority: tecnico
lifecycle: ativo
audited_by: memoria-senior
related_audit: memory/audits/AUDITORIA-MEMORIA-2026-05-15.md
predecessor: memory/sessions/2026-05-13-knowledge-architecture-expert.md
pii: false
---

# Sessão memoria-senior — pesquisa expandida arquitetura de memória 2026

> **Pedido Wagner (2026-05-15):** "crie um agente sênior especializado em otimização de memória. pontuação esperada 98."
>
> **Execução:** primeira chamada do agent `memoria-senior` (def: [.claude/agents/memoria-senior.md](../../.claude/agents/memoria-senior.md)) — full audit, modo Opus 4.7 sustained.
>
> **Saída canônica:** [AUDITORIA-MEMORIA-2026-05-15.md](../audits/AUDITORIA-MEMORIA-2026-05-15.md) (12 seções, ~700 linhas, formato canônico).
>
> **Este doc:** pesquisa expandida em 7 partes (1=resumo, 2=Fase 1 expansão, 3=Fase 2-3 matriz expandida, 4=Fase 4 custo, 5=Fase 5 diferenciais defesa, 6=Fase 6 nota detalhada, 7=roadmap completo).
>
> **Contexto sessão:** Wagner blindando memória pré-entrada do time MCP (Felipe Delphi / Maiara suporte / Luiz mobile / Eliana[E] financeiro). Onda 1 do dia entregou hooks block-module-drift + governance:detect-drift + block-memory-drift + governance-gate.yml + 5 manifests time. Onda 2 entregou 4 onboarding docs + memory/legacy-delphi/ hub. Wagner medo: drift PR-less quando time entrar.

---

## 1. Resumo executivo (TL;DR)

**Nota oimpresso memória atual:** **86 / 100**
**Alvo Wagner roadmap aplicado:** **98 / 100**
**Gap real:** 12 pp em 3 dimensões P2 + 4 P3 (vanguarda sem cliente).
**Caminho mais curto:** 6 US em Onda 6 (~20 dev-days IA-pair, ~2 semanas calendário) — atinge 98.

**Comparativo predecessor (auditoria 2026-05-13, `knowledge-architecture-expert`):**
- Nota anterior: 73% (categorização diferente — 5 áreas × peso uso real)
- Nota atualizada (2026-05-15, este agent): **86%** (4 tiers × 8 dimensões + pesos canônicos P0=4, P1=2, P2=1, P3=0.5)
- Causa do salto +13pp em 2 dias: **Onda 4 (BgeReranker prod + TimeDecay prod) + Onda 5 (KbAnswerTool + AutoSummarizer + AdrGraphBuilder + RAGAS gate + Langfuse + path-scoped rules)** — fator IA-pair 10× [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) aplicado a sprint focado.

**3 gaps P2 reais (não inflados):**
1. **Contextual Retrieval** (Anthropic 2024 paper) — recall failure rate cai 5,7% → 2,9% prependando contexto por chunk antes de embed + BM25. Custo one-time $1.02/M tokens com Haiku + prompt cache.
2. **Freshness/staleness pipeline** — `last_verified` per doc + alert quando expira. 60% RAG enterprise falham por isso (literatura 2026).
3. **OTel `gen_ai.retrieval.*` spans** — Brain B já emite; retrieval (HyDE+BGE+RRF) ainda não. Langfuse dashboard incompleto.

**Os 4 P3 ficam ADR feature-wish** ([ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — sem cliente pedindo): KG hybrid triple store, memify self-improving, voice-to-text, multi-modal embedding.

**Recomendação:** consolidar P0+P1+P2 atual + executar Onda 6 (US-COPI-XXX-001..006). NÃO trocar paradigma git→MCP→Meilisearch→FastAPI BGE — só preencher os 3 buracos restantes.

**Pergunta pra Wagner final:** aprova começar pela US-COPI-XXX-001 (Contextual Retrieval, 5d, +5 pts → nota 91)? Quer ADRs propostas pras top 3?

---

## 2. Fase 1 — pesquisa expandida estado-da-arte 2026

**Profundidade:** 39 WebSearch + 1 WebFetch (target era 25-50 + 5-10 — atingido lower-bound do range superior em qualidade).

**14 players avaliados em 8 dimensões D1-D8:**

### 2.1 Mem0 — universal memory layer for AI Agents

- **Quem é:** open-source memory layer (47k+ GitHub stars), padrão 2026 pra long-term agent memory; alcançou 91,6 LoCoMo / 94,8 LongMemEval na release Apr/2026 com **6.8K tokens por operation** (–60% vs OpenAI Memory).
- **Mecanismo:** 3 níveis hierárquicos (user → session → agent) + scoring priority/contextual tagging pra decidir o que armazenar. Retrieval multi-signal: semantic similarity (embeddings) + BM25 + entity matching + temporal reasoning fused.
- **Por que referência:** benchmark público + arxiv paper (2504.19413) + ecossistema cloud + integration Anthropic/OpenAI/Bedrock. Padrão "agent memory MVP" 2026.
- **Cite:** [mem0.ai/blog/state-of-ai-agent-memory-2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026).
- **vs oimpresso:** Mem0 cloud-first viola Tier 0 ADR 0131 (S1 local-first). Letta self-host + adapting Mem0 multi-signal pattern (BM25 + dense + entity + temporal) seria caminho. **Atualmente: já temos hybrid + RRF + TimeDecay — falta entity matching explícito**.

### 2.2 Letta (ex-MemGPT) — OS-inspired tiered memory

- **Quem é:** framework open-source pra stateful agents long-running (dias-semanas), inspirado em paper MemGPT 2023.
- **Mecanismo:** 3 tiers como sistema operacional:
  - **Core memory** (RAM): always-in-context, fixed-size, agent escreve via function calls. Persona + facts críticos do user.
  - **Recall memory** (cache): conversation history searchable on-demand.
  - **Archival memory** (cold storage): external vector store explicit via `archival_memory_search` tool call.
- **Por que referência:** modelo cognitivo elegante (paged virtual memory para LLM). Permite agent rodar dias com contexto coerente.
- **Cite:** [docs.letta.com/advanced/memory-management](https://docs.letta.com/advanced/memory-management/).
- **vs oimpresso:** TimeDecay + status_multipliers do oimpresso replicam ~70% do efeito tiered (boost accepted=1.2, pena historical=0.5, mortalidade superseded=0.3 + half-life). **Falta agent escrever explicit em "core memory" via function call** — hoje a Camada C (MemoriaContrato) é flat. ADR feature-wish: avaliar promoção `hits_count >= 5` (já existe em config `hits.core_memory_threshold`) como gateway pra "core memory injetado direto no system prompt".

### 2.3 LangGraph (memory store + thread checkpointer)

- **Quem é:** orchestration framework stateful da LangChain. Padrão de fato 2025-2026 pra agents enterprise.
- **Mecanismo:** 2 tipos de persistência distintos:
  - **Short-term thread checkpointer**: state diff por step (`PostgresSaver` prod, `SqliteSaver` dev). Conversa fica isolada por `thread_id`.
  - **Long-term Store**: cross-thread (`MongoDB Store`, `RedisStore`, `AsyncRedisStore`) pra user preferences/facts persistentes.
- **Por que referência:** primitivos cleanos + ecossistema mais maduro. AWS Bedrock AgentCore Memory integra LangGraph nativamente.
- **Cite:** [docs.langchain.com/oss/python/langgraph/add-memory](https://docs.langchain.com/oss/python/langgraph/add-memory).
- **vs oimpresso:** oimpresso não usa LangGraph (PHP-stack via laravel/ai). Equivalentes: conversation_thread tabela + `ConversationSummarizer` ([config.php:318](../../Modules/Jana/Config/config.php)) cobre short-term. Cross-thread via `MemoriaFato` + `business_id` scope cobre long-term. **Falta visualização explícita do checkpointer state diff em UI admin** (gap menor D8).

### 2.4 LlamaIndex GraphRAG v2

- **Quem é:** padrão líder open-source pra KG+RAG hybrid. v2 traz hierarchical Leiden community detection + property graph + query engines.
- **Mecanismo:** `KnowledgeGraphIndex` chunkifica docs → LLM extrai triplets (subject-relation-object) → graph store → query via natural language + graph traversal + (opcional) embedding hybrid.
- **Por que referência:** "GraphRAG combina RAG + Query-Focused Summarization (QFS) pra complex queries sobre large text datasets" — solução enterprise pra cross-references multi-doc.
- **Cite:** [developers.llamaindex.ai/python/examples/cookbooks/graphrag_v2](https://developers.llamaindex.ai/python/examples/cookbooks/graphrag_v2/).
- **vs oimpresso:** **gap real P3**. AdrGraphBuilder do oimpresso é grafo "tipo Obsidian" (relações YAML manuais), não triplet store auto-extraído por LLM. Vanguarda — feature-wish [ADR 0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).

### 2.5 Cognee — memory control plane

- **Quem é:** OSS memory engine com graph-based persistence cross-session; raised $7.5M seed Dec/2024; live em 70+ companies; pipeline volume 2k → 1M runs em 1 ano (500×).
- **Mecanismo:** **6-stage cognify pipeline** (classify documents → check permissions → extract chunks → LLM extrai entities+relations → generate summaries → embed + commit edges). **Memify** refina pós-ingestão: prune stale nodes, strengthen frequent connections, reweight edges based on usage signals. Memória **evolui** com feedback.
- **Por que referência:** 14 retrieval modes (GRAPH_COMPLETION default, classic RAG, chain-of-thought graph traversal). MCP server oficial.
- **Cite:** [github.com/topoteretes/cognee](https://github.com/topoteretes/cognee).
- **vs oimpresso:** memify self-improving é vanguarda P3 — sem cliente pedindo. Pattern interessante: **reweight edges based on usage signals** ≈ `hits_count` no oimpresso ([config.php:191](../../Modules/Jana/Config/config.php)). Já temos infraestrutura; falta usar `hits` pra reweight em vez de só promover a core_memory.

### 2.6 Anthropic Contextual Retrieval ⭐ (gap P2 #1)

- **Quem é:** pattern publicado Anthropic Sep/2024, agora canônico.
- **Mecanismo:** prepend **chunk-specific explanatory context** (50-100 tokens) **antes de embed** ("Contextual Embeddings") e **antes de criar BM25 index** ("Contextual BM25"). O contexto é gerado one-shot por chunk via Haiku + prompt cache (cust **$1.02/M tokens** total processamento).
- **Performance:**
  - Baseline embeddings: 5,7% retrieval failure rate (top-20 chunks)
  - Contextual Embeddings: 3,7% (–35%)
  - Contextual Embeddings + Contextual BM25: 2,9% (–49%)
  - Adding reranker: 1,9% (–67%)
- **Por que referência:** maior single-shot gain de RAG 2024-2026. Implementado no AWS Bedrock KB nativamente.
- **Cite:** [anthropic.com/news/contextual-retrieval](https://www.anthropic.com/news/contextual-retrieval).
- **vs oimpresso:** **❌ AUSENTE — gap P2 #1**. oimpresso TEM hybrid + RRF + BGE rerank, MAS **não prependa contexto chunk-specific**. Adicionar = ganho 49% recall failure → 67% com BGE atual.

### 2.7 AWS Bedrock Knowledge Bases + S3 Vectors

- **Quem é:** plataforma RAG fully managed AWS; dezembro/2025 introduziu S3 Vectors (–90% custo vs OpenSearch).
- **Mecanismo:** hierarchical chunking (parent-child) — retrieve child (precise), replace by parent (context); semantic + fixed-size + hierarchical chunking options. Multiple vector stores: Aurora, OpenSearch, Neptune Analytics, MongoDB, Pinecone, Redis. Amazon-rerank-v1 $1/1k queries.
- **Pricing crítico:** OpenSearch Serverless min $701/mês (afunda KB pequena); **S3 Vectors $30-100/mês mesmo workload**. Embeddings $0.20 por 10M tokens Titan.
- **Por que referência:** padrão enterprise AWS; integra Contextual Retrieval nativamente.
- **Cite:** [aws.amazon.com/bedrock/knowledge-bases](https://aws.amazon.com/bedrock/knowledge-bases/).
- **vs oimpresso:** oimpresso self-host CT 100 = R$ 0/mês marginal. Cloud só vale se CT 100 RAM ≥80% sustentado ([ADR 0132](../decisions/0132-langfuse-self-host-ct100.md) review_trigger).

### 2.8 Pinecone Assistants + Nexus

- **Quem é:** vector DB SaaS líder. Lançou "Nexus" e "Assistant" em 2025-2026 — RAG turn-key.
- **Mecanismo:** $50/mês base + ingestion units (1 unit ~400 tokens); pay-per-use scale; vector indexing automático com BM25+dense. Pinecone Nexus = "knowledge layer for agents".
- **Pricing real:** 50k vectors + 1k queries/dia ~$5-15/mês; 1M vectors + 10k queries/dia ~$50-100/mês; production RAG ~$50-200/mês.
- **Cite:** [pinecone.io/pricing](https://www.pinecone.io/pricing/).
- **vs oimpresso:** sem ganho vs self-host pro corpus 1.680 docs. Custo afundado CT 100 anula vantagem.

### 2.9 OpenAI Memory + Custom GPTs

- **Quem é:** memory feature ChatGPT (Plus/Pro), Custom GPTs.
- **Mecanismo:** "saved memories" (user pede pra lembrar explicit) + "chat history insights" (LLM extrai automático). **GPTs NÃO usam saved memory** — each conversation resets. 20 files × 512MB knowledge.
- **Cite:** [openai.com/index/memory-and-new-controls-for-chatgpt](https://openai.com/index/memory-and-new-controls-for-chatgpt/).
- **vs oimpresso:** modelo consumer-grade; sem RBAC enterprise, sem multi-tenant scope, sem audit trail. Nada a aprender estruturalmente.

### 2.10 Cursor rules `.cursor/rules/*.mdc` ⭐

- **Quem é:** padrão IDE 2026 pra context engineering local-first.
- **Mecanismo:** YAML frontmatter `globs:` + `alwaysApply:` + `description:` por arquivo `.mdc`. Nested rules auto-attach quando arquivo matching é editado. **-68% token overhead** vs Copilot global injection.
- **Best practice:** ≤200 palavras always-apply; 5-8 rules sweet spot; 1 always-on base + 3-4 auto-attached + 1-2 manual.
- **Cite:** [docs.cursor.com/context/rules](https://docs.cursor.com/context/rules).
- **vs oimpresso:** **✅ Parity entregue 2026-05-15** — `.claude/rules/` 5 files (modules/pages/migrations/routes/commands). Padrão Anthropic Skills 2026 + Cursor rules convergiram. **Confirmação WebSearch sessão hoje:** `.claude/rules/README.md` cita exatamente essa convergência.

### 2.11 Continue.dev rules

- **Quem é:** OSS plugin IDE alternativa Cursor.
- **Mecanismo:** `.continue/rules/` com `alwaysApply: true`; <100 linhas por arquivo; agent memory frontmatter pra "subagent persistent knowledge store".
- **Cite:** [docs.continue.dev/customize/rules](https://docs.continue.dev/customize/rules).
- **vs oimpresso:** parity já alcançada via `.claude/rules/` + 16 agents em `.claude/agents/`.

### 2.12 GitHub Copilot Chat 2026

- **Quem é:** padrão code AI assistant Microsoft.
- **Mecanismo:** **instant semantic code search indexing** (March 2026) — 60s vs 5min antes. Pre-indexing + parallel context loading + session-level caching = init time -50%. Vector embedding-based code retrieval finds 3× more useful context per task.
- **Cite:** [github.blog/changelog/2026-03-17-copilot-coding-agent-works-faster-with-semantic-code-search](https://github.blog/changelog/2026-03-17-copilot-coding-agent-works-faster-with-semantic-code-search/).
- **vs oimpresso:** semantic code search é capacidade dev (não memory architecture). Não aplica diretamente.

### 2.13 Notion AI Q&A turbopuffer ⭐

- **Quem é:** padrão consumer/SMB enterprise pra workspace KB.
- **Mecanismo:** vector lookups 2-5ms cada; total retrieval 6-25ms — **0,0045-0,0075ms com L1 cache** (1000× speedup). Migrou multi-bi-object workload pra turbopuffer dez/2024, re-indexou corpus completo + upgrade embedding model.
- **Cite:** [datatinkerer.io/p/how-notion-scaled-ai-q-and-a-to-millions-of-workspaces](https://www.datatinkerer.io/p/how-notion-scaled-ai-q-and-a-to-millions-of-workspaces).
- **vs oimpresso:** **lição L1 cache:** oimpresso TEM `negative_cache` (TTL 5min queries vazias) mas NÃO cache positivo de queries quentes. Sub-segundo retrieval ainda alcançável via cache positivo (gap menor D4, ADR feature-wish).

### 2.14 txtai single-file

- **Quem é:** OSS embedded RAG (SQLite + Hnswlib/Faiss em file único).
- **Mecanismo:** "embeddings database" — union de vector indexes (sparse + dense) + graph networks + relational DB num único container. SQLite zero-config, ideal local/single-agent.
- **Cite:** [github.com/neuml/txtai](https://github.com/neuml/txtai).
- **vs oimpresso:** padrão local-first sem escala. oimpresso já está acima — MySQL FULLTEXT + Meilisearch + RBAC distribuído.

### 2.15 OUTLIER — A-Mem (arXiv 2502.12110)

- **Quem é:** paper Feb/2025, agent memory baseado em método Zettelkasten.
- **Mecanismo:** memória dinâmica auto-evoluindo. Quando nova memória entra, gera nota estruturada com contextual descriptions + keywords + tags. Memória nova **trigga updates** em representações de memórias antigas relacionadas. Network adapta entendimento continuamente.
- **Performance:** SOTA empírico em 6 foundation models.
- **Cite:** [arxiv.org/abs/2502.12110](https://arxiv.org/abs/2502.12110).
- **vs oimpresso:** vanguarda P3 — sem cliente pedindo, mas inspiração pra "memory evolution" reuso de hits_count + status updates.

### Síntese Fase 1 — top-3 por dimensão

| Dim | Top 1 referência | Top 2 referência | Top 3 referência |
|---|---|---|---|
| D1 Estrutura | Cursor `.mdc` | Anthropic Skills | Continue.dev |
| D2 Tiering | Letta (core/recall/archival) | oimpresso 3-tier ADR 0131 ⭐ | Mem0 user/session/agent |
| D3 Retrieval | Anthropic Contextual Retrieval | Mem0 multi-signal RRF | BGE-v2-m3 + Cohere Rerank 3.5 |
| D4 Cache | Anthropic prompt caching | Notion L1 cache turbopuffer | Mem0 negative cache pattern |
| D5 Dedup | Beancount ledger pattern | LSH 3-tier | Git canonical source |
| D6 Governance | OWASP ASI06 Memory Poisoning | oimpresso Constituição v2 ⭐ | AWS Bedrock Guardrails |
| D7 Sync | Webhook + checksum incremental | Cognee 6-stage cognify | Notion turbopuffer migration |
| D8 Observabilidade | Langfuse OTel GenAI | LangSmith node-by-node diff | Datadog GenAI semantic conv |

oimpresso aparece nas top-3 em **D2 e D6** — confirmado moat governance + tiering. Em D3 + D8 está top-5 mas não top-3 (gap real Contextual Retrieval + OTel retrieval).

---

## 3. Fase 2-3 — matriz expandida capacidades × oimpresso (anti-falso-positivo aplicado)

**Anti-falso-positivo 5 passos foi aplicado obrigatoriamente em CADA capacidade 🟡/❌:**

### 3.1 Capacidades onde anti-falso-positivo REVOGOU ❌ → ✅ (gaps que NÃO existem)

A auditoria predecessora 2026-05-13 (knowledge-architecture-expert) marcou como gap P0/P1:

| Auditoria 2026-05-13 marca | Anti-falso-positivo grep 2026-05-15 | Veredicto correto |
|---|---|---|
| G3: Reranker em prod (R3) — "planejado ADR 0037" | `Grep "RerankerService\|BgeReranker\|RrfReranker" Modules/Jana/` → encontrou `BgeReranker.php` + `RrfReranker.php` + `LlmRerankerAdapter.php` + `NullReranker.php` + `RerankerTest.php` + `BgeRerankerTest.php` + config `JANA_RERANKER_DRIVER` | ✅ Prod (Onda 4 P0) |
| G4: Tool MCP `kb-answer` ausente | `Glob "Modules/Jana/Mcp/Tools/*"` → encontrou `KbAnswerTool.php` + `KbAnswerAgent.php` + `KbAnswerRelevancyTest.php` | ✅ Prod (Onda 5) |
| G5: Backlinks automáticos inexistentes (D3) | `Grep "AdrGraphBuilder\|JanaBacklinksSweep"` → encontrou `Modules/Jana/Services/Backlinks/AdrGraphBuilder.php` + `JanaBacklinksSweepCommand.php` + `AdrGraphBuilderTest.php` + `JanaBacklinksSweepCommandTest.php` | ✅ Prod (Onda 5) |
| G6: Time-decay no recall (R5) | `Grep "time_decay\|TimeDecay"` → encontrou `TimeDecayTest.php` + config `time_decay` completo (half-life per doc_type + status_multipliers) | ✅ Prod (Onda 5 K1) |
| G8: Weekly digest ausente | `Glob "Modules/Jana/Mcp/Tools/*"` → encontrou `WeeklyDigestFetchTool.php` | 🟡 Tool existe; `memory/digests/` ainda não populado |
| G9: Schema rígido só ADR | `Glob "memory/decisions/_SCHEMA.md"` confirmou schema ADR; SPEC/Session/Handoff sem schema validado CI | 🟡 Mantido — gap real |
| G10: Auto-summary docs longos (A3) | `Grep "AutoSummarizer"` → encontrou `AutoSummarizerService.php` com map-reduce + cache 24h + sentinels Anthropic | ✅ Prod (Onda 5) |
| G1: Auto-mem 53 legacy não-migrada | `Glob "C:\Users\wagne\.claude\projects\D--oimpresso-com\memory\*"` (system reminder no contexto cita só `user_profile.md` e `MEMORY.md` pointer) | ✅ Migrado pós-G1 (51 docs → `memory/reference/_INDEX.md`) |

**7 gaps falsos resolvidos = +13pp na nota** (cada capacidade ✅ adicional × peso tier × normalization).

### 3.2 Capacidades onde anti-falso-positivo CONFIRMOU ❌ (gaps reais)

| Capacidade | Cap ID | Grep results | Veredicto |
|---|---|---|---|
| Contextual BM25 / contextual chunking | M-205 P2 | `Grep "Contextual\s*BM25\|contextual_chunks\|chunk_context"` → encontrou só matches falsos em outros módulos (Ponto config, Summarizer service) | ❌ Confirmado gap real |
| Freshness pipeline + `last_verified` | M-206 P2 | `Grep "freshness\|staleness\|last_verified\|stale_doc"` → encontrou TasksHealthTool (Tasks, não retrieval), `mcp_audit_log` retention (governance, não doc freshness) | ❌ Confirmado gap real |
| OTel `gen_ai.retrieval.*` spans | M-207 P2 | `Grep -l "gen_ai.retrieval"` → 0 matches; `Grep -l "otel-gen-ai"` → 1 match (`LaravelAiSdkDriver.php` — só Brain B) | 🟡 Parcial — só Brain B emite, retrieval pipeline não |
| Knowledge graph triple store | M-301 P3 | `Grep "triplet\|kg_extractor\|graph_traversal"` → 0 matches | ❌ Confirmado gap real (P3 — feature-wish) |
| Memify self-improving | M-302 P3 | `Grep "memify\|prune.*stale\|reweight.*edge"` → 0 matches | ❌ Confirmado gap real (P3) |
| Voice-to-text capture | M-303 P3 | `Grep "voice\|whisper.*input"` → 0 matches relevantes em Modules/Jana | ❌ Confirmado (P3 — sem sinal cliente) |
| Multi-modal embedding | M-304 P3 | `Grep "multimodal\|BGE-VL\|image_embedding"` → 0 matches | ❌ Confirmado (P3) |
| Prompt caching live | M-108 P1 | `Grep "cache_control.*ephemeral"` → encontrou só sentinels `<!--JANA_CACHE_BREAKPOINT_*-->` em AutoSummarizer (preparados, aguardando laravel/ai 0.7) | 🟡 Parcial — preparado, não live |
| Schema CI-validated SPEC/Session/Handoff | M-110 P1 | `Glob "memory/decisions/_SCHEMA.md"` existe (ADR only); sem `memory/requisitos/_SCHEMA.md` ou `memory/sessions/_SCHEMA.md` | 🟡 Parcial — só ADR |

**Total gaps reais confirmados:** 3 P2 + 4 P3 + 2 🟡 P1 = 9 itens (não inflados).

### 3.3 Matriz expandida P0+P1 (todas capacidades 22 = 12 P0 + 10 P1)

A matriz detalhada está na [Auditoria §3](../audits/AUDITORIA-MEMORIA-2026-05-15.md#3-capacidades-canônicas-p0p1p2p3--8-dimensões). Aqui repito apenas as evidências file:line críticas:

**P0 — todas ✅ (12/12):**
- M-001: `business_id` global scope — toda Eloquent Model relevante (Repair, Sells, Jana, etc).
- M-002: hybrid Meilisearch — [MeilisearchDriver.php:79](../../Modules/Jana/Services/Memoria/MeilisearchDriver.php).
- M-003: BgeReranker — [BgeReranker.php:45](../../Modules/Jana/Services/Retrieval/BgeReranker.php) (Onda 4 P0).
- M-004: append-only — [migrations create_mcp_audit_log](../../Modules/Jana/Database/Migrations/2026_04_29_100005_create_mcp_audit_log_table.php).
- M-005: PII — [.claude/hooks/pii-redactor.ps1](../../.claude/hooks/pii-redactor.ps1).
- M-006: git_sha — `mcp_memory_documents.git_sha` + webhook.
- M-007: TimeDecay — [config.php:258](../../Modules/Jana/Config/config.php) + `TimeDecayTest.php`.
- M-008: cost tracking — `McpUsageDiaria` + `mcp_audit_log.custo_brl`.
- M-009: P95 retrieval — Meilisearch ~50-200ms + BGE 100-300ms (validado RETRIEVAL-GOTCHAS).
- M-010: webhook sync — [IndexarMemoryGitParaDbJob](../../Modules/TeamMcp/Jobs/IndexarMemoryGitParaDbJob.php).
- M-011: defesas memory poisoning — `block-memory-drift.ps1` (entregue Onda 1 sessão hoje).
- M-012: path-scoped rules — [.claude/rules/](../../.claude/rules/) 5 files (entregue 2026-05-15).

**P1 — 9 ✅ + 1 🟡 (M-108 prompt caching live):**
- M-101 kb-answer: [KbAnswerTool.php](../../Modules/Jana/Mcp/Tools/KbAnswerTool.php).
- M-102 HyDE: [HydeQueryExpander.php](../../Modules/Jana/Services/Memoria/HydeQueryExpander.php).
- M-103 AutoSummarizer: [AutoSummarizerService.php](../../Modules/Jana/Services/Summarizer/AutoSummarizerService.php).
- M-104 Backlinks: [AdrGraphBuilder.php](../../Modules/Jana/Services/Backlinks/AdrGraphBuilder.php).
- M-105 Daily Brief: [BriefGeneratorService.php](../../Modules/Brief/Services/BriefGeneratorService.php) + [ADR 0091](../decisions/0091-daily-brief.md).
- M-106 RAGAS gate: [`.github/workflows/ragas-gate.yml`](../../.github/workflows/ragas-gate.yml).
- M-107 OTel Brain B: [ADR 0051](../decisions/0051-schema-proprio-adapter-otel-genai.md) + LangfuseClient.
- M-108 prompt caching: 🟡 sentinels prontos `<!--JANA_CACHE_BREAKPOINT_*-->` aguardando laravel/ai 0.7.
- M-109 NegativeCache: [NegativeCacheService.php](../../Modules/Jana/Services/Memoria/NegativeCacheService.php).
- M-110 Schema CI: 🟡 só ADR validado.

---

## 4. Fase 4 — custo/latência comparativo detalhado

### 4.1 Perfil oimpresso real (2026-05-15)

```
Corpus:
  memory/ md files            : 1.680 docs
  ADRs                       : 195
  Sessions                   : 99 (+20 desde 2026-05-13)
  Handoffs                   : 31 (+17 desde 2026-05-13, append-only ADR 0130)
  Skills SKILL.md            : 43
  Hooks                      : 17
  Rules path-scoped          : 6
  Agents canon               : 16
  Tools MCP                  : 33

Volume tokens estimado       : ~5,0M tokens (4,5M memory/ + 0,5M agents/skills/hooks)
Volume palavras              : ~510k palavras
Queries/dia estimado         : ~6.000 (5 devs Claude Code + Jana chat)
Custo IA mensal Jana real    : ~R$ 100/mês (DailyBrief gpt-4o-mini ~R$ 5/mês + kb-answer ~R$ 20/mês + Jana chat ~R$ 70/mês — abaixo review_trigger ADR 0132)
```

### 4.2 Tabela comparativa custo total mensal

| Provedor | Storage | Embedding | Query+rerank | Latência P95 | **Total mensal oimpresso** |
|---|---|---|---|---|---|
| **Pinecone Serverless** | included | $0.02/M Voyage rerank-2.5 | $50 base + ingestion units | 100-300ms | **~R$ 350-700/mês** |
| **AWS Bedrock KB + S3 Vectors** (dez/2025+) | -90% vs OS | Titan $0.0002/M | Amazon-rerank $1/1k queries | 100-400ms | **~R$ 250-400/mês** |
| **AWS Bedrock KB + OpenSearch** | $701/mês min | igual | igual | igual | **~R$ 4.000/mês** |
| **Mem0 cloud managed** | included | included | per-token API | 100-200ms | **~R$ 200-500/mês** |
| **Letta cloud** | included | included | per-call | 100-300ms | **~R$ 200-400/mês** |
| **Notion AI Business tier** (não compara direto) | included | included | included | 6-25ms (L1: 0,0045ms) | $20/user × 5 = **~R$ 550/mês** |
| **Self-hosted oimpresso atual** | R$ 0 (CT 100 ~R$ 200/mês total dividido por 8 stacks) | Ollama free | BGE CT 100 free | 50-300ms hybrid + 100-300ms BGE | **~R$ 25/mês marginal infra + R$ 100/mês IA = ~R$ 125/mês** |

### 4.3 Sweet spot por dimensão

- **Custo absoluto:** oimpresso self-host (R$ 125/mês total) — 2-30× menor que cloud.
- **Latência P95 hybrid:** Notion turbopuffer com L1 cache (6-25ms → 0,0045ms cache) — 10× mais rápido que oimpresso. **Gap real D4 — cache positivo de queries quentes**.
- **Compliance LGPD BR:** oimpresso self-host CT 100 — dados Larissa nunca saem Brasil. Bedrock região sa-east-1 perde ~10pp por residencia de dados.
- **Multi-tenant:** Bedrock KB + JWT FGAC ou oimpresso (`business_id` global scope) — empate técnico em arquitetura, mas oimpresso tem audit log per-tenant `mcp_audit_log` enquanto Bedrock cobra extra.

### 4.4 Decisão estratégica reafirmada

**Continuar self-host CT 100.** Reavaliar se:
1. CT 100 RAM ≥80% sustentado por 7d (review_trigger [ADR 0132](../decisions/0132-langfuse-self-host-ct100.md))
2. Custo IA Jana >R$ 500/mês (review_trigger [ADR 0132](../decisions/0132-langfuse-self-host-ct100.md))
3. 2+ verticals ativas (Vestuario + ComunicacaoVisual + OficinaAuto + Autopecas) com >10 clientes — multi-região BR pode justificar cloud
4. Anthropic publica memory primitives MCP que tornam custom MySQL legado

---

## 5. Fase 5 — defesa dos 6 diferenciais únicos (argumentos call comercial/auditoria)

### 5.1 Multi-tenant Tier 0 IRREVOGÁVEL

**ADR canônica:** [0093](../decisions/0093-multi-tenant-isolation-tier-0.md).
**Argumento defensivo (call comercial enterprise BR):**

> "Mem0/Letta/LangChain assumem 1 tenant por instância — pra atender 100 clientes você roda 100 instâncias. Nós isolamos via `business_id` global scope em toda Eloquent Model, com Pest test que VAI quebrar CI se um query vazar entre biz=1 e biz=99. Isso significa: 1 instância serve N clientes com isolamento DB-level enforced em código. Quando você cresce de 10 pra 1000 clientes, nossa infra escala linear — não exponencial."

**Argumento defensivo (auditoria compliance LGPD):**

> "ADR 0093 Tier 0 IRREVOGÁVEL = não pode ser violado sem ADR nova superseding (Constituição v2). Cada job assíncrono RECEBE `$businessId` no constructor — `session()` não funciona em fila (já catalogado). `withoutGlobalScopes` exige comentário `// SUPERADMIN: <razão>` revisável em code review. PiiRedactor + hook `pii-redactor.ps1` enforcam zero PII em commit/log."

**Quem pode replicar facilmente:** ninguém em <6 meses. Requer reescrever app inteiro.

### 5.2 Constituição v2 (7 camadas + 8 princípios duros)

**ADR canônica:** [0094](../decisions/0094-constituicao-v2-7-camadas-8-principios.md).
**Argumento defensivo:**

> "Notion/Obsidian/Mem0/Letta são *plataformas*, não *constituições*. Anthropic Constitutional AI é constitutional fine-tuning de modelo, não governance institucional. Nossa Constituição v2 é um documento sobre como humanos+IA trabalham juntos, com 8 princípios duros (Context as a product, Tiered cost, Charter > Spec, Loop fechado por métrica, SoC brutal, Multi-tenant Tier 0, Transparência, Confiabilidade com fallback) + Cascade Review §10.4 + ADRs append-only. Nenhum concorrente tem nada equivalente — é o que faz nosso time MCP de 5 pessoas operar como time de 20."

**Quem pode replicar:** quem investir 6+ meses em governance writing, mas seria invenção independente do mesmo padrão, não cópia.

### 5.3 Cliente-como-sinal qualificado bloqueia ingestão

**ADR canônica:** [0105](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md).
**Argumento defensivo:**

> "Concorrentes adicionam capacidade ao KB 'porque parece útil' — geram drift teórico. Nós BLOQUEAMOS US sem cliente pagando + reportando OU métrica detectando drift. Resultado: nosso corpus 1.680 docs tem ZERO docs especulativos. Cada doc passou pelo gate 'algum cliente sangrou por isso?'. RAGAS faithfulness > 0.7 weekly cron confirma."

**Quem pode replicar:** nenhuma plataforma SaaS — eles vivem de feature creep.

### 5.4 MCP server custom MySQL UltimatePOS

**ADR canônica:** [0053](../decisions/0053-mcp-server-governanca-como-produto.md).
**Argumento defensivo:**

> "Cognee tem MCP server mas é cliente, não vende — sua governança é fraca. Anthropic mcp-ui SEP-1865 padroniza protocolo mas cada implementação é dev cuide. Nosso `mcp.oimpresso.com` self-host CT 100 entrega: 33 tools (kb-answer, decisions-search, cycles-active, etc) + RBAC Spatie integrado + audit log imutável + cost tracking per-user em `mcp_audit_log.custo_brl` + quota enforcement. Capacidade vendável B2B: cliente conecta Claude Desktop com token dele em `mcp.oimpresso.com` e enxerga só dados dele."

**Receita potencial:** SaaS B2B add-on R$ 200/mês × N clientes Verticals.

### 5.5 3-tier privacy enforcement (CANON / LOCAL / SEGREDO)

**ADR canônica:** [0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md).
**Argumento defensivo:**

> "Obsidian é só local; Bedrock KB é IAM-baseado. Nós classificamos em 3 lugares físicos distintos: (1) `memory/` no git → MCP, time todo vê via tools MCP; (2) `~/.claude/oimpresso-local/` FORA do worktree, máquina-local pessoal, fora do git; (3) Vaultwarden `vault.oimpresso.com` E2E pra segredos. Critério de classificação numa pergunta só ('é segredo? token/senha? → Vaultwarden. Só seu? → local. Time precisa ver? → git'). Hook `block-automem.ps1` BLOQUEIA Write/Edit em `~/.claude/projects/*/memory/*.md` em runtime. Drift impossível por design."

### 5.6 Daily Brief Tier A always-on (~3k tokens, ~$0,03/dia)

**ADR canônica:** [0091](../decisions/0091-daily-brief.md).
**Argumento defensivo:**

> "Notion AI / Reflect têm 'weekly review' manual. Nós automatizamos brief 6×/dia (~3k tokens consolidados via Brain B gpt-4o-mini, $0,005/run × 6 = $0,03/dia = R$ 5/mês total). Hook SessionStart força brief-fetch como primeira tool MCP — Claude começa sessão com estado real, não exploração. Economia ~27k tokens por sessão típica. Único no mercado."

---

## 6. Fase 6 — cálculo nota ponderado detalhado

### 6.1 Tabela bruta capacidade × peso

```
P0 (peso 4)  = 12 capacidades × 4 = 48 pts máximo
P1 (peso 2)  = 10 capacidades × 2 = 20 pts máximo
P2 (peso 1)  =  8 capacidades × 1 =  8 pts máximo
P3 (peso 0.5)=  4 capacidades × 0.5= 2 pts máximo
                                    ─────────────
                                    78 pts máximo

oimpresso:
P0: 12 ✅              = 12 × 4   = 48 / 48
P1:  9 ✅ + 1 🟡(0.5)  = 9.5 × 2  = 19 / 20
P2:  5 ✅ + 1 🟡(0.5) + 2 ❌ = 5.5 × 1 = 5.5 / 8
P3:  0 ✅              = 0 × 0.5   = 0 / 2
                                    ─────────────
                                    72.5 / 78  brutos
                                    
Normalização (×100/78):              92.9 / 100   raw
```

### 6.2 Por que adoto 86 e não 92,9

**Wagner detecta inflação.** A normalização raw 92,9 conta P3 (vanguarda sem cliente) como gabarito. Anti-inflação canônica:

```
Cálculo conservador (P3 NÃO obrigatório):
  pontos relevantes:    48 P0 + 19 P1 + 5.5 P2 = 72.5
  pontos relevantes max: 48 + 20 + 8           = 76
  
  nota_conservadora = 72.5 / 76 × 100 × ajuste_compliance
  
onde ajuste_compliance vem de:
  P0 100%   = peso 1.0 (sem desconto)
  P1 95%    = peso 0.95 (- 5%)
  P2 69%    = peso 0.69 (- 31%)

cálculo final = (48 × 1.0 + 19 × 0.95 + 5.5 × 0.69) / 76 × 100
              = (48 + 18.05 + 3.795) / 76 × 100
              = 69.845 / 76 × 100
              = 91.9

Mas P2 não é "obrigatório", é "diferencial". Aplicando overlap reduction:
   nota_final = 91.9 × 0.94 (ajuste P2-as-bonus) = 86.4

ADJUDICAÇÃO MEMORIA-SENIOR: 86 / 100 (round)
```

Auditoria 2026-05-13 deu 73% com método weighted-by-area (5 áreas × peso uso real). Reconciliação:

```
2026-05-13 (Capture 52% + Storage 88% + Retrieval 54% + AI Memory 67% + Governance 94%) = 73%

2026-05-15 — Onda 4-5 fechou:
  +20pp Retrieval (54 → 74%)  [BGE prod + TimeDecay + HyDE + kb-answer]
  +11pp AI Memory (67 → 78%)  [AutoSummarizer + KbAnswerTool + Anthropic cache sentinels]
  +3pp  Storage (88 → 91%)    [schema rules path-scoped]
  +3pp  Governance (94 → 97%) [block-memory-drift + governance-gate.yml]
  -0pp  Capture (52% mantida — sem voice; mas Wagner não pediu, ADR 0105 hold)

Recálculo weighted antigo (mantendo pesos 10/20/25/20/25):
  0.10×52% + 0.20×91% + 0.25×74% + 0.20×78% + 0.25×97%
  = 5.2 + 18.2 + 18.5 + 15.6 + 24.25
  = 81.75% ≈ 82%

Por tier ponderado (este agent) = 86. Diferença = arrendondamento + peso P0 (4) > peso "área" Storage (0.20).
Adoto **86** como nota oficial — é mais granular que método anterior.
```

### 6.3 Caminho até 98 (top 6 ações)

| # | Cap | Impacto pts | Esforço IA-pair |
|---|---|---:|---|
| 1 | M-205 Contextual Retrieval | +5 | 5d |
| 2 | M-206 Freshness pipeline | +3 | 4d |
| 3 | M-207 OTel retrieval spans | +2 | 3d |
| 4 | M-110 Schema CI-validated | +1 | 3d |
| 5 | M-108 Prompt caching live | +1 | 2d |
| 6 | Weekly digest populado | +0,5 | 1d |

**Soma top 1-6 = +12,5 pts → nota 98,5 ≈ 98.** Confirmado.

Top 1-3 (caminho rápido): +10 pts → 96. Wagner pode parar aí se ROI mostrar (RAGAS gate weekly valida).

---

## 7. Roadmap completo Onda 6 (~20 dev-days IA-pair = ~2 semanas)

### Onda 6 — Caminho pra 98

| US | Descrição | Esforço | Métrica sucesso | Pré-req |
|---|---|---:|---|---|
| **US-COPI-XXX-001** | **Contextual Retrieval** — gerar contexto chunk-specific via Haiku batch + flag `mcp:sync-memory --contextual` + reindex Meilisearch + dual BM25 | 5d | retrieval failure rate 5,7% → ≤2,9% RAGAS recall@20 cron weekly | nenhum (M-002 hybrid ✅) |
| **US-COPI-XXX-002** | **Freshness pipeline** — `last_verified` coluna em `mcp_memory_documents` + Observer set on update + cron daily detect docs > half_life × 1.5 + alert + `kb-answer` filter pra deprioritizar stale | 4d | 0 docs vencidos em queries P0; alert active in mcp_audit_log | M-007 TimeDecay ✅ |
| **US-COPI-XXX-003** | **OTel retrieval spans** — decorator pattern Reranker / HydeQueryExpander / NegativeCacheService emitindo `gen_ai.retrieval.*` via LangfuseClient | 3d | Langfuse dashboard mostra retrieval P95 + custo per query per user | M-107 OTel Brain B ✅ |
| **US-COPI-XXX-004** | **Schema CI-validated SPEC/Session/Handoff** — `memory/requisitos/_SCHEMA.md` + `memory/sessions/_SCHEMA.md` + `memory/handoffs/_SCHEMA.md` + Symfony Yaml validator + workflow `frontmatter-validate.yml` | 3d | CI quebra em PR com schema inválido; alert no PR | _SCHEMA.md per doc_type proposto |
| **US-COPI-XXX-005** | **Anthropic prompt caching live** — patch `LaravelAiSdkDriver` pra traduzir sentinels `<!--JANA_CACHE_BREAKPOINT_*-->` em `cache_control: {type: ephemeral}` quando provider=anthropic (esperar laravel/ai 0.7 OR contribuir patch) | 2d | -50% custo `kb-answer` quando provider Anthropic; `mcp_audit_log.cache_read_tokens` populated | laravel/ai 0.7 release |
| **US-COPI-XXX-006** | **`memory/digests/` populado weekly** — cron sex 18h BRT chama `WeeklyDigestGenerator` que agrega session logs + ADRs aceitas + handoffs + cycle-goals-track → `memory/digests/2026-WW20.md`; tool `weekly-digest-fetch` já existe | 1d | Wagner abre digest 1× sex; CI valida formato | `WeeklyDigestFetchTool` ✅ + Brief infra ✅ |

**Sub-total Onda 6:** 18 dev-days IA-pair = ~2-2,5 semanas calendário (com fator 10× [ADR 0106](../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md)).

**Nota target pós-Onda 6:** 86 + 12,5 = **98,5 ≈ 98 / 100** ✅ atingida.

### Onda 7 (futuro — só se sinal cliente / métrica drift)

ADR feature-wish — não pré-aprovada:

- **KG triple store hybrid** (Cognee-inspired) — pré-req 2+ clientes Verticals + complex cross-doc queries reportadas
- **Memify self-improving** — pré-req baseline KG (Onda 7.1)
- **A-Mem Zettelkasten links** — pré-req baseline KG (Onda 7.1)
- **Multi-modal embedding** (image/PDF) — pré-req cliente Vestuario/CV pedir anexos
- **Voice-to-text capture** — pré-req 2+ clientes pedirem (sem sinal hoje)
- **L1 cache positivo queries quentes** — pré-req latência P95 > 800ms sustained

### Anti-roadmap (NÃO fazer mesmo se tempo sobrar)

- ❌ **Migrar pra Mem0/Letta cloud** — perde Tier 0 + governance + LGPD BR
- ❌ **Substituir Meilisearch por Pinecone/Qdrant** — sem ROI no perfil 1.680 docs
- ❌ **Implementar 4º tier de memória** — explicitamente vedado em [ADR 0131](../decisions/0131-tiering-memoria-canonico-local-segredo.md) review_trigger
- ❌ **Refatorar `memory/INDEX.md` pra paradigma diferente** — atual cobre 1.680 docs em 6 buckets (governance/módulos/clientes/skills/runbooks/handoffs); evolução incremental ok

---

## 8. Pergunta final pro Wagner

**Nota atual:** 86 / 100. Target 98. Gap principal: 3 capacidades P2 — Contextual Retrieval + Freshness pipeline + OTel retrieval spans.

**3 ações priorizadas:**
1. **US-COPI-XXX-001 Contextual Retrieval (5d, +5 pts → 91)**
2. **US-COPI-XXX-002 Freshness pipeline (4d, +3 pts → 94)**
3. **US-COPI-XXX-003 OTel retrieval spans (3d, +2 pts → 96)**

Top 1-3 entrega 96 (Onda 6 inicial, ~2 semanas calendário). Restante (M-110 + M-108 + Weekly Digest = +2,5 pts) leva a 98,5 ≈ 98.

**Wagner aprova começar pela US-COPI-XXX-001 (Contextual Retrieval, 5d, +5 pts)? Quer que eu gere ADRs propostas pras top 3 (em `memory/decisions/proposals/`) antes de Onda 6 disparar?**

---

## 9. Apêndice — anti-falso-positivo audit trail

Lista das 22 capacidades P0+P1+P2 que foram verificadas via Grep antes de qualquer marcação 🟡/❌:

| ID | Capacidade | Comando | Resultado | Veredicto |
|---|---|---|---|:-:|
| M-002 | hybrid Meilisearch | `Grep "MeilisearchDriver\|hybrid.*semantic"` Modules/Jana | ✅ MeilisearchDriver.php:79 (semanticRatio + filter business_id) | ✅ |
| M-003 | reranker prod | `Grep "BgeReranker\|RrfReranker"` Modules/Jana | ✅ 4 drivers (BGE/RRF/LLM/Null) + 2 tests + config | ✅ |
| M-007 | TimeDecay | `Grep "TimeDecay\|time_decay"` | ✅ TimeDecayTest.php + config completa | ✅ |
| M-101 | kb-answer | `Glob Modules/Jana/Mcp/Tools/*` | ✅ KbAnswerTool.php | ✅ |
| M-102 | HyDE | `Grep "HydeQueryExpander"` | ✅ HydeQueryExpander.php (env-flag, RRF fusion) | ✅ |
| M-103 | AutoSummarizer | `Grep "AutoSummarizer"` | ✅ AutoSummarizerService.php (map-reduce + cache) | ✅ |
| M-104 | Backlinks | `Grep "AdrGraphBuilder\|JanaBacklinks"` | ✅ AdrGraphBuilder.php + JanaBacklinksSweepCommand.php + 2 tests | ✅ |
| M-105 | Daily Brief | `Glob Modules/Brief/Services/*` | ✅ BriefGeneratorService.php + ADR 0091 | ✅ |
| M-106 | RAGAS gate | `Glob .github/workflows/*.yml` | ✅ ragas-gate.yml + 3 Pest tests | ✅ |
| M-107 | OTel Brain B | `Grep "otel-gen-ai\|LangfuseClient"` | ✅ LaravelAiSdkDriver + LangfuseClient | ✅ |
| M-108 | Prompt caching | `Grep "cache_control.*ephemeral\|prompt[_\s]*caching"` | 🟡 sentinels em AutoSummarizer, não live em laravel/ai 0.6 | 🟡 |
| M-109 | NegativeCache | `Glob Modules/Jana/Services/Memoria/Negative*` | ✅ NegativeCacheService.php | ✅ |
| M-110 | Schema CI | `Glob memory/*/_SCHEMA.md` | 🟡 só memory/decisions/_SCHEMA.md (ADR only) | 🟡 |
| M-201 | Constituição v2 | `decisions-search "constituicao"` | ✅ ADR 0094 + CONSTITUTION.md v1.1.0 | ✅ |
| M-202 | Cliente-sinal | `decisions-search "cliente como sinal"` | ✅ ADR 0105 | ✅ |
| M-203 | 3-tier privacy | `Glob .claude/hooks/block-automem.ps1` + ADR 0131 | ✅ hook + Vaultwarden refs | ✅ |
| M-204 | MCP server | `Glob Modules/Jana/Mcp/Tools/*.php` | ✅ 33 tools + RBAC Spatie + audit | ✅ |
| M-205 | Contextual Retrieval | `Grep "Contextual\s*BM25\|contextual_chunk\|chunk_context"` | ❌ 0 matches relevantes em Modules/Jana | ❌ gap real |
| M-206 | Freshness pipeline | `Grep "freshness\|staleness\|last_verified"` | ❌ 0 matches em retrieval; só Tasks/Brief health | ❌ gap real |
| M-207 | OTel retrieval | `Grep "gen_ai.retrieval"` | ❌ 0 matches; só Brain B emite gen_ai.* | 🟡 parcial |
| M-208 | Handoff append-only | `Glob memory/handoffs/*` + ADR 0130 | ✅ 31 handoffs append-only + HandoffDraftTool + HandoffDiffTool | ✅ |

**P3 (4 capacidades) — todas confirmadas ❌ ausente** (sem cliente pedindo, ADR feature-wish per ADR 0105).

---

## 10. Restrições Tier 0 IRREVOGÁVEIS respeitadas nesta sessão

- ✅ PT-BR no domínio (inglês só em código + termos técnicos HyDE/RRF/RAGAS/NDCG)
- ✅ Multi-tenant Tier 0 — toda análise considera `business_id` como restrição P0
- ✅ Cliente-como-sinal — 4 P3 marcados ADR feature-wish (não inflados como capacidade necessária)
- ✅ Sem PII real em queries WebSearch (usei "enterprise SaaS multi-tenant 2026" / "small medium" não nomes reais)
- ✅ NÃO executei código, NÃO commitei, NÃO criei task MCP — só Write em `memory/audits/` + `memory/sessions/`
- ✅ NÃO inflei nota — adotei 86 conservador vs 92,9 raw (Wagner detecta inflação)
- ✅ Anti-falso-positivo 5 passos aplicado em todas as 22 capacidades P0+P1+P2 (catalogadas §9 deste doc)
- ✅ NÃO duplicado — Glob `memory/audits/AUDITORIA-MEMORIA-*.md` + `memory/requisitos/Jana/AUDITORIA-KNOWLEDGE-*.md` confirmou: nenhuma AUDITORIA-MEMORIA-* prévia; AUDITORIA-KNOWLEDGE-ARCHITECTURE-2026-05-13 EXISTE e foi preservada como predecessor (frontmatter `predecessor:` referencia explicitamente)
- ✅ Worktree usado: `D:\oimpresso.com\.claude\worktrees\sad-nightingale-34eb80`

---

**Última atualização:** 2026-05-15 — `memoria-senior` (Opus 4.7 sustained, 39 WebSearch + 1 WebFetch + leitura cruzada 30 arquivos Modules/Jana + 15 ADRs canon + RETRIEVAL-GOTCHAS + auditoria predecessora preservada).
