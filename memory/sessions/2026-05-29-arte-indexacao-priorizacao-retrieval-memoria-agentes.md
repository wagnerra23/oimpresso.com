---
tipo: session
slug: 2026-05-29-arte-indexacao-priorizacao-retrieval-memoria-agentes
data: 2026-05-29
autor: agente estado-da-arte (Wagner)
tema: Indexação, priorização e retrieval de conhecimento/memória para agentes de IA (2026) — gap analysis oimpresso
relevancia_meta: 70
---

# Estado-da-arte: indexação · priorização · retrieval de memória pra agentes (2026)

> Pesquisa limpa (Fase 1) feita ANTES de ler qualquer código oimpresso. Comparação (Fase 2) e avaliação (Fase 3) depois. Consultor sênior, sem inflar.

---

## 1. Como os líderes 2026 resolvem (com fontes)

### Players de referência

| Player | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Letta (MemGPT)** | Hierarquia de memória 3-tier inspirada em SO: **Core** (sempre no contexto, RAM — perfil/estado de tarefa, agente edita via `core_memory_append/replace`), **Recall** (histórico de conversa fora do contexto, buscável), **Archival** (vector store externo via `archival_memory_search`). TTL por bloco de memória pra prune automático. | Definiu o vocabulário de tiering de memória que todo mundo cita. Core/Recall/Archival é o modelo mental canônico. |
| **Zep / Graphiti** | Knowledge graph **temporal bi-temporal**: rastreia 4 timestamps (quando o fato ocorreu, quando foi ingerido, e a janela de validade — quando o fato virou inválido). Fatos não são deletados, são **invalidados** (valid_until). Integra dados conversacionais + estruturados de negócio com proveniência por fato. | Bate MemGPT no DMR (94.8% vs 93.4%); +18.5% no LongMemEval com **-90% latência**. Graphiti MCP Server v1.0 (nov/2025). O modelo bi-temporal é o estado-da-arte de "fato que envelhece". |
| **Mem0** | Retrieval **multi-signal**: 3 passes em paralelo (semântica + keyword + entity matching) fundidos. Abandonou graph store externo por **entity linking embutido na extração** (entidades em coleções paralelas como boost de ranking, não traversal). Ranking implícito: user-memory > session > raw history. | LoCoMo 92.5 a ~6.900 tokens/query, +29.6 em queries temporais. Paper ECAI 2025. Pragmático: prova que graph traversal puro raramente vale o custo. |
| **Cognee** | Pipeline `cognify` (6 estágios: classify → permissões → chunk → extrair triplas SRO → summaries → embed+grafo). Operação **`memify`**: poda nós stale, **re-pondera arestas por frequência de uso**, deriva fatos novos. Índice **auto-melhorável** + ontologias auto-geradas. | Live em 70+ empresas (Bayer, U. Wyoming). É o estado-da-arte de **auto-manutenção do índice** (self-improving graph). |
| **Anthropic Contextual Retrieval** | Pré-pende contexto gerado por LLM a cada chunk antes de embeddar. **Hybrid (dense + contextual BM25) + RRF + rerank**. | Reduz falha de retrieval top-20 em **67%** (5.7%→1.9%) com hybrid+rerank. É o baseline de retrieval que toda RAG séria 2026 copia. |

### Padrões transversais (context engineering 2026)

- **System-prompt tiering**: o que é *sempre relevante* (terminologia de domínio, convenções org, formato de saída, regras canônicas) vive no system prompt / core memory — **always-on**, não recuperado. O resto é **just-in-time retrieval**. Embutir dado dinâmico no system prompt "fica stale e incha o cache" — anti-padrão explícito.
- **Retrieval SOTA = pipeline multi-estágio**: dense+BM25 → RRF → rerank (Cohere Rerank 3.5 / cross-encoder / ColBERT late-interaction). Hybrid+Cohere domina single-stage por larga margem (+17pp Recall@5). ColBERT é o meio-termo speed/precision pra reranquear 100-500 candidatos.
- **Staleness/decay**: TTL + decay funcionam pra memórias de baixa relevância. **Staleness em memória de alta relevância (fato confiante mas desatualizado) é problema aberto declarado** (Mem0, 2026). A resposta de Zep é o modelo bi-temporal (invalidar, não decair).
- **Drift detection**: comparar estado atual da fonte vs snapshot baseline; flag quando diverge; alerta proativo quando a fonte não atualiza dentro da janela de frescura.
- **GraphRAG**: poderoso pra queries globais/"sense-making", mas indexação custa 10-100x vector RAG e **summaries não atualizam sozinhos** (re-rodar community detection é fardo de manutenção). LazyGraphRAG (jun/2025) difere isso pra query-time a 0.1% do custo. Recomendação consensual: **começar com RAG hybrid, ir pra GraphRAG só quando bater no teto**.

**Fontes:** [Letta agent memory](https://www.letta.com/blog/agent-memory) · [Zep arXiv 2501.13956](https://arxiv.org/abs/2501.13956) · [Graphiti](https://github.com/getzep/graphiti) · [Mem0 State of Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026) · [Anthropic Contextual Retrieval (Claude Cookbook)](https://platform.claude.com/cookbook/capabilities-contextual-embeddings-guide) · [Cohere Rerank 3.5 + OpenSearch (AWS)](https://aws.amazon.com/blogs/big-data/enhancing-search-relevancy-with-cohere-rerank-3-5-and-amazon-opensearch-service/) · [Cognee self-improving memory (Memgraph)](https://memgraph.com/blog/from-rag-to-graphs-cognee-ai-memory) · [Context Engineering 2025 (Mem0)](https://mem0.ai/blog/context-engineering-ai-agents-guide) · [GraphRAG production tradeoffs](https://tianpan.co/blog/2026-04-09-graphrag-production-when-vector-search-hits-ceiling) · [ColBERT vs cross-encoder](https://www.emergentmind.com/topics/colbertv2-retriever)

---

## 2. Gap analysis — oimpresso vs estado-da-arte, por dimensão

Código real lido: `MeilisearchDriver.php`, `DecisionsSearchTool.php`, `MemoriaSearchTool.php`, `KbAnswerTool.php`, `StalenessDetectorService.php`, `FreshnessCheckCommand.php`, `memory/INDEX.md`.

| Dimensão | Estado-da-arte (Fase 1) | Estado oimpresso hoje | Distância |
|---|---|---|---|
| **Retrieval (chat)** | hybrid dense+BM25 → RRF → rerank → decay | `MeilisearchDriver`: hybrid (semantic_ratio 0.7) + HyDE + RRF (k=60) + time-decay (half-life por doc_type) + Peso Real (ADR 0232, flag OFF) + reranker (rrf/llm). **Top de linha.** | **curta** (no chat) |
| **Retrieval (tools MCP do time)** | mesmo pipeline pra todo consumidor | `decisions-search`/`memoria-search`/`kb-answer` usam **MySQL FULLTEXT puro** (`MATCH AGAINST` / `buscarTexto`). Zero semântica, zero rerank, zero decay. **kb-answer faz síntese LLM por cima de recall FULLTEXT** — bom andar de cima sobre fundação fraca. | **longa** |
| **Indexação (corpus do time)** | embeddings + (opcional) entidades/grafo | `mcp_memory_documents` sincronizado git→DB via webhook (ADR 0053). FULLTEXT index. **Sem embeddings nesse corpus** — embeddings só existem no índice Meilisearch dos `jana_memoria_facts` (chat). Dois mundos separados. | **média-longa** |
| **Priorização / context-eng (canônico/supremo)** | system-prompt tiering: canônico always-on, resto JIT | `CLAUDE.md` + `@imports` + skills Tier A (always-on via hook) = **tiering forte e explícito**. Constituição como LEI MÁXIMA. Peso Real (ADR 0232) modela "decisão é evergreen, memória decai" — **conceitualmente à frente do mercado**. MAS: o `memory/INDEX.md` teve regressão — docs Tier 0 normativos (Constituição/NORTE-ROI/Protocolo) ficavam enterrados; corrigido manualmente 2026-05-29. | **curta no design, média na execução** |
| **Staleness / decay** | TTL + decay (baixa relev.); bi-temporal pra alta relev. (problema aberto) | **Dois mecanismos**: (a) query-time `applyTimeDecay` (half-life adr=365/spec=180/session=30/handoff=14 + status multipliers); (b) index-time `FreshnessCheck` (FRESH/WARM/STALE/CRITICAL + drift git↔DB + alerta idempotente + dispatch re-index). `valid_from/valid_until` = **bi-temporal-lite** (supersede). | **curta** — surpreendentemente forte |
| **Drift detection** | snapshot baseline vs estado atual + alerta | `StalenessDetectorService::detectDrift`: tipo A (`updated_at > indexed_at`) + tipo B (`git_sha != HEAD git`). Cron 04:30. **Existe e funciona** (bugs git-SHA e cutoffs corrigidos no commit de hoje). | **curta** |
| **Auto-manutenção do índice** | Cognee `memify`: poda nós stale, re-pondera por uso, deriva fatos; índices auto-gerados | `module:specs` auto-gera 44 specs de módulo. MAS o **`memory/INDEX.md` mestre é mantido à mão** — contagens stale (rodapé admite "contagens não têm regen automático, drift volta"), priorização Tier 0 regrediu silenciosamente. Re-ponderação de arestas por uso de retrieval: **não existe** (há `HitTrackerService` mas não realimenta ranking do corpus do time). | **longa** |

---

## 3. Top gaps rankeados — CONSOLIDAR vs EVOLUIR

### CONSOLIDAR (já está bom ou acima do mercado — NÃO mexer)

- **Pipeline de retrieval do chat** (`MeilisearchDriver`): hybrid+HyDE+RRF+decay+rerank é estado-da-arte. Não tocar sem sinal de métrica.
- **Freshness pipeline index-time** (4 níveis + drift git↔DB + alerta idempotente): cobre exatamente o que Mem0/Cognee descrevem como drift detection. Já bate o mercado open. Acabou de ter bugs corrigidos — deixar estabilizar.
- **Peso Real (ADR 0232)**: a distinção "decisão é evergreen (não decai por tempo, só por supersede) vs memória decai" é **conceitualmente à frente** do que Mem0/Zep publicam. Manter a flag em rollout consciente, não reescrever.
- **System-prompt tiering** (CLAUDE.md + skills Tier A always-on): exatamente o padrão que a literatura 2026 prega. Consolidado.

### EVOLUIR (rankeado por impacto × esforço)

| # | Gap | Impacto | Esforço (IA-pair, ADR 0106 ~10x) | Pré-req? |
|---|---|---|---|---|
| 1 | **Tools MCP do time usam FULLTEXT puro** enquanto o chat tem pipeline rico. `decisions-search`/`memoria-search`/`kb-answer` deveriam recuperar pelo mesmo `MeilisearchDriver` (hybrid+rerank+decay). É o maior gap de qualidade de recall que o time sente todo dia. | **alto** | **~2-4h IA-pair** (`kb-answer` já tem fase de retrieval isolada em `buscarFontes` — trocar o motor; `mcp_memory_documents` precisa de embedder no índice Scout) | Embedder no índice do corpus do time (ver #2) |
| 2 | **`mcp_memory_documents` sem embeddings** — indexação só FULLTEXT. Sem isso, #1 não tem semântica pra recuperar. | **alto** | **~3-5h IA-pair** (configurar embedder OpenAI text-embedding-3-small no índice Scout do corpus do time; backfill) | Nenhum bloqueante (binário Meilisearch já existe) |
| 3 | **`memory/INDEX.md` sem regeneração automática** — contagens stale, priorização Tier 0 regrediu silenciosamente. Estado-da-arte (Cognee) auto-gera/auto-mantém índice. Mínimo: comando que regenera contagens + valida que docs Tier 0 normativos estão no topo, falhando CI se sumirem. | **médio-alto** | **~2-3h IA-pair** (comando `jana:index-regen` + Pest "Tier 0 docs presentes no INDEX") | Nenhum |
| 4 | **HitTracker não realimenta ranking do corpus do time** — Cognee re-pondera arestas por frequência de uso. oimpresso loga hits mas não usa como sinal de relevância no recall do time. | **médio** | **~3-4h IA-pair** | Depende de #1+#2 (precisa do pipeline unificado primeiro) |
| 5 | **GraphRAG / entity linking** — não existe. Honestamente: **NÃO é gap urgente.** Consenso 2026 é só ir pra grafo ao bater no teto do hybrid. oimpresso não bateu. Anotar como "quando recall hybrid saturar". | **baixo (hoje)** | alto | Bloqueado por sinal de necessidade |

---

## 4. Surpresa estratégica

**oimpresso está CRITICAMENTE ATRÁS de si mesmo, não do mercado.** A surpresa não é uma tecnologia que falta — é uma **assimetria interna**: o time construiu um pipeline de retrieval que bate o estado-da-arte open-source (hybrid+HyDE+RRF+decay+rerank+Peso Real), e então **deixou as tools que o próprio time usa todo dia rodando em MySQL FULLTEXT cru** — tecnologia de 2010. O `kb-answer` é o caso mais doloroso: gasta um LLM sintetizando resposta sobre um recall FULLTEXT que pode nem ter trazido o doc certo (garbage-in). É síntese de luxo sobre fundação de tijolo.

Onde está **acima** do mercado: o Peso Real (ADR 0232) resolve, por design, exatamente o "staleness em memória de alta relevância" que a Mem0 declara como **problema aberto não resolvido** em 2026 — ao separar a natureza temporal de decisão (evergreen) vs memória (decai). Isso é publicável. O risco é morrer em flag OFF sem nunca ser validado.

---

## Recomendação final

**Comece pelo #2 → #1 (mesma frente).** Alto-impacto, esforço médio, sem pré-req bloqueante (binário Meilisearch já roda). É a correção da assimetria interna: dar ao corpus do time (`mcp_memory_documents`) o mesmo motor que o chat já tem. Resolve a maior dor diária de recall e desbloqueia #4.

**Próxima ação hoje:** abrir SPEC (recalibrado ADR 0106) pra "unificar retrieval das tools MCP no MeilisearchDriver" — passo 1 = configurar embedder OpenAI `text-embedding-3-small` no índice Scout de `mcp_memory_documents` + backfill; passo 2 = trocar `buscarFontes` do `kb-answer` (e o `buscarTexto` de `decisions-search`/`memoria-search`) pra rotear pelo pipeline hybrid. Multi-tenant Tier 0: o filtro `business_id`/`acessiveisPara` JÁ existe nas tools — preservar byte-a-byte ao trocar o motor (gap que vaze tenant = P0).
