---
date: "2026-07-05"
topic: "máquina anti-apodrecimento do índice RAG — delta STALE na sentinela (#3824) + canary recall@k com piso 0.80 (#3825)"
authors: [C]
prs: [3824, 3825]
related_adrs: ["0270-ciclo-de-vida-da-informacao-porta-unica-destilacao-decaimento", "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes", "0312-decisions-search-fulltext-hybrid-docs-off"]
outcomes:
  - "PR #3824 (empilhada na #3823): check mcp_index_sync_gap ganha critério STALE por heartbeat indexed_at >7d — CT 100 8/8 passed"
  - "PR #3825 (base main): jana:recall-eval real ganha recall_at_k agregado + gate --min-recall=0.80 — CT 100 5/5 passed, CI verde"
  - "Smoke real staging: lane keyword recall@5=0.0741 (alerta legítimo); 0.815 do handoff é da lane semantic — duas lanes documentadas"
---

# Máquina anti-apodrecimento do índice RAG — delta STALE + canary recall@k

Executa o **next_step #3** do handoff [2026-07-05-0130-rag-investigacao-profunda-sync-fix](../handoffs/2026-07-05-0130-rag-investigacao-profunda-sync-fix.md): (1) sentinela "doc canônico ausente do índice OU stale (indexed_at velho)"; (2) canary recall@k semanal com alerta se recall<80% (loop IA-OS #3).

## Colisão de sessão paralela — resolvida por extensão, não duplicação (T6)

Ao ir testar no CT 100, o bench (`oimpresso-staging`) estava com HEAD na branch de **outra sessão** que já tinha implementado a sentinela de **ausência** (#3821 sync robusto MERGED + #3822→#3823 sentinela). Minha versão da sentinela (branch já pushada) foi **descartada** (branch deletada) e o trabalho reconvertido em 2 deltas não-duplicados:

- **[#3824](https://github.com/wagnerra23/oimpresso.com/pull/3824)** (empilhada na #3823, retarget automático pra main): critério **STALE por heartbeat** — `indexSyncGapStats(+heartbeats, +limiteStale)` params opcionais (back-compat: os 4 testes da #3823 passam inalterados); check 10d acusa doc vivo com `indexed_at` >7d. Fundamento: todo run de sync toca `indexed_at` mesmo sem mudança de conteúdo (branch "sem mudança" do `indexarArquivo`) ⇒ heartbeat parado = sync quebrado — o residual "deadlock/OOM" do handoff, que ausência sozinha não pega (docs já indexados param no tempo sem nunca sumir). **CT 100: 8/8 passed (14 assertions).**
- **[#3825](https://github.com/wagnerra23/oimpresso.com/pull/3825)** (base main): canary recall@k — `runReal` computa `recall_at_k` (macro-média IR padrão) + `recall` por query; gate real vira `recall_at_k ≥ --min-recall (0.80)` + `recall_eval_violations = 0`, substituindo o binário 100%-por-query (sem piso, sem trend). O schedule semanal (dom 06:30 BRT staging, Kernel) já existia — **nenhum workflow novo** (T6). Modo mock (gate de PR) inalterado. **CT 100: 5/5 passed (10 assertions); CI da PR 100% verde.**

Desfecho: Wagner aprovou o merge no fim da sessão — **#3825 MERGED** (`d6bf4b82ea`); a #3824 foi **rebaseada sobre o main** após o squash-merge da #3823 (branch empilhada fica DIRTY quando a base entra por squash — cherry-pick do commit único sobre `origin/main` + force-with-lease resolve) e mergeia na sequência com CI verde.

## Descoberta do smoke real (honestidade de medição)

`php artisan jana:recall-eval --mode=real` no staging (2026-07-05): **recall@5 = 0.0741** na lane que o comando mede (search Meilisearch keyword direto no índice `mcp_memory_documents`), 25/27 queries perdendo docs, violations=0. O **0.815** do handoff foi medido na lane **semantic/hybrid**. Consequência operacional: o canary semanal alerta e é **sinal legítimo** (retrieval keyword abaixo do alvo — context_recall 0.38, hybrid docs_pipeline off, ADR 0312) até o next_step #2 do handoff (reabrir hybrid) aterrissar; depois vira detector de regressão (<0.80). Comentários no Kernel e no comando documentam as duas lanes explicitamente — sem prometer verde que não existe.

## Lições

1. **Checar PRs/branches de sessões paralelas ANTES de implementar** — a colisão só apareceu no `git log -1` do bench CT 100, com a implementação pronta. Custo recuperado convertendo em delta empilhado, mas o check (`gh pr list` + `git log -3` — nota de memória "sessões paralelas" já existia) devia vir no início.
2. **Symlink de `vendor/` em worktree do container NÃO serve pra testar classe alterada** — o autoload do composer resolve os paths relativos ao checkout principal, então os testes novos rodam contra a classe VELHA (4 fails fantasma "Undefined array key"). Testar no checkout do bench (checkout da branch + restaurar a original depois).
