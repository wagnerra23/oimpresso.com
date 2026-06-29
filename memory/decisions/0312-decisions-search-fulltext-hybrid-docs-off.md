---
slug: 0312-decisions-search-fulltext-hybrid-docs-off
number: 312
title: "decisions-search volta ao FULLTEXT — hybrid de docs desligado (embedder qwen3 exige instrução de query)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-06-29"
module: jana
tags: [mcp, busca, retrieval, embedder, meilisearch, fulltext, hybrid, qwen3, nomic, decisions-search, claude-4-8]
supersedes: []
superseded_by: []
related:
  - 0068-sprint9-retrieval-ollama-reranker-strategy
  - 0053-mcp-server-governanca-como-produto
  - 0091-daily-brief
---

# ADR 0312 — decisions-search volta ao FULLTEXT (hybrid de docs desligado)

**Status:** ✅ Aceito em 2026-06-29 por Wagner ("pode fazer").
**Não supersede** a [ADR 0068](0068-sprint9-retrieval-ollama-reranker-strategy.md) — é **desativação operacional reversível** do pipeline hybrid **apenas para docs/ADRs**.

---

## Contexto

A tool MCP `decisions-search` (e `kb-answer`) usava o pipeline **hybrid** (Meilisearch semântico + lexical) atrás da flag `JANA_MCP_SEARCH_PIPELINE_DOCS` (ligada em prod via [ADR 0068](0068-sprint9-retrieval-ollama-reranker-strategy.md) / US-RET-001), com fallback FULLTEXT MySQL. Medição em 2026-06-29 mostrou que a busca retornava **lixo** em casos triviais — ex: "daily brief estado consolidado do projeto" devolvia `recharts`, `agents`, `portal-repair` e **não** a ADR 0091 (Daily Brief).

## Causa-raiz (medida, não inferida)

O embedder de busca é `qwen3-embedding:0.6b` (config `copiloto.mcp_search.docs_embedder`), com `semanticRatio = 0.6`. Medições no índice/embedder real (CT 100):

1. **`qwen3-embedding` exige prompt de instrução na query** (`Instruct: …\nQuery: …`); o Meilisearch (source `ollama`) envia a query **raw**, então o vetor cai no espaço errado e a **similaridade inverte**:
   - query raw → `cos(query, 0091) = 0.5306` **< ** `cos(query, lixo recharts) = 0.7068` (errado);
   - query com instrução → `0.4788 > 0.4286` (a ordem corrige).
2. **Trocar o embedder para `nomic` NÃO resolve** — na busca real do Meilisearch o nomic recupera a 0091 mas **quebra** outros casos (ex "Centrifugo"/0058 some). Troca quais casos falham, não melhora no geral.
3. **`documentTemplate` é pobre** — `{{doc.title}}. {{doc.content_excerpt}}` truncado a 400 bytes, e `content_excerpt` na prática **só repete o título**.
4. **O FULLTEXT MySQL mede melhor** que ambos os hybrids nesse corpus (≈6/7 vs ≈4/7 dos alvos no top-3). ADRs são docs curtos, vocabulário técnico PT/EN, nomes/slugs — onde o **lexical bate o semântico** (modelos de embedding pequenos não capturam o jargão de nicho).

## Decisão

**Desligar `JANA_MCP_SEARCH_PIPELINE_DOCS` (`=false`)** no `.env` do `oimpresso-mcp` (CT 100) → `decisions-search`/`kb-answer` usam o **FULLTEXT MySQL**. A flag `JANA_MCP_SEARCH_PIPELINE_MEMORIA` (busca de memória/chat) **permanece ligada** — fora do escopo desta medição.

Reversível: religar a flag volta o hybrid. Índice Meilisearch e os dois embedders (`nomic_local`, `qwen3_local`) **permanecem intactos** — nada é apagado.

## Evidência A/B (smoke prod 2026-06-29)

| Query | ANTES (hybrid/qwen3) | DEPOIS (fulltext) |
|---|---|---|
| daily brief | recharts, agents, portal (lixo) | **0091 (r1)**, 0097, 0226 |
| multi-tenant | 0093 ausente do top-3 | **0093 (r2)** |
| Centrifugo | 0058 (r1) | **0058 (r1)** — sem regressão |

## Consequências

- **Positivo:** recall consistente e imediato na busca de ADR/docs do time MCP. Zero custo de embedder/latência semântica nesse caminho.
- **Trade-off:** perde-se o (hipotético) ganho semântico em queries conceituais sem overlap léxico — mas a medição mostrou que o semântico atual **piora**, não melhora.
- **kb-answer** (mesmo `buscarHybrid` via flag de docs) também volta ao FULLTEXT.
- **Não resolvido por esta ADR:** casos canon-vs-canon (ex 0035, 0093) onde ADRs legítimas competem — alavanca é **curadoria** via campo `summary` no frontmatter (infra entregue no PR #3383, frente 1).

## Condição de reativação (reabrir o hybrid)

Religar a flag **só** quando o hybrid bem-feito superar o FULLTEXT num gold-set honesto, exigindo: (a) **instrução de query** no embedder (REST embedder com template, ou pré-processamento), (b) **`documentTemplate` real** (conteúdo da decisão, não só o título), (c) **reindex**, (d) **re-tuning do `semanticRatio`** (0.6 dá peso demais ao semântico). Até lá, FULLTEXT é o caminho canônico de docs.
