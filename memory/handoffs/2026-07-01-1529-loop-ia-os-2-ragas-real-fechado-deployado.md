# Handoff — Loop IA-OS #2 (RAGAS gate) fechado honesto + deployado no staging

**Quando:** 2026-07-01 15:29 BRT
**Sessão:** vigilant-banach-8eeb7d
**Autor:** [CC] (Wagner aprovou escopo: "1" → build eval real · "vai" → loop-close · "a" → deploy staging)

## TL;DR

O item **#2 do loop IA-OS** ("RAGAS gate em CI") estava marcado pendente — mas o diagnóstico revelou que o problema não era "falta o gate": **o gate existia e era teatro tautológico**. Os 3 caminhos de eval (`JanaRagasCiCommand` + `KbAnswerRelevancyTest` + `BriefDiarioFaithfulnessTest`) mediam `answer = context = ground_truth` → faithfulness ~1.000 sempre, mesmo em modo real. Tautologia banida por [ADR 0271](../decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) + proibicoes §"Teste que deriva do código". Fechado **honesto**, não flipando "done" sobre teatro.

## O que foi feito (2 PRs merged + deploy)

- **[#3516](https://github.com/wagnerra23/oimpresso.com/pull/3516)** (mata a tautologia): extrai `KbAnswerService` de `KbAnswerTool` (retrieval `mcp_memory_documents` + síntese gpt-4o-mini via `KbAnswerAgent`, Tool delega — comportamento preservado). Novo comando `jana:ragas-real-eval` mede a saída REAL da Jana; **antitautologia dura** — sem `OPENAI_API_KEY`/sem contexto → SKIP honesto (exit 0 neutro), nunca score inventado. Fix PHPStan: narrow `instanceof App\User` (mismatch suprimido no baseline se perdeu ao mover pro service).
- **[#3521](https://github.com/wagnerra23/oimpresso.com/pull/3521)** (ops + conhecimento): agenda `jana:ragas-real-eval` semanal (dom 07:00 BRT) em `environments(['staging'])` — **de propósito NÃO `['live']`** (em live/Hostinger não há Meilisearch/OPENAI → seria ghost como o `recall-eval --mode=real`). Honestifica o gate PR `jana-ragas-gate.yml`: rebatizado "smoke (tautológico)" + **força mock em pull_request** (parava de queimar ~$1,20/mês num 1.000 falso). Baseline honesto `governance/jana-ragas-real-baseline.json` + **ADR 0318** + tracker `.claude/loop-fechar-o-loop.json` loop-2 `done=true`.
- **Deploy staging (CT 100):** `oimpresso-staging` estava na branch `feat/perfil-meu-perfil` (não main). Reset → `origin/main`, `build.sh` (npm build; **composer não roda na imagem** — PSR-4 acha as classes novas sem dump, meus PRs não add dependência), **21 migrations aditivas rodadas** (DONE), caches limpos.

## Baseline honesto medido (CT 100 staging, N=51, modo real)

| Métrica | Real | Gate tautológico escondia |
|---|---|---|
| Faithfulness | **0.6916** | 1.000 (falso) — gap 0.31 = a medida do teatro |
| Answer Relevancy | **0.8039** | 0.851 |
| Context Recall | **0.3839** | — (gap de retrieval real) |

Thresholds do schedule = baseline−margem (0.65/0.75), alerta em regressão, não no 0.80 aspiracional.

## Estado VIVO / ao vivo (prova, não promessa)

- `schedule:list` no staging mostra: `0 7 * * 0 php artisan jana:ragas-real-eval --json --threshold-faithfulness=0.65 --threshold-relevancy=0.75 · Next Due: em 3 dias` — **NÃO é ghost, roda domingo**.
- Smoke do comando **deployado** (não cópia): `mode:real`, custo $0.0136, 8/8 avaliadas, output não-tautológico (faithfulness varia 0→1).
- `https://staging.oimpresso.com/login → HTTP 200` pós-deploy.

## Achado real (follow-up registrado)

`context_recall 0.3839` — o retriever FULLTEXT cobre ~38% do que o ground_truth precisa (hybrid `copiloto.mcp_search.docs_pipeline` off). O eval tautológico escondia isso atrás de ~1.0. **US-COPI-130** criada (RAG quality — subir context_recall; meta ≥0.60; medir antes→depois via `jana:ragas-real-eval`).

## Aberto / próximo

- **US-COPI-130** (RAG quality) — no MCP DB; SPEC.md canon: o MCP server escreveu na cópia dele (CT 100), sync git do bloco a reconciliar no fluxo normal.
- 1º real-eval autoritativo dispara **domingo (dom 07:00 BRT)** no staging → alimenta `storage/logs/ragas-real-eval.log`. Verificar o trend depois.
- Fazer o comando ler o baseline pra alertar por %-regressão (hoje usa threshold absoluto baseline−margem).

## Estado MCP no momento do fechamento

- `cycles-active` (COPI): **nenhum cycle ATIVO**.
- `decisions-search "RAGAS eval tautologia loop"`: **ADR 0318** já indexada no MCP (webhook git→MCP sincronizou pós-merge) + 0051/0041 (stack QA-IA/OTel).
- `my-work` (início da sessão): 30 tasks ativas (8 review, 8 blocked, 14 todo). HITL pendente Wagner: FIN-004, US-NFE-048.
- `sessions-recent`: tool não exposta neste token MCP.
