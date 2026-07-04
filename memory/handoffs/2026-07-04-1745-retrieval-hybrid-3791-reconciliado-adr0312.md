---
date: "2026-07-04"
time: "17:45 BRT"
slug: retrieval-hybrid-3791-reconciliado-adr0312
tldr: "Retrieval da Jana (docs_pipeline): buscarHybrid via API REST direta (#3791, flag OFF/neutro em prod), runbook on-prem Gold refinado (#3792), US-COPI-130 registrada (#3793). ACHADO no fechamento: a ADR 0312 desligou o hybrid de propósito (qwen3 query raw inverte a similaridade). O A/B '9.5x' usou baseline errado; ragas real dá empate (0.395 vs 0.422). Ligar teria regredido — Wagner esperou (correto). Camadas 2+3 bloqueadas em infra/secret."
prs: [3791, 3792, 3793]
related_adrs:
  - 0312-decisions-search-fulltext-hybrid-docs-off
  - 0318-ragas-eval-real-mata-tautologia-ct100-staging
  - 0068-sprint9-retrieval-ollama-reranker-strategy
next_steps:
  - "NÃO religar JANA_MCP_SEARCH_PIPELINE_DOCS até cumprir as 4 condições da ADR 0312 (instrução de query qwen3 + documentTemplate real + reindex + re-tuning semanticRatio) E superar o FULLTEXT num gold-set honesto"
  - "Camada 2 (contextual): provisionar ANTHROPIC_API_KEY no CT 100 (vive no Hostinger) → backfill Haiku (~$3-4, 1459 docs) + fix toSearchableArray (contextual no excerpt) + reindex + A/B"
  - "Camada 3 (reranker): conectar container bge-reranker à rede docker-host_default + endpoint/DNS + driver=bge → plugar no buscarHybrid"
  - "FIN-004 (blocked): aguarda Wagner definir valor/plano da assinatura ROTA LIVRE + confirmar se é assinatura do ERP"
---

# Retrieval da Jana — camada 1 corrigida, reconciliada com ADR 0312, camadas 2+3 bloqueadas

## Estado MCP no momento do fechamento

- **Cycle ativo:** nenhum em COPI (`cycles-active` → "Nenhum cycle ATIVO").
- **my-work (@wagner):** 30 tasks (8 review, 8 blocked, 14 todo). Blocked inclui `FIN-4` (FIN-004 cobrança ROTA LIVRE) + US-NFE-043..048 Gold (dormentes) + FORJA-136.
- **decisions-search "retrieval hybrid reranker contextual":** trouxe a ADR **0312** (o achado crítico) + 0318 + 0148 + 0054.
- **main HEAD:** inclui #3791/#3792/#3793 mergeados nesta sessão.

## O que foi feito (3 PRs mergeados)

1. **[#3791](https://github.com/wagnerra23/oimpresso.com/pull/3791)** — `McpMemoryDocument::buscarHybrid` reescrito pra chamar a API REST do Meilisearch DIRETO (bypassa o Scout `collection` engine que ignorava o `hybrid`). Corrige um bug real (Scout driver default=collection no staging). **Neutro em prod:** a flag `JANA_MCP_SEARCH_PIPELINE_DOCS` continua OFF, então comportamento prod inalterado (FULLTEXT). Deployado no `oimpresso-mcp` prod (HEAD 6efb1920f) e staging.
2. **[#3792](https://github.com/wagnerra23/oimpresso.com/pull/3792)** — refino do `RUNBOOK-recuperacao-on-prem` (Gold): seletor de trilha A/B (emissão vs manifestação ADR 0116) + pegadinhas reais de upgrade + molde de reutilização pros 49 dormentes. `stub-em-construcao → ativo`.
3. **[#3793](https://github.com/wagnerra23/oimpresso.com/pull/3793)** — registra US-COPI-130 (camadas 2+3 do docs_pipeline) no SPEC do Jana + timeline com bloqueadores concretos + reconciliação com a ADR 0312.

## ⚠️ Achado crítico: ADR 0312 (li tarde) + erro de baseline

**A ADR 0312 (2026-06-29, aceita Wagner) desligou o hybrid de docs de propósito.** Causa-raiz medida (smoke prod): o embedder `qwen3-embedding:0.6b` EXIGE instrução de query (`Instruct:…\nQuery:…`), mas o Meilisearch (source ollama) envia a query RAW → o vetor cai no espaço errado e a similaridade INVERTE (ex: "daily brief" retornava `recharts`/`agents` em vez da ADR 0091). FULLTEXT MySQL mede melhor (~6/7 vs ~4/7 top-3).

**Dois erros meus:**
1. Não rodei `decisions-search` no início (violei o protocolo) → perdi a 0312, que é diretamente sobre isto.
2. O "recall@5 0.074→0.704 (~9.5x)" que apresentei comparou hybrid vs **Meilisearch keyword**, NÃO vs o **FULLTEXT MySQL** que é o caminho real de prod (flag OFF). O número honesto é o **ragas A/B** (KbAnswerService FULLTEXT real vs hybrid, mesmo corpus): **quase empate — context_recall 0.395 (FULLTEXT) vs 0.422 (hybrid)**.

**Consequência:** ligar o hybrid hoje seria REGRESSÃO vs a 0312. Wagner escolheu esperar as camadas 2+3 → decisão correta, evitou a regressão. Nada em prod foi tocado.

**Reconciliação:** a US-COPI-130 = exatamente a "condição de reativação" da 0312. Religar exige TODAS: (a) instrução de query no qwen3 [peça que faltava no meu plano], (b) documentTemplate real (= camada 2 contextual), (c) reindex, (d) re-tuning semanticRatio. + camada 3 (reranker) por cima.

## O tema da sessão

O retrieval da Jana tem **3 camadas de qualidade construídas e nenhuma ativada** — e há razão em cada caso:
- **Camada 1 (hybrid):** flag OFF por decisão (0312), não acidente. `buscarHybrid` tinha bug de Scout (corrigido #3791) mas o hybrid em si degrada sem instrução de query.
- **Camada 2 (contextual):** service Haiku pronto, backfill nunca rodou (0/1459), `ANTHROPIC_API_KEY` no Hostinger (ausente no CT 100), `toSearchableArray` só prepende contextual ao BM25 (não ao excerpt/embedding).
- **Camada 3 (reranker):** `BgeReranker` pronto + container `healthy`, mas em rede docker isolada (`bge-reranker_default` ≠ `docker-host_default`) → UNREACHABLE; driver=`rrf` não `bge`.

## Como retomar

O caminho canônico está na ADR 0312 §"Condição de reativação" + na US-COPI-130. **Pré-requisitos que dependem do Wagner (não auto mode):** provisionar `ANTHROPIC_API_KEY` no CT 100 (camada 2), conectar a rede do BGE (camada 3). Só então é trabalho de código+medição. **Gate de aceite duro:** o hybrid reformado tem que SUPERAR o FULLTEXT num gold-set honesto antes de religar a flag — senão fica FULLTEXT (canônico por 0312).

## Lições

- **decisions-search ANTES de mexer** — teria pego a 0312 no minuto 1 e poupado a investigação sob premissa errada. Violei o próprio protocolo.
- **Baseline certo importa** — comparar contra "keyword do Meilisearch" ≠ contra o FULLTEXT MySQL de prod. O número honesto (ragas) mostrou empate, não 9.5x.
- **A cautela salvou** — não ligar cego + Wagner escolher esperar evitou a regressão que a 0312 já documentava.
