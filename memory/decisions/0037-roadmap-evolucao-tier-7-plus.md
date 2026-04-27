# ADR 0037 — Roadmap de evolução pós-Sprint 5: Tier 5-6 → Tier 7-9 LongMemEval

**Status:** ✅ Aceita — roadmap derivado de pesquisa profunda 2026-04-26 sessão 18
**Data decisão:** 2026-04-26 (sessão 18 fim de noite)
**Autor:** Wagner (instrução *"pesquise as melhores implementações... como evoluir? compare as alternativas"*) + *"salve na memoria, e continue"*
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Relacionado:**
- [ADR 0036 — replanejamento Meilisearch first](0036-replanejamento-meilisearch-first.md) — base
- [ADR 0035 — verdade canônica](0035-stack-ai-canonica-wagner-2026-04-26.md)
- [ADR 0033 — vector store](0033-vector-store-meilisearch-pgvector-mem0.md)

---

## Contexto

Após Sprint 5 ([PR #26](https://github.com/wagnerra23/oimpresso.com/pull/26) — bridge memória↔chat) o Copiloto está em **Tier 5-6 estimado de LongMemEval** (ICLR 2025). Pesquisa profunda em 2026-04-26 sessão 18 mapeou estado-da-arte e identificou 5 GAPs concretos pra evolução até Tier 7-9.

**Padrão dual-layer Hot/Cold já implementado** ✅ (alinhado com prática 2026):
- Hot Path: `recallMemoria()` síncrono antes do LLM (top-K via Scout/Meilisearch)
- Cold Path: `ExtrairFatosDaConversaJob` assíncrono via Horizon

**Comparativo de benchmarks LongMemEval (state of art):**
- OMEGA 95.4% (managed premium)
- Mastra Observational 94.87%
- Hindsight 91.4%
- Letta 83.2%
- Zep/Graphiti 71.2%
- Mem0 ~67%
- **oimpresso atual: ~50-65% estimado** (não medido — primeiro sprint da evolução é justamente medir)

## Decisão: roadmap em 5 sprints sequenciais (sprints 7-11)

Cada sprint **mensurável** + **com gatilho mensurável de pivot** + **R$0/mês recorrente** até validar.

### Sprint 7 — RAGAS evaluation (PRIMEIRO de tudo)

**Por quê primeiro:** "you can't improve what you don't measure". Padrão estado-da-arte 2026 = RAGAS (Retrieval Augmented Generation Assessment) — não exige gold answers manuais.

**Métricas-alvo de produção:**
- Faithfulness > 0.90 (resposta fiel ao contexto recuperado)
- Answer relevancy > 0.85
- Context recall > 0.80 (recuperou os fatos certos?)
- Context precision > 0.75 (não recuperou ruído?)

**Implementação:**
- Pacote PHP: pesquisar (`ragas-php` se existir, OU implementar via Pest custom)
- Suite `tests/Eval/CopilotoMemoriaEvalTest.php` rodando em CI/GH Actions
- 20-30 cenários gold criados por Wagner (mix Larissa-style + edge cases)

**Esforço:** Médio (1 sprint)
**Custo:** R$0 (rodar tokens em CI)
**Output:** baseline numérica do Copiloto. Sem essa baseline, sprints 8-11 são fé.

### Sprint 8 — Semantic caching (highest ROI)

**Por quê:** estado-da-arte 2026 mostra **semantic caching corta até 68.8% de tokens LLM em produção**. Larissa repete perguntas similares (typical SaaS user) — primeiro hit paga, similares são cache.

**Implementação:**
- `SemanticCacheMiddleware` aplicado antes de `recallMemoria()`
- Hash da query (vector embedding) → similarity > 0.95 retorna resposta cached
- TTL configurável (default 24h pra contexto de business muda diariamente)
- Storage: Redis OU `Cache::remember` na infra atual

**Esforço:** Baixo (0.5-1 sprint)
**Custo:** R$0 (usa Redis/Cache atual)
**Métrica de fé:** rodar RAGAS antes/depois — se faithfulness não cair >0.02 e tokens cair >40%, mantém. Senão pivota.

### Sprint 9 — RRF tuning (Meilisearch já tem)

**Por quê:** Reciprocal Rank Fusion combina BM25 + vector. **Meilisearch tem hybrid search builtin via `semantic_ratio`** (config atual: 0.5). Pesquisa 2026 mostra que tuning empírico do ratio (0.3-0.7 range) gera +10-15% recall em domínios específicos.

**Implementação:**
- A/B test em CI: rodar suite RAGAS com `semantic_ratio` em [0.3, 0.4, 0.5, 0.6, 0.7]
- Escolher o que maximiza `recall × precision`
- Atualizar config default

**Esforço:** Baixo (0.5 sprint)
**Custo:** R$0
**Risco:** baixo — feature já no Meilisearch, só estamos tuning

### Sprint 10 — HyDE / Query Expansion

**Por quê:** estado-da-arte 2026 — gera pergunta/documento hipotético com LLM antes da busca. **Bridge "phrasing gap"** — Larissa pergunta "como tá o caixa?" mas fato salvo é "meta de R$80k/mês". Hybrid search puro pode falhar; HyDE expande query pra "qual o status financeiro do business em relação à meta de faturamento?".

**Implementação:**
- Antes de `recallMemoria()`, chamar `ExpandirQueryAgent` (LLM cheap — gpt-4o-mini)
- Buscar com query expandida + query original (RRF entre as duas)
- Caching agressivo (pergunta repetida = mesma expansão)

**Esforço:** Médio (1-1.5 sprints)
**Custo:** ~10-15% mais tokens por pergunta (mitigado por cache)
**Métrica de fé:** RAGAS recall sobe >+15%? Se sim, mantém. Senão, deleta.

### Sprint 11 — Trigger conditional Mem0/Zep upgrade

**Por quê:** ADR 0036 já documenta 5 triggers. Sprint 11 é onde validamos:
1. Dedup do MeilisearchDriver falha em ≥10% dos casos
2. Conversas >50 turnos perdem contexto
3. Conflict resolution temporal precisa de validity windows native (Zep)
4. Wagner pedir explicitamente
5. Tier 6-7 LongMemEval virar requisito comercial

**Sem trigger:** continua MeilisearchDriver (custo R$0/mês).
**Com trigger:** ativa `Mem0RestDriver` ou `ZepRestDriver` na `MemoriaContrato` (interface trocável).

## Ordem & dependências

```
Sprint 7 (RAGAS) ──┐
                   ├──→ baseline mensurável
                   │
Sprint 8 (cache) ──┴──→ -68.8% tokens (HIGH ROI imediato)
                                     │
Sprint 9 (RRF) ──────────────────────┴──→ +10-15% recall (low risk)
                                                  │
Sprint 10 (HyDE) ─────────────────────────────────┴──→ +15% recall (medium risk)
                                                              │
Sprint 11 (Mem0/Zep) ─────────────────────────────────────────┴──→ trigger condicional
```

**Sprint 7 é gate**: sem RAGAS, sprints 8-10 não têm como provar que melhoraram.

## Justificativa econômica

| Sprint | Custo direto | Economia esperada | ROI |
|---|---|---|---|
| 7 | 1 sprint | habilita medição | infra de melhoria |
| 8 | 0.5 sprint | -68.8% tokens LLM (~R$200-2000/mês em escala) | **muito alto** |
| 9 | 0.5 sprint | +10-15% recall (qualidade) | médio |
| 10 | 1.5 sprint | +15% recall (qualidade) | médio |
| 11 | 0-3 sprints | depende do trigger | condicional |

**Total se executar tudo:** ~3.5-6.5 sprints (~5-9 semanas Wagner solo). Custo recorrente continua R$0/mês até trigger ativar Mem0.

## Trade-off explícito vs concorrentes verticais

Verticais gráficos (Mubisys/Zênite/Calcgraf) **não têm Copiloto IA-first**. Mesmo em Tier 5-6 estamos à frente. Sprint 7-10 nos coloca em Tier 7-8 — patamar de SaaS modernos.

**Não vale ir além de Tier 8 sem 10+ clientes pagantes** — investimento marginal vs retorno cai. ADR 0036 já trava Mem0 condicional por isso.

## Consequências

✅ Roadmap mensurável até 9-10 semanas a partir de hoje.
✅ Sprint 7 (RAGAS) como gate força disciplina de medir antes de otimizar.
✅ Custo recorrente continua R$0 (Meilisearch self-hosted + cache local).
✅ Triggers do ADR 0036 ainda governam decisão de Mem0 — sem queimar grana antes de validar.
⚠️ RAGAS em PHP pode não ter pacote pronto — pode exigir port/wrap manual.
⚠️ HyDE adiciona latência (10-15% por query expansion). Caching de expansão mitiga.
⚠️ Sprint 11 sem trigger = NÃO executar. Documentar isso pra próximo agente não "fazer pelo gosto".

## Alternativas consideradas

- **Trocar Meilisearch por Typesense agora** (auto-embedding builtin, latência <50ms p99): rejeitado pra v1 — Meilisearch funciona, Typesense é evolução natural se Meilisearch ficar limitado.
- **Pular RAGAS e ir direto pra Mem0:** rejeitado — sem baseline, não dá pra provar que Mem0 melhorou.
- **Procedural memory (skills learned):** adiado — categoria já existe no schema (`copiloto_memoria_facts.metadata.categoria=acao_pendente`) mas mecanismo é complexo. Volta ao roadmap só se trigger ativar.

## Refs externas (das pesquisas 2026-04-26)

- [Architecture & Orchestration of Memory Systems in AI Agents (analyticsvidhya 2026)](https://www.analyticsvidhya.com/blog/2026/04/memory-systems-in-ai-agents/)
- [Hermes OS: dual-layer architecture 2026](https://hermesos.cloud/blog/ai-agent-memory-systems)
- [Mem0: State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [RAG at Scale (Redis 2026)](https://redis.io/blog/rag-at-scale/) — semantic caching 68.8% redução
- [RAG in 2026 Practical Blueprint (DEV)](https://dev.to/suraj_khaitan_f893c243958/-rag-in-2026-a-practical-blueprint-for-retrieval-augmented-generation-16pp)
- [Design Patterns for Long-Term Memory (Serokell)](https://serokell.io/blog/design-patterns-for-long-term-memory-in-llm-powered-architectures)
- [Meilisearch vs Typesense (OSSAlt 2026)](https://ossalt.com/blog/meilisearch-vs-typesense-vs-elasticsearch-search-2026)
- [Agent Memory Architectures: Vector vs Graph vs Episodic](https://www.digitalapplied.com/blog/agent-memory-architectures-vector-graph-episodic)
- [Steve Kinney: Memory Systems for AI Agents 2026](https://stevekinney.com/writing/agent-memory-systems)

## Compromisso de revisão

Reavaliar após sprint 7 com baseline RAGAS real:
- Se faithfulness >0.85 já no baseline → sprint 8 (caching) tem ROI alto
- Se faithfulness <0.70 → revisar arquitetura, não otimizar (sprint 8 espera)
- Se context recall <0.60 → urgência em sprint 10 (HyDE)
