# 2026-05-16 — IA-MATURITY KB (Wave 22 agent)

**Branch:** `claude/governance-wave-21-22-mega`
**Worktree:** `D:\oimpresso.com\.claude\worktrees\jolly-hypatia-b8741c`
**Escopo exclusivo:** `memory/requisitos/KB/IA-MATURITY-FICHA.md` + este session log
**Áreas proibidas:** outros 11 agents Wave 22 (zero overlap)

## O que fiz

1. **Pesquisa estado-da-arte** (3 WebSearch):
   - Pinecone vs Weaviate vs Chroma 2026 (hybrid, latência, knowledge graph)
   - BGE reranker + Meilisearch + Ollama embedder best practices 2026
   - Mem0/Mem0g long-term memory + graph edges + Backstage TechDocs

2. **Inventário código KB atual** (Glob + Read):
   - 13 Entities (`KbNode`, `KbEdge`, `KbPath`, `KbDecisionTree`, etc) — grafo tipado completo
   - 5 Services (`KbArticleService`, `KbBridgeStateService`, `KbCorpusBuilder`, `KbEdgeAutoDeriver`, `KbRagService`)
   - Bridge `mcp_memory_documents` (352+ docs canon) preservado
   - ONDA 0+1+2+4+5(parcial) já em main via PR #934 (ADR 0150 aceita)

3. **FICHA escrita** (12 capacidades P0/P1, 6 sistemas comparados, 5 gaps priorizados):
   - Maturidade atual **56,3/100** — fundação canônica forte (hybrid Meili + grafo + bridge + citações) porém faltam camadas baseline 2026
   - Pós-fix estimado **~82/100** — líder categoria ERP+KB+IA+grafo BR

## Top 5 gaps priorizados

- **G1** Reranker BGE-v2-m3 no `KbRagService` (1-2d, +nDCG 15-25%)
- **G2** RAGAS eval suite pra KB (port pattern Wave 21 H4, 1d, mede baseline cego)
- **G3** Multi-modal embeddings Docling+vision (3-4d, alimenta catálogos Roland/Larissa)
- **G4** Drift detector "artigo KB desatualizado vs git" (2d, combate KB morto)
- **G5** Memory ops Mem0-style + temporal reasoning (3d, +37pp temporal queries per paper Mem0g)

## Diferenciais únicos confirmados

- **Grafo tipado nativo** (`kb_edges` 8 tipos) — Pinecone/Chroma não têm, Weaviate tem como bonus, Mem0g só com FalkorDB add-on. oimpresso já tem desde ONDA 1
- **Bridge git canon `mcp_memory_documents`** — citações com `git_sha` → GitHub URL preserva fotografia git (ADR 0061)
- **Integração ERP nativa** — kb_edges `references-data` cruza OS/cliente/NFe reais
- **Self-host completo** Ollama CT 100 + Meilisearch (zero SaaS crítico, respeita ADR 0062)

## Tier 0 respeitados

- ✅ Ollama embedder CT 100 (não SaaS) — todos gaps mantêm self-host
- ✅ Meilisearch hybrid canônico — reranker é POST, não substituto
- ✅ Multi-tenant `business_id` global scope
- ✅ Custo IA tracking declarado (G2, G5)
- ✅ Mock mode pra tudo que chama LLM
- ✅ PT-BR
- ✅ Zero git ops (parent consolida)
- ✅ Zero overlap com outros 11 agents Wave 22

## Arquivos

- `memory/requisitos/KB/IA-MATURITY-FICHA.md` (criado — ~160 linhas)
- `memory/sessions/2026-05-16-ia-maturity-kb-wave22.md` (este)

## Sources

Ver seção final da FICHA (7 URLs WebSearch).
