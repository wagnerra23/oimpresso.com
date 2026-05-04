---
name: RAG / agent memory estado-da-arte 2026
description: Achados da pesquisa profunda 2026-04-26 sobre RAG patterns, dual-layer Hot/Cold, frameworks de memória (Mem0/Letta/Zep/OMEGA) + métricas RAGAS. Consolidado em ADR 0037 do projeto.
type: reference
originSessionId: dazzling-lichterman-e59b61
---

Pesquisa profunda em 2026-04-26 sessão 18 sobre estado-da-arte de RAG / agent memory. Aplica ao Copiloto do oimpresso.

## Padrão dual-layer Hot/Cold (2026)

Estado-da-arte 2026 = **Memory Node que roda após cada agent turn**:
- **Hot Path:** recall síncrono top-K antes do LLM (vector search hybrid)
- **Cold Path:** extração + persistência assíncrona depois (Job/queue)

✅ **oimpresso já segue isso** após Sprint 5 (PR #26): `recallMemoria()` síncrono + `ExtrairFatosDaConversaJob` async.

## Métricas-alvo de produção (RAGAS — ICLR 2025 standard)

- Faithfulness > 0.90 (resposta fiel ao contexto)
- Answer relevancy > 0.85
- Context recall > 0.80 (recuperou os fatos certos?)
- Context precision > 0.75 (sem ruído?)

RAGAS não exige gold answers manuais — usa LLM-as-judge.

## Comparativo benchmark LongMemEval

| Framework | Score | Status oimpresso |
|---|---|---|
| OMEGA | 95.4% | rejeitado (premium-only) |
| Mastra Observational | 94.87% | não considerado (Python) |
| Hindsight | 91.4% | não considerado (Python) |
| Letta | 83.2% | reservado (sprint 11+ se trigger) |
| Zep/Graphiti | 71.2% | reservado (sprint 11+ se temporal validity virar requisito) |
| Mem0 | ~67% | reservado (sprint 11+ ADR 0036) |
| **oimpresso pós-sprint 5** | ~50-65% estimado | **medir em sprint 7 via RAGAS** |

## 5 GAPs concretos vs estado-da-arte (formalizados em ADR 0037)

| # | GAP | ROI | Sprint |
|---|---|---|---|
| 1 | RAGAS evaluation no CI | infra | 7 (gate obrigatório) |
| 2 | Semantic caching | -68.8% tokens | 8 (high ROI) |
| 3 | RRF tuning Meilisearch | +10-15% recall | 9 |
| 4 | HyDE / query expansion | +15% recall | 10 |
| 5 | Trigger Mem0/Zep upgrade | depende | 11 (condicional) |

## Performance comparison search engines (2026)

- **Typesense:** <50ms p99, 10k QPS/node, **auto-embedding builtin** (Azure/GCP). Mais rápido. Evolução natural se Meilisearch ficar limitado.
- **Meilisearch (atual):** sub-50ms, hybrid search, **bring-your-own embeddings**. OK pra MVP. Self-hosted R$0/mês.
- **pgvector:** rejeitado (exige Postgres, ADR 0033).

## Insights práticos

1. **"can't improve what you don't measure"** — RAGAS é gate antes de qualquer otimização
2. **"lost in the middle"** problem — LLMs ignoram contexto no meio. Top-K deve estar nas extremidades do prompt
3. **Chunking:** 256-512 tokens pra factoid, 1024 pra analítico
4. **Semantic caching mata 68.8% dos tokens** em produção típica — biggest single optimization
5. **HyDE bridges phrasing gap:** Larissa pergunta "como tá o caixa?" → expand pra "qual status financeiro vs meta?" → Meilisearch acha o fato salvo

## Como aplicar

- Quando Wagner perguntar "como evoluir" / "próximo sprint" do Copiloto → abrir ADR 0037
- Quando ele pedir Mem0 → checar 5 triggers do ADR 0036; sem trigger, fica em Meilisearch
- Quando rodar RAGAS em sprint 7, baseline numérica decide se sprint 8/9/10 valem
- Pesquisas externas: 9 sources documentadas no rodapé do ADR 0037
