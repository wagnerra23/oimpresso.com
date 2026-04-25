# Glossário — LaravelAI

> Vocabulário de IA + grafo + RAG contextualizado pelo módulo.

## Conceitos básicos

- **Knowledge Graph** — grafo direcionado de entidades + relações (`kg_entities`, `kg_relations`)
- **Entity** — nó: user, role, permission, resource, ADR, invoice, contract
- **Relation** — aresta tipada: HAS_ROLE, CAN_ACCESS, GOVERNED_BY, CITED_IN
- **Triplet** — `(subject, predicate, object)` — "Alice HAS_ROLE Vendas"
- **Hop** — aresta percorrida em multi-hop query (`A → B → C` = 2 hops)

## RAG (Retrieval Augmented Generation)

- **RAG** — agente busca docs relevantes ANTES de gerar resposta (vs LLM puro que alucina)
- **Vector store** — armazenamento de embeddings (em LaravelAI: `kg_entities.embedding`)
- **Embedding** — vetor numérico que codifica significado semântico
- **Top-K search** — buscar K documentos mais similares por cosine distance
- **Cosine similarity** — métrica `[-1, 1]` de similaridade entre vetores
- **Citation** — ponteiro a fonte na resposta (`adr:0007`, `audit_log:1234`)

## LLM e providers

- **LLM** — Large Language Model (GPT-4, Claude, Llama, etc.)
- **Provider** — OpenAI, Anthropic, Cohere, etc.
- **Model** — modelo específico do provider (`gpt-4o-mini`, `claude-haiku-4-5`, `text-embedding-3-small`)
- **Token** — unidade de texto pro LLM (~0.75 palavra; cobrado por token)
- **Context window** — quantos tokens cabem no prompt+resposta (gpt-4o: 128k; haiku-4.5: 200k)
- **Hallucination** — IA inventando fatos; LaravelAI mitiga citando fonte SEMPRE
- **Grounding** — fornecer dados/contexto pra reduzir hallucination
- **Prompt injection** — ataque onde user injeta instruções pra subverter o agente
- **Jailbreak** — bypass de salvaguardas do LLM via prompt criativo

## Storage

- **pgvector** — extensão PostgreSQL pra armazenar/buscar vetores
- **HNSW** — algoritmo de índice vetorial (Hierarchical Navigable Small World)
- **JSON column** — alternativa em MySQL (sem pgvector); busca brute-force
- **Eloquent** — ORM Laravel; usado pra grafo MVP
- **Neo4j** — database graph nativo; potencial futuro pra produção
- **Cypher** — query language do Neo4j

## Operações de query

- **Multi-hop** — query atravessa N arestas (`A-[r1]->B-[r2]->C` = 2 hops)
- **Recursive WITH** — PostgreSQL feature pra recursão (multi-hop em SQL)
- **Path-finding** — encontrar caminho entre 2 entidades
- **Centrality** — métrica de importância de um nó (PageRank, betweenness, etc.) — futuro
- **Community detection** — agrupar nós relacionados — futuro

## Sync e cache

- **Observer** — listener Eloquent (created/updated/deleted)
- **Hash-based detection** — comparar hash atual vs persisted pra detectar mudança
- **Cron / Scheduled task** — job recorrente Laravel
- **Cache invalidation** — remover cache obsoleto após mutação

## Compliance

- **PII** — Personally Identifiable Information (CPF, e-mail, telefone)
- **PII masking** — substituir por placeholder (`***.***.***-**`)
- **LGPD** — Lei Geral de Proteção de Dados (Brasil)
- **Data residency** — onde os dados ficam fisicamente (preocupação multi-tenant)
- **Opt-out training** — config provider pra não usar dados em treinamento

## Quotas

- **Quota** — limite mensal de queries (1k Pro, 10k Enterprise)
- **Rate limit** — limite por minuto (anti-abuso, default 30/min)
- **Token budget** — limite de tokens consumidos por query (anti-runaway cost)

## UI

- **React Flow** — biblioteca pra grafo interativo
- **Recharts** — biblioteca de charts (timeline, gráficos)
- **Inertia v2** — Laravel + React full-stack pattern
- **shadcn/ui** — componentes React copiáveis (não dependency NPM)
- **Cytoscape** — alternativa rejeitada (UI-0001)

## Domínio oimpresso

- **MemCofre** — módulo de documentação (estendido por LaravelAI)
- **Spatie Permission** — sistema de roles + permissions
- **Activity Log** — audit log Spatie
- **business_id** — tenant scope
- **ADR** — Architecture Decision Record (markdown em `memory/requisitos/.../adr/`)

## Acrônimos

- **ANN** — Approximate Nearest Neighbor (search algorithm)
- **HNSW** — Hierarchical Navigable Small World
- **LLM** — Large Language Model
- **RAG** — Retrieval Augmented Generation
- **PII** — Personally Identifiable Information
- **NLP** — Natural Language Processing
- **TPS** — Tokens Per Second (LLM throughput)
