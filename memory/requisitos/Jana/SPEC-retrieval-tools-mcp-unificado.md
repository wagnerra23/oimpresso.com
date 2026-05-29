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

## ✅ ATIVADO EM PROD (2026-05-29) + findings live

Deployado no `oimpresso-mcp` (CT 100) — ver [INFRA-ACESSO-CANON](../../reference/INFRA-ACESSO-CANON.md). Container estava 1302 commits atrás; deploy destravado pelo fix do Dockerfile (exts).

- ✅ **`decisions-search` + `kb-answer` HYBRID LIVE** (`JANA_MCP_SEARCH_PIPELINE_DOCS=true`). Smoke real no app: `buscarHybrid('isolamento multi-tenant', 4, null, 'adr')` → `0006 · 0218 · 0093 · ARQ-0001` (semântico). Embedder = `qwen3_local` (config `copiloto.mcp_search.docs_embedder` — NÃO o `openai` do chat).
- ⏸️ **`memoria-search` OFF** (`JANA_MCP_SEARCH_PIPELINE_MEMORIA=false`). Bloqueado: o índice **`jana_memoria_facts` tem embedders `{}` (vazio)** — sem embedder configurado, hybrid impossível. Precisa configurar embedder + reindex (tarefa infra).
- 🔴 **FINDING separado (chat):** como `jana_memoria_facts` não tem embedder, **o recall semântico do CHAT também está degradado (keyword-only)** apesar de `COPILOTO_MEMORIA_EMBEDDER=openai` no .env. O índice precisa do embedder configurado pra o chat ter hybrid de verdade. Abrir issue/ADR próprio.

## ⚠️ Dois corpora distintos (Wagner, 2026-05-29 — NÃO conflar)

| | **Corpus MCP** (`mcp_memory_documents`) | **Corpus Jana** (`jana_memoria_facts`) |
|---|---|---|
| O que é | Conhecimento de **programação/produto** (ADRs/SPECs/sessions/handoffs) — fonte única do git, indexado sob `business_id=1` (default do `IndexarMemoryGitParaDb`) | **Dados do CLIENTE** (fatos/metas/conversas) |
| Tenancy | **GLOBAL — serve TODOS os clientes/devs.** NÃO é per-cliente. **Sem filtro de tenant.** | **Per-cliente: `business_id`-scoped** (NÃO `user_id`) |
| Tools | `decisions-search`, `kb-answer` | `memoria-search` |
| Consumidor | Time de dev (Wagner/Felipe/…) via Claude Code | A própria Jana (chat do cliente no produto) |

**MCP ≠ Jana.** O MCP (programação) atende todos os clientes; a Jana é restrita por `business_id` e serve um cliente. Já confundi isso 2× (chat em #1922, business_id no corpus MCP em #1928 — ambos revertidos).

## Não-objetivos (o que NÃO fazer)

- ❌ **NÃO** colocar filtro `business_id` no corpus MCP (`mcp_memory_documents`). É conhecimento global de programação. **#1928 foi revertido por isso.**
- ❌ **NÃO** reusar `MemoriaContrato::buscar(business, **user**)` do chat — filtra `user_id`; a memória da Jana é por `business`, não por usuário. **#1922 fechado por isso.**
- ❌ **NÃO** ligar default sem validar recall@5 com golden set.
- ❌ **NÃO** ir pra GraphRAG/entity-linking agora (consenso 2026: só ao saturar hybrid; não saturamos).

## Estado real (corrigido)

Os **dois** corpora **já têm Scout/Meilisearch hybrid + embedder** (ADR 0068 pro MCP; chat pro Jana). **Não falta indexação.** O que falta é só **rotear as tools** (que ainda usam FULLTEXT/`buscarTexto`) pelo Scout hybrid. Sem reindex, sem campo novo.

## User stories (recalibradas ADR 0106)

### US-RET-001 — Rotear `decisions-search` + `kb-answer` (corpus MCP, GLOBAL) · P0 · ✅ FEITO
- ✅ `McpMemoryDocument::buscarHybrid(query, limit, user, tipo?, module?)` — Scout hybrid (embedder `qwen3_local` + semanticRatio do config). **Verificado LIVE no índice CT 100**: embedder qwen3_local, filterableAttributes `[status,type,module,slug]` (SEM business_id → corpus global), 1048 docs 100% embedded; busca semântica "isolamento multi-tenant" retornou ADR 0006/0093/0218/ARQ-0001.
- ✅ **SEM filtro de tenant** — só status ativo + type/module (Meilisearch) + `acessiveisPara` (permissão Spatie) na hidratação.
- ✅ `decisions-search` + `kb-answer::buscarFontes` roteados atrás de `JANA_MCP_SEARCH_PIPELINE_DOCS` (default OFF) + fallback gracioso pro `buscarTexto` (erro/vazio). archived continua FULLTEXT (índice não tem superseded).
- ✅ Testes: smoke buscarHybrid + KbAnswerToolTest (8, flag OFF = byte-a-byte). PHPStan limpo.
- ⏳ **Pendente:** ligar `JANA_MCP_SEARCH_PIPELINE_DOCS=true` em prod + validar recall (US-RET-003).

### US-RET-002 — Rotear `memoria-search` (corpus Jana, per-cliente) · P1 · ✅ FEITO
- ✅ `MeilisearchDriver::buscarBusiness(biz, query, topK)` — variante **`business_id`-scoped** (`userId` nullable em `buscarInterno`; com userId≠null o chat fica byte-idêntico — 29 testes do pipeline verdes). NÃO reusa `buscar(business,user)`.
- ✅ `memoria-search` roteia por ela atrás de `JANA_MCP_SEARCH_PIPELINE_MEMORIA` (default OFF) + fallback FULLTEXT (erro/vazio/driver-incompatível). 4 testes (formata · vazio→null · erro→null · guard instanceof). PHPStan limpo.
- ⏳ **Pendente:** ligar a flag em prod + validar recall (US-RET-003). Meilisearch do `jana_memoria_facts` já roda (chat usa) — sem dependência de CT 100.

### US-RET-003 — Gate golden-set recall@5 antes de default-ON · P1 · ~3h
- Golden set ~20 queries reais; `copiloto:eval` compara ON vs FULLTEXT; só vira default se não regredir.
- **Aceite:** recall@5 ON ≥ baseline; Wagner aprova flip.

## Sequência

```
US-RET-001 (decisions-search + kb-answer, sem tenant) ──┐
US-RET-002 (memoria-search, business-scoped) ───────────┼─→ US-RET-003 (golden set) → Wagner liga default
```

## Multi-tenant (Tier 0)

- Corpus **MCP** (decisions-search/kb-answer): **sem `business_id`** — global. Só `acessiveisPara` (permissão) + `porStatusAtivo`.
- Corpus **Jana** (memoria-search): `business_id`-scoped (sem `user_id`). Pest cross-tenant obrigatório.

## Esforço total

~10-15h IA-pair (ADR 0106). Infra R$0 (Meilisearch já roda); custo LLM marginal (embeddings backfill ~1×, eval daily).

## Métrica de sucesso

recall@5 das tools MCP ≥ recall do chat (hoje 0.84) · zero regressão cross-tenant · `kb-answer` deixa de sintetizar sobre recall FULLTEXT.
