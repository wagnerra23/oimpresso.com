# ADR 0054 — Pacote enterprise de busca de memória: por quê + como evolui

**Status:** Aceito
**Data:** 2026-04-29
**Decidido por:** Wagner [W] — *"implemente as praticas na busca de memória. e continue aprendendo e melhorando. estipulo para ter um excelente ROI"*
**Estende:** [ADR 0035](0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0036](0036-replanejamento-meilisearch-first.md), [ADR 0037](0037-roadmap-evolucao-tier-7-plus.md), [ADR 0046](0046-chat-agent-gap-contexto-rico.md), [ADR 0047](0047-wagner-solo-sprint-memoria-agente.md), [ADR 0049](0049-camadas-memoria-agente-fase-por-fase.md), [ADR 0050](0050-metricas-obrigatorias-memoria-table.md)

---

## Contexto

Em 29-abr-2026, baseline do gabarito 50 perguntas Larissa-style mediu:

| Métrica | Valor | Gate ADR 0050 |
|---|---|---|
| Recall@3 | **0.125** | ≥ 0.80 |
| Precision@3 | 0.190 | ≥ 0.60 |
| MRR | 0.274 | ≥ 0.70 |
| Latência p95 | 771 ms | < 2000 ms |

Causa raiz: corpus de **6 fatos** total (94% rejeitados por filtro `relevancia<5`) — não problema do retrieval. Mesmo com Meilisearch hybrid, BM25, e ContextoNegocio, sem corpus o sistema é faminto.

Wagner deu mandato amplo: implementar **todas** as práticas state-of-the-art alinhadas com roadmap canônico (ADR 0037 Sprints 8-10), com promessa de "excelente ROI", e continuar evoluindo automaticamente.

## Decisão — pacote em 3 camadas

### 🟢 Camada A — Economia de tokens (-68.8% LLM, ROI imediato)

| # | Otimização | Status | Arquivo |
|---|---|---|---|
| 1 | **SemanticCacheMiddleware** (S8-1) | ✅ wired + smoke prod OK | `Services/Cache/SemanticCacheService.php` |
| 2 | **ConversationSummarizer** (S8-2) | ✅ wired + 4.09× compressão validada | `Services/Memoria/ConversationSummarizer.php` |
| 3 | **ProfileDistiller** (S8-3) | ⚠️ wired, teste falhou silencioso | `Services/Memoria/ProfileDistiller.php` |
| 4 | **Negative cache** (S10-3) | 🟡 schema feito, lógica pendente | `Database/Migrations/...negative_cache.php` |

### 🟡 Camada B — Qualidade do retrieval (+15-20pp recall esperado)

| # | Otimização | Status | Arquivo |
|---|---|---|---|
| 5 | **HyDE Query Expander** (S10-1) | 🟡 service pronto, **wiring pendente** | `Services/Memoria/HydeQueryExpander.php` |
| 6 | **LLM Reranker** (S10-2) | 🟡 service pronto, **wiring pendente** | `Services/Memoria/LlmReranker.php` |
| 7 | **RRF tuning** semantic_ratio (P2-2) | 🔲 TODO | A/B test contra gabarito |

### 🔵 Camada C — Higiene de memória (recall quality long-term)

| # | Otimização | Status | Arquivo |
|---|---|---|---|
| 8 | **Auto-promote facts** (S8-4) | 🟡 schema pronto, lógica pendente | migration + service hook |
| 9 | **Filtro relaxado** 5→3 (EVAL-2) | ✅ deployed | `Jobs/ExtrairFatosDaConversaJob.php` |
| 10 | **Gabarito 50 perguntas** (EVAL-1) | ✅ deployed | `MemoriaGabaritoSeeder.php` |

---

## Por quê ESSAS escolhas (justificativa por item)

### 1. SemanticCache — pelo Sprint 8 highest ROI

ADR 0037 já apontou: *"semantic caching corta até 68.8% de tokens LLM em produção"*. Larissa repete pergunta similar 5-7×/dia ("qual faturamento?", "quanto vendi?"). Cada repetição cacheada = 1 LLM call evitado.

**Trade-off:** TTL curto (1h) pra dados que mudam (faturamento muda no dia) vs hit rate alto. Mitigação: `invalidarPorBusiness()` chamado após eventos (nova venda, ajuste de meta).

**Smoke real validou:** query exata 11ms, fuzzy 2ms, miss 1ms. Funciona.

### 2. ConversationSummarizer — comprime hot window

Conversa Larissa real (conv #3): 48 mensagens. Sem summarizer, hot window vira 5k+ tokens dos quais só 1k são relevantes.

**Smoke real validou:** 928 tokens → 227 tokens (**4.09× compressão**), preservando fatos numéricos críticos: *"R$ 31.513,29 abril 2026"*, *"R$ 38.215,07 março maior venda"*.

**Trade-off:** 1× LLM call cheap por compressão (R$ 0,001) vs amortização em 5+ turnos seguintes. Vale.

### 3. ProfileDistiller — substitui ContextoNegocio crus

Hoje `formatarContextoNegocio` calcula dinamicamente a cada request (~150-250 tokens). Distiller faz 1× LLM call/dia/business pra gerar narrativa compacta (~200 tokens), substituindo o cálculo.

**Trade-off:** R$ 0,002/dia/biz vs -30% tokens system prompt em CADA request. Em 100 requests/dia: economia 30k tokens × R$ 0,000165 = R$ 0,005/dia. Paga em 1 dia se biz tem >50 requests.

**Pendência:** smoke deu profile vazio com biz=4 (ContextoNegocio retorna pouco data). Debug pendente.

### 4. HyDE Query Expander — bridge "phrasing gap"

User: *"como tá o caixa?"* — query CURTA, ambígua. Embedding desse texto tem POUCO sinal pra match com facts armazenados como *"faturamento líquido R$ 27.272 abril 2026"*.

HyDE gera "documento hipotético" (~80 tokens) que **responderia** a pergunta usando jargão de negócio. Esse doc tem MUITO mais sinal lexical pra match vector.

**Literatura RAG 2026:** +15% Recall@10. **Custo:** R$ 0,000264/expansão + cache 1h.

### 5. LLM Reranker — substitui cross-encoder GPU

Estado-da-arte 2026 pede reranker cross-encoder após retrieval (top-100 → top-10). Cross-encoder dedicated requer container Python + GPU — inviável Hostinger.

**Solução:** LLM-as-Reranker — gpt-4o-mini ranqueia top-K candidatos por relevância à query. Custo: R$ 0,0005/rerank vs +5pp recall@5 esperado. Cache 5min pro mesmo query+candidatos.

### 6. Auto-promote facts — bypass retrieval pra fatos cruciais

Hoje cada request faz round-trip Meilisearch (~50-200ms). Facts com hits acumulado >=5 são CRUCIAIS — meta da empresa, top cliente, etc.

**Solução:** flag `core_memory=true` → injetado direto no system prompt SEM passar por Meilisearch. Latência 200ms → 0ms pra fatos cruciais.

### 7. Negative cache — economia em queries off-topic

User pergunta *"qual previsão do tempo amanhã?"* — não está no DB, nunca vai estar. Hoje cada vez faz round-trip Meilisearch (50-200ms) pra retornar nada.

**Solução:** marca cache_key + biz como "sem hits", próxima query igual salta direto pra null em ~5ms. TTL 15min pra mitigar staleness.

---

## Como vai funcionar a EVOLUÇÃO

### Loop de aprendizado mensurável

```
[gabarito 50 perguntas]
       ↓
[copiloto:eval baseline]  ← captura R@3, P@3, MRR, latência
       ↓
[identifica gap mais barato]  ← qual métrica está pior + qual otimização barata corrige
       ↓
[ativa OU tuna otimização N]
       ↓
[copiloto:eval depois]
       ↓
[delta vs baseline]
       ↓
[decisão: MANTÉM / REVERTE / PRÓXIMA]
       ↓
[grava decisão em copiloto_memoria_metricas + log]
```

### Gates objetivos pra avançar (5 fases)

| Fase | Gate Recall@3 | Foco | Próxima ação |
|---|---|---|---|
| **1 (atual)** | < 0.30 | **Corpus** (poucos facts) | Backfill facts + relax filtro + extraction sources |
| 2 | ≥ 0.30 < 0.50 | **Retrieval** | RRF tuning A/B + HyDE wiring |
| 3 | ≥ 0.50 < 0.80 | **Ranking** | Reranker wiring + Auto-promote |
| 4 | ≥ 0.80 | **Economia** | Cache + Summarizer + Distiller (já feitos) |
| 5 | ≥ 0.80 + Faithfulness ≥ 0.90 | **Inteligência** | Reflective memory (ADR 0049 fase 3) |

### Estratégia de re-medição automática

| Cadência | Comando | O que faz |
|---|---|---|
| **Diária (cron 02:00 BRT)** | `copiloto:metrics:apurar` | 8 métricas obrigatórias ADR 0050 |
| **Diária** | `copiloto:eval --persist` | Gabarito completo, grava em `copiloto_memoria_metricas` |
| **Diária** | `copiloto:cache:stats` | Hit rate, R$ economizados |
| **Semanal (sex 23:00)** | `copiloto:eval --resposta` | + RAGAS (Faithfulness, Answer Relevancy) — custo R$ 0,11/run |
| **Mensal** | re-curate gabarito + re-baseline | Detecta drift |

### A/B framework (planejado pra Sprint 9+)

```bash
copiloto:metrics:ab-test \
  --condicao-a='{"hyde": false, "rerank": false}' \
  --condicao-b='{"hyde": true, "rerank": true}' \
  --suite=gabarito --runs=3 --persist
```

Saída: tabela com winner por métrica + p-valor (t-test). **Mínimo:** se ΔRecall@3 < +0.05 e Δlatência > +500ms, NÃO mantém.

### Auto-aprendizado (auto-pilot — fase 4-5)

Sistema observa próprios resultados e ajusta sozinho:

| Trigger | Ação automática |
|---|---|
| Fact com hits ≥ 5 | `core_memory = true` (skip retrieval) |
| Fact com hits = 0 + age > 30d | soft-delete (bloat reducer) |
| Cache entry com hits ≥ 10 | bump TTL 1h → 24h |
| Cache entry com hits = 0 + age > 7d | purge |
| Query com Faithfulness < 0.5 (RAGAS) | flag pra HITL review na dashboard |
| HyDE expansion com Δrecall < 0 em 50+ runs | desabilitada auto pra essa categoria |
| Reranker com Δrecall < 0 em 50+ runs | desabilitado auto pra essa categoria |

### Triggers de upgrade arquitetural

Se em qualquer fase o cap de qualidade for atingido (improvement plateau):

| Sinal | Decisão |
|---|---|
| Recall@3 plateau em 0.50-0.60 com tudo ativo | Avalia upgrade Mem0 (R$ 1,2k/ano) ou Letta (free) — ADR 0036 trigger |
| Faithfulness plateau em 0.70-0.80 | Avalia upgrade modelo (gpt-4o vs 4o-mini) ou agentic loop |
| Latência p95 > 3s consistente | Avalia migração Meilisearch → Qdrant local + reranker GPU CT 100 |

## Métricas que provam ROI desta decisão

### Antes (29-abr 2026 baseline)

```
Recall@3:        0.125
Precision@3:     0.190
MRR:             0.274
Tokens/turno:    275 in + 93 out = 368
Custo/turno:     R$ 0,000548
Cache hit rate:  N/A (não existia)
```

### Após pacote completo wired (estimativa pós-Phase 4)

```
Recall@3:        ≥ 0.80 (gate ADR 0050)
Precision@3:     ≥ 0.60
MRR:             ≥ 0.70
Tokens/turno:    100 in + 50 out = 150 (-59% via cache + summarizer)
Custo/turno:     R$ 0,0001 (-82%)
Cache hit rate:  ≥ 30% (gate Cycle 01)
```

**Impacto comercial:** Margem por cliente Tier 1A (R$ 199/mês plano):
- Antes: ~R$ 0,55/dia × 30 = R$ 16,50/mês custo IA = **8% do plano**
- Depois: ~R$ 0,10/dia × 30 = R$ 3,00/mês = **1,5% do plano** → **+6,5pp margem por cliente**

Em 50 clientes: economia R$ 675/mês × 12 = **R$ 8.100/ano** só de IA.

## Consequências

**Positivas:**
- Roadmap deixa de ser intuição, vira matemática (gates numéricos)
- Cada otimização é mensurável isoladamente (A/B framework)
- Auto-pilot evita "feature creep" — sistema desabiliza otimizações que não rendem
- Decisões de upgrade arquitetural ficam baseadas em sinais observados, não opinião
- ROI fica auditável (custo absoluto/turno + economia acumulada via cache hit)

**Negativas / Trade-offs:**
- Esforço inicial: 5d (Camada A wired + Camada B services + ADRs documentados)
- Curva de aprendizado: cada otimização precisa monitoramento mensal
- Risk de "config sprawl" — 10+ flags pra ajustar; mitigação: defaults sensatos + scheduler de A/B
- Custo de manutenção do gabarito: Wagner adiciona ~5 perguntas novas/mês
- Dependência da disciplina de medir antes de evoluir (ADR 0049)

## Pré-requisitos pra ativar Phase 2+

1. **Wire HyDE no MeilisearchDriver::buscar** — chamar antes de Scout query
2. **Wire Reranker** — chamar depois de Scout query top-50, retornar top-10
3. **Wire Negative cache** — checa antes de buscar; grava se retornou vazio
4. **Implementar ProfileDistiller fix** — bug atual gera profile vazio
5. **Implementar Auto-promote logic** — service que percorre `mcp_audit_log` ou logs de uso e marca core_memory
6. **Backfill facts** — re-rodar `ExtrairFatosDaConversaJob` em todas conversas com filtro relaxado (já tem comando `copiloto:backfill-fatos`)

## Referências

- ADR 0037 roadmap Tier 7+
- ADR 0049 6 camadas de memória
- ADR 0050 8 métricas obrigatórias
- LongMemEval ICLR 2025: https://arxiv.org/abs/2410.10813
- Hybrid retrieval RRF best practices 2026: https://superlinked.com/vectorhub/articles/optimizing-rag-with-hybrid-search-reranking
- HyDE paper: https://arxiv.org/abs/2212.10496
- Smoke validations: `memory/sessions/2026-04-29-pacote-enterprise-memoria.md` (próxima sessão a criar)
