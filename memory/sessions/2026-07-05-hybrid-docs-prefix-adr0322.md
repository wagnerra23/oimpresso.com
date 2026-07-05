---
date: "2026-07-05"
topic: "Reabertura do hybrid de docs com instruction-prefix qwen3 — medição A/B CT 100 + ADR 0322"
authors: [C]
---

# Hybrid de docs reaberto com instruction-prefix — medição A/B + ADR 0322

**Origem:** next_step #2 do handoff [2026-07-05-0130](../handoffs/2026-07-05-0130-rag-investigacao-profunda-sync-fix.md) — briefings só acháveis via semântica, prod em FULLTEXT (ADR 0312), caso de uso #1 (estado do módulo) não fechava em prod.

**Regra seguida:** medir ANTES de propor (lição 2026-07-04 — grade sem A/B inverte prioridade). Testes SEMPRE no CT 100.

## Medição (CT 100, golden set N=27, 2 runs idênticos — estável)

| Caminho | recall@5 | violations |
|---|---|---|
| FULLTEXT MySQL (prod) | 0.556 (15/27) | 0 |
| Hybrid raw 0.6 (o que a 0312 desligou) | 0.815 (22/27) | 0 |
| **Hybrid + prefix (vector pré-computado)** | **0.852 (23/27)** | 0 |

- Sweep semanticRatio 0.3/0.5/0.6/0.7/0.9/1.0 → platô 0.852 em 0.6–1.0 (0.3 despenca pra 0.259 — keyword Meili fraco em query NL). Mantido 0.6.
- Prefix via `vector` pré-computado no Ollama (`q` segue raw pro BM25) — sem poluir o lado lexical.
- Latência: embed p50 284ms + Meili p50 80ms ≈ ~370ms (viável; reranker BGE eram 68s).
- Smoke das queries-assinatura da 0312: multi-tenant → 0093 r1 ✅; Centrifugo → 0058 r1 ✅; "daily brief" → 0091 segue fora do top-5 ❌ (canon-vs-canon; FULLTEXT acha em r5; alavanca = curadoria/summary G4).
- Per-query: hybrid+prefix ganha 10 que o FT perde (incl. os 3 `briefing:<Mod>`); FT ganha 2 (0130, 0052); ambos erram 2 (0091, 0053). Saldo +8.

## Entrega

- **ADR 0322** (`supersedes_partially: [0312]`, status proposto) — executa a condição de reativação da própria 0312: (a) prefix ✅ (b) template 🟡 parcial (c) reindex ✅ pós #3815/#3821 (d) ratio sweep ✅.
- **Código:** `buscarHybrid` pré-computa embedding prefixado (config-as-code `docs_query_instruction`) com fail-open duplo (Ollama fora → raw; Meili fora → FULLTEXT). 3 Pest novos (5/5 verdes no CT 100 staging).
- **Rollout:** flag existente `JANA_MCP_SEARCH_PIPELINE_DOCS=true` no CT 100 SÓ após aceite Wagner. Rollback = flag false.

## Contexto paralelo

Next_step #1 do handoff (sync robusto) foi entregue por sessão paralela ([#3821](https://github.com/wagnerra23/oimpresso.com/pull/3821)) — o cron `mcp:sync-memory` rodou completo durante esta sessão (lock + retry deadlock funcionando). Residuais no US-COPI-130: canary recall@k (baseline 0.852 vira floor), template rico (G3), summary por doc (G4), fusão FT∪hybrid, golden set → 100+ (G7).
