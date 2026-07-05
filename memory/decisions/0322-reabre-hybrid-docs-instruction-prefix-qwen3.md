---
slug: 0322-reabre-hybrid-docs-instruction-prefix-qwen3
number: 322
title: "Reabre o hybrid de docs com instruction-prefix qwen3 — executa a condição de reativação da ADR 0312 (hybrid 0.852 vs FULLTEXT 0.556 no golden set)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-05"
accepted_via: "Wagner 2026-07-05: apresentada a evidência A/B (FULLTEXT 0.556 vs hybrid+prefix 0.852, N=27, 2 runs) no PR #3829 → 'vai' (executar) → 'merge' (aceite da ADR + rollout do escopo pré-aprovado)."
module: jana
tags: [mcp, busca, retrieval, embedder, meilisearch, hybrid, qwen3, instruction-prefix, fulltext, decisions-search, kb-answer, briefing]
supersedes: []
supersedes_partially:
  - 0312-decisions-search-fulltext-hybrid-docs-off
superseded_by: []
related:
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento
  - 0068-sprint9-retrieval-ollama-reranker-strategy
  - 0053-mcp-server-governanca-como-produto
pii: false
---

# ADR 0322 — Reabre o hybrid de docs com instruction-prefix qwen3

**Supersede PARCIAL da [ADR 0312](0312-decisions-search-fulltext-hybrid-docs-off.md):** substitui apenas a §Decisão ("FULLTEXT é o caminho canônico de docs") **executando a §Condição de reativação que a própria 0312 exigiu**. O diagnóstico da 0312 (qwen3 instruction-aware, query raw inverte similaridade; medição antes de ligar) permanece válido e é exatamente o que esta ADR aplica. A 0312 segue `lifecycle: ativo` como registro do mecanismo.

## Contexto

A 0312 desligou `JANA_MCP_SEARCH_PIPELINE_DOCS` porque o hybrid mandava a query **raw** pro embedder `qwen3-embedding:0.6b` (instruction-aware) — a similaridade invertia e a busca devolvia lixo. Condição de reativação escrita na 0312: (a) instrução de query no embedder, (b) documentTemplate real, (c) reindex, (d) re-tuning do semanticRatio — e o hybrid teria que **superar o FULLTEXT num gold-set honesto**.

Desde então os dados mudaram: o sync fix [#3815](https://github.com/wagnerra23/oimpresso.com/pull/3815) indexou os BRIEFINGs (porta única do estado de módulo, ADR 0270 D-2) e o [#3821](https://github.com/wagnerra23/oimpresso.com/pull/3821) tornou o sync robusto. O caso de uso #1 (Larissa/Wagner: "estado consolidado do módulo X") **só fecha via semântica** — o FULLTEXT MySQL não casa a pergunta com o corpo do BRIEFING. Com prod em FULLTEXT, o principal caso de uso não funciona.

## Decisão

**Religar o hybrid de docs (`decisions-search`/`kb-answer`) COM instruction-prefix**, implementado assim:

1. `McpMemoryDocument::buscarHybrid` pré-computa o embedding de `instrução + query` direto no Ollama (mesmo modelo do índice) e envia como **`vector`** na busca Meilisearch. O **`q` continua raw** — o lado lexical (BM25) não vê o prefixo.
2. Instrução em config-as-code (`copiloto.mcp_search.docs_query_instruction`), não ajuste manual: `Instruct: Given a search query in Portuguese, retrieve the most relevant architecture decision record or governance document.\nQuery: `.
3. **Fail-open de qualidade, nunca de disponibilidade:** Ollama fora → hybrid raw (comportamento pré-0322); Meilisearch fora/vazio → fallback FULLTEXT (comportamento 0312). A busca nunca quebra.
4. `semanticRatio` mantido em **0.6** (sweep 0.3→1.0 mediu platô 0.852 em 0.6–1.0; 0.6 preserva o lado lexical pra queries de slug/jargão).
5. Rollout via flag existente `JANA_MCP_SEARCH_PIPELINE_DOCS=true` no `.env` do `oimpresso-mcp` (CT 100) **após aceite Wagner**. Rollback = flag `false` (volta FULLTEXT, zero deploy).

## Condições da 0312 — status na medição

| Condição 0312 | Status |
|---|---|
| (a) instrução de query no embedder | ✅ vector pré-computado com prefixo (este PR) |
| (b) documentTemplate real | 🟡 parcial — excerpt já é corpo real (frontmatter-strip), mas template segue 400 bytes; enriquecer = evolução (US-COPI-130 G3/G4) |
| (c) reindex | ✅ corpus re-syncado pós #3815/#3821 (1582 docs, BRIEFINGs do golden set no índice) |
| (d) re-tuning semanticRatio | ✅ sweep 0.3/0.5/0.6/0.7/0.9/1.0 — platô 0.6–1.0, mantém 0.6 |
| Gate: superar FULLTEXT em gold-set honesto | ✅ ver evidência |

## Evidência A/B (CT 100, 2026-07-05, golden set N=27 `tests/eval/recall-golden.yaml`, 2 runs idênticos)

| Caminho | recall@5 | violations (top-3) |
|---|---|---|
| FULLTEXT MySQL (prod hoje) | 0.556 (15/27) | 0 |
| Hybrid raw 0.6 (config que a 0312 desligou) | 0.815 (22/27) | 0 |
| **Hybrid + prefix 0.6 (esta ADR)** | **0.852 (23/27)** | **0** |

- Hybrid+prefix ganha **10 queries que o FULLTEXT perde** — incluindo as 3 portas de módulo (`briefing:Jana`/`Financeiro`/`Whatsapp`, caso de uso #1), `0093` multi-tenant, `0104` MWART, 3 pares colididos ADR 0274.
- Queries-assinatura da 0312 re-smokadas: "multi-tenant isolation" → 0093 **r1** (a 0312 media ausente do top-3); "Centrifugo websocket" → 0058 **r1** (sem regressão).
- **Latência** do prefix: embed Ollama p50 284ms + Meili p50 80ms ≈ **~370ms** — viável pra chat/tools (vs reranker BGE 68s/20docs, inviável, medição 2026-07-04).

## Residuais honestos (não resolvidos por esta ADR)

1. **Canon-vs-canon persiste:** "daily brief estado consolidado do projeto" ainda não traz a 0091 no top-5 do hybrid (FULLTEXT traz em r5); `0053` idem no golden. Alavanca é **curadoria** (campo `summary`, G4 da grade 2026-07-04) — mesma conclusão da 0312.
2. **2 regressões pontuais vs FULLTEXT:** `0130` (handoff-protocolo) e `0052` (três ângulos) — o FULLTEXT acha por overlap léxico exato. Fusão FT∪hybrid seria evolução (US-COPI-130), não bloqueio: o saldo líquido é +8 queries.
3. **Template rico** (condição b completa) fica pra US-COPI-130 G3 — exige reindex de 1582 docs no CPU.

## Máquina anti-apodrecimento (adendo "vira máquina" 2026-07-04)

- Instrução/ratio/embedder em **config-as-code** (este PR) — reconciliáveis, não `.env` solto (exceto a flag de rollout, que é o interruptor deliberado).
- **Canary recall@k** (US-COPI-130): baseline desta ADR (0.852) vira floor no `jana:recall-eval` — regressão silenciosa de upgrade de lib/embedder dispara alerta.
- Golden set N=27 é a régua viva; expandir pra 100+ segue no US-COPI-130 G7.

## Consequências

- **Positivo:** caso de uso #1 (estado do módulo via BRIEFING) fecha em prod; recall@5 +29.6pp vs FULLTEXT; pares colididos resolvem melhor; custo zero de API (embedder local).
- **Trade-off:** +~370ms de latência por busca; 2 queries lexicais pontuais pioram (saldo +8); dependência do Ollama no caminho quente — mitigada por fail-open duplo (raw → FULLTEXT).
- **Rollback:** `JANA_MCP_SEARCH_PIPELINE_DOCS=false` restaura o estado 0312 integralmente.
