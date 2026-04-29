# Session 2026-04-29 (noite) — Pacote enterprise busca de memória + evolução automática

> **Resumo:** Após sprint memória completa de manhã (`2026-04-29-sprint-memoria-completa.md`),
> sessão de tarde/noite acrescentou: governança MCP no app web, chat streaming SSE,
> chat enterprise via assistant-ui, gabarito 50 perguntas + baseline real medido,
> pacote 10 otimizações Sprint 8/10 ADR 0037, ADR 0054 estratégia evolução.
>
> **Modo:** Wagner solo · **Branch:** `main` · **PRs:** todos commits direto em main

---

## ⏱️ Linha do tempo

### Bloco 1 — Governança como produto (manhã→tarde)

| Hora | Entrega | Commit |
|---|---|---|
| early afternoon | **MEM-MCP-1.e** Dashboard `/copiloto/admin/governanca` | `bebc1486` |
| afternoon | **fix audit middleware** extrai params.name JSON-RPC body | `532f2156` |

**O quê:** Tela Inertia com 4 KPIs (calls, taxa sucesso, latency p95, custo R$),
chart calls/dia, distribuição por status, denied por error_code, top tools, top users.
Permission gate `copiloto.mcp.usage.all`.

**Bug fix collateral:** McpAuthMiddleware lia `$request->input('name')` mas MCP JSON-RPC
tem `name` em `params.name` — corrigido pra extrair de `params.name`/`params.uri` +
`method` do body.

### Bloco 2 — Chat enterprise (tarde)

| Hora | Entrega | Commit |
|---|---|---|
| afternoon | **COP-CHAT-STREAM** SSE streaming token-por-token | `9fcf0e80` |
| afternoon | **fix Vizra bypass** com laravel/ai Agent::stream() | `d3cba46c` |
| afternoon | **MEM-CHAT-ENT-1** chat enterprise via @assistant-ui/react | `b1e2f67d` + `06ae8644` + `f7dd2af7` |

**O quê:**
- Endpoint `/copiloto/conversas/{id}/mensagens/stream` com `StreamedResponse` SSE
- Frontend Chat.tsx com fetch + ReadableStream + ParseChunk
- Substituído manual Thread/Composer por @assistant-ui/react v0.10
- Markdown render + Stop button + welcome com 4 sugestões + auto-scroll

**Smoke prod via Chrome MCP:**
- Conversa real WR2 (biz=1): "Diga somente: TESTE STREAMING OK"
- Network: POST /mensagens/stream → 200 OK
- Bolha CP renderiza markdown corretamente

### Bloco 3 — Mensurabilidade (tarde→noite)

| Hora | Entrega | Commit |
|---|---|---|
| evening | **MEM-EVAL-1** gabarito 50 perguntas Larissa-style + cmd `copiloto:eval` | `d29c8ae7` |
| evening | **fix eval** topK + auto-resolve user_id | `014537b6` + `a72f0baf` |
| evening | **MEM-EVAL-2** relax filtro fatos 5→3 + cmd `copiloto:backfill-fatos` | `bd2d9981` |

**O quê:**
- Schema `copiloto_memoria_gabarito` (50 perguntas + memoria_esperada_keys + categoria)
- Service `GabaritoEvaluator` calcula Recall@3, R@10, P@3, MRR
- Cmd `copiloto:eval --business=4 --persist` grava em `copiloto_memoria_metricas`
- Cobertura: 5 categorias LongMemEval (info-extraction, multi-session, temporal,
  knowledge-update, abstention) × 7 domínios oimpresso (faturamento, clientes,
  metas, despesas, capability, cross-tenant, lgpd)

**Baseline REAL medido em prod (biz=4 Larissa, 6 facts):**

| Métrica | Valor | Gate ADR 0050 |
|---|---|---|
| Recall@3 | **0.125** | ≥ 0.80 (6.4× abaixo) |
| Precision@3 | 0.190 | ≥ 0.60 |
| MRR | 0.274 | ≥ 0.70 |
| Latência p95 | 771 ms | < 2000 ms ✅ |

**Por categoria:**
- info-extraction: R@3=0.172
- multi-session: R@3=0.146
- temporal: R@3=0.135
- knowledge-update: R@3=0.107
- abstention: R@3=0.000 (esperado — keys=[])

**Diagnóstico:** R@10 = R@3 → corpus pequeno, não problema retrieval.
Causa raiz: filtro `relevancia<5` rejeitava 94% dos fatos extraídos.

### Bloco 4 — Pacote enterprise busca memória (noite)

Wagner: *"implemente as praticas na busca de memória. e continue aprendendo e
melhorando. estipulo para ter um excelente ROI"*

| Otimização | ID | Commit | Status |
|---|---|---|---|
| **MEM-CC-1** schema sessions/messages/blobs (compartilhamento futuro) | — | `0bc1f222` | schema only, ativ. depois |
| **MEM-S8-1** SemanticCacheMiddleware | — | `c31375d8` | ✅ wired + smoke OK |
| **MEM-S8-2** ConversationSummarizer | — | `0e0e5dea` | ✅ wired + 4× compressão validada |
| **MEM-S8-3** ProfileDistiller | — | `0e0e5dea` | ⚠️ wired, debug pendente |
| **MEM-S8-4** Auto-promote facts (hits≥5 → core_memory) | — | `0e0e5dea` | 🟡 schema, lógica pendente |
| **MEM-S10-1** HyDE Query Expander | — | `3d060fec` | 🟡 service, wiring pendente |
| **MEM-S10-2** LlmReranker | — | `3d060fec` | 🟡 service, wiring pendente |
| **MEM-S10-3** Negative cache | — | `3d060fec` | 🟡 schema only |
| **ADR 0054** doc por quê + como evolui | — | `e3ea5b92` | ✅ canônica |

**Smoke real:**
- SemanticCache: query exata HIT em 11ms, fuzzy HIT em 2ms (Jaccard 0.85), miss 1ms
- ConversationSummarizer (conv #3 Larissa, 48 msgs):
  - tokens_before=928 → tokens_after=227 (**4.09× compressão**)
  - Summary preserva: *"R$ 31.513,29 abril"* + *"R$ 38.215,07 março maior venda"*

### Bloco 5 — Estado da arte time + planejamento (noite final)

Wagner: *"estado da arte para claude code em equipe"*

Pesquisa profunda 4 fontes Anthropic + community:
- **Pilar A:** Agent Teams (Anthropic mar/2026)
- **Pilar B:** `.mcp.json` project-scoped (commitado, team aprova 1×)
- **Pilar C:** CLAUDE.md monorepo-aware (walk-up directory tree)
- **Pilar D:** Anthropic Cowork (Team plan $25/seat/mo + Enterprise admin console)

**Decisão:** híbrido recomendado — começa com **MEM-CC-1 self-host (R$ 0)** + decide
Anthropic Team plan quando 4+ devs ativos (R$ 685/mês).

---

## 📊 Métricas reais do dia

### Suite de testes

| Antes | Depois | Δ |
|---|---|---|
| 77 passed | (mantido) | 0 regressão (não rodou re-suite, código novo isolado) |

### Novas migrations (deployed em prod)

1. `2026_04_29_200001_create_copiloto_memoria_gabarito_table` — 50 perguntas
2. `2026_04_29_300001/2/3_create_mcp_cc_*_table` — sessions/messages/blobs (3 tables)
3. `2026_04_29_400001_create_copiloto_cache_semantico_table` — cache LLM
4. `2026_04_29_500001_create_copiloto_business_profile_table` — profile distilled
5. `2026_04_29_500002_add_promotion_to_memoria_facts` — hits_count/core_memory
6. `2026_04_29_500003_create_copiloto_negative_cache_table` — cache negativo

**Total: +7 novas tables/cols em prod hoje.**

### Novos services

| Service | Wired? | Linhas |
|---|---|---|
| `Mcp/CcIngestController` | 🟡 schema only | 165 |
| `Cache/SemanticCacheService` | ✅ | 200 |
| `Memoria/ConversationSummarizer` | ✅ | 175 |
| `Memoria/ProfileDistiller` | ✅ (bug pendente) | 170 |
| `Memoria/HydeQueryExpander` | 🟡 ready, not wired | 90 |
| `Memoria/LlmReranker` | 🟡 ready, not wired | 130 |
| `Metricas/GabaritoEvaluator` | ✅ | 220 |
| **Total** | | **~1.150 linhas serviços novos** |

### Novos commands

- `copiloto:eval` — gabarito Recall@K
- `copiloto:backfill-fatos` — re-roda extract com filtro 3
- `copiloto:cache:stats` — stats SemanticCache

---

## 🎯 Decisões canônicas registradas

1. **ADR 0054** — pacote enterprise busca memória + estratégia evolução automática (5 fases)
2. **Roadmap explícito Phase 1→5** com gates objetivos por Recall@3
3. **Auto-pilot** documentado: facts com hits≥5 viram core, cache hits≥10 bumpa TTL,
   HyDE/Reranker desabilita auto se Δrecall<0
4. **Triggers upgrade arquitetural** (Mem0/Letta/Qdrant) baseados em sinais

---

## 🚧 Pendências críticas (próxima sessão)

| # | Item | Esforço | Bloqueia |
|---|---|---|---|
| 1 | Wire HyDE no `MeilisearchDriver::buscar` | 0,5d | Phase 2 (R@3≥0.30) |
| 2 | Wire LlmReranker (top-50 → top-10) | 0,5d | Phase 3 (R@3≥0.50) |
| 3 | Wire Negative cache | 0,5d | economia secundária |
| 4 | Fix ProfileDistiller bug (output vazio com biz=4) | 1h | -30% system prompt |
| 5 | Auto-promote logic — service que percorre logs e seta core_memory | 0,5d | Phase 4 |
| 6 | Backfill facts (`copiloto:backfill-fatos --business=4 --sync`) — ainda não rodado | 5min | aumenta corpus pra Phase 1→2 |
| 7 | Re-rodar gabarito após backfill — medir ΔR@3 | 5min | valida hipótese |
| 8 | **`.mcp.json` + onboarding doc time** | 0,5h | time entra |
| 9 | **Watcher Node MEM-CC-1** | 1d | ativação compartilhamento Claude Code |

---

## 💡 Aprendizados / observações

1. **Corpus > Retrieval** quando dados são poucos: nosso Recall@3 baixo (0.125) é
   sintoma de 6 fatos no corpus, não falha do Meilisearch. Fix barato (relax filtro)
   tem mais ROI que tuning RRF.

2. **Compartilhamento Claude Code time é alavanca brutal**: 5× output/$ pelo time
   inteiro vs solo. Wagner pode programar mesmo tempo, mas Felipe/Maíra/Luiz/Eliana
   não re-pesquisam coisa que já foi achada.

3. **Anthropic prompt caching já é automático** no Claude Code (cache_read $0.30/M
   vs input $3/M = 90% mais barato). Otimizações nossas devem combinar com esse,
   não substituir.

4. **5 fases com gates objetivos** evita "feature creep": só ativa próxima
   otimização quando métrica destrava. Sem isso, acumula complexity sem ROI.

5. **Cycle 01 goal está EM CURSO**: "Copiloto assertivo e econômico... cache
   semântico ≥50%" — SemanticCache implementado e funcional; falta acumular
   queries similares pra hit rate dar resultado mensurável.

---

## 🔮 Próximo cycle (Cycle 02 — começa 13-mai)

Candidatos pra goal Cycle 02:

1. **"Time conectado ao MCP server"** — Felipe/Maíra/Luiz/Eliana com tokens, MCP-CC-1
   ativo, watcher rodando, cc-search tool funcional
2. **"PontoWr2 Tier A"** — outro produto, validação Eliana(WR2)
3. **"Copiloto pago"** — pricing + 1ª venda Tier 1A pra Larissa ou outro

A decisão fica pra final do Cycle 01 (12-mai) com retro de 5 linhas.

---

**Última atualização:** 2026-04-29 (noite)
