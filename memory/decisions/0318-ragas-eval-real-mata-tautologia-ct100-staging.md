---
slug: 0318-ragas-eval-real-mata-tautologia-ct100-staging
number: 318
title: "RAGAS eval real da Jana — mata a tautologia answer=ground_truth, mede saída de verdade no CT 100 staging"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-01"
accepted_via: "Wagner 2026-07-01: apresentado o diagnóstico (gate RAGAS existente é tautológico nos 3 caminhos) + recomendação → escolheu '1' (construir o eval real, custo LLM ~$1-2/mês OK, placement CT 100 staging) → 'vai' (seguir pro loop-close: agenda + baseline + honestifica gate PR)."
module: jana
tags: [ia, jana, ragas, eval, avaliacao, tautologia, gate, ct100, staging, loop-ia-os, antiteatro]
supersedes: []
superseded_by: []
related:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0035-stack-ai-canonica-wagner-2026-04-26
  - 0053-mcp-server-governanca-como-produto
  - 0093-multi-tenant-isolation-tier-0
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

# ADR 0318 — RAGAS eval real da Jana (mata a tautologia, mede no CT 100 staging)

## Contexto

O item **#2 do loop IA-OS** ("RAGAS gate em CI") estava marcado pendente. A investigação (sessão 2026-07-01) revelou que o problema **não era "falta o gate"** — o gate existe (`jana-ragas-gate.yml` + `jana:ragas-ci-eval` + gold-set de 51 perguntas + baseline). O problema é que **os 3 caminhos de avaliação eram tautológicos**:

| Caminho | Onde |
|---|---|
| `JanaRagasCiCommand` (gate PR + canary) | `answer = context = ground_truth` |
| `KbAnswerRelevancyTest` | `answer = context = ground_truth` |
| `BriefDiarioFaithfulnessTest` | `answer = context = ground_truth` |

Medir faithfulness de `answer=ground_truth` contra `context=ground_truth` é perguntar *"o ground_truth é fiel a si mesmo?"* → **~1.000 sempre**, mesmo em `RAGAS_MODE=real`. É a tautologia banida pela [ADR 0271](0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md) e por `memory/proibicoes.md` §"Teste que deriva do código". **Prova ao vivo:** rodando o gate existente em modo real neste ciclo, faithfulness cravou **1.000**; o eval real (abaixo) dá **0.69**. O gap de 0.31 é a medida exata do teatro.

Por que travou no "W29+": as perguntas do gold-set são fatos de ADR/governança. Responder de verdade exige **Meilisearch + `mcp_memory_documents` populada + LLM**. O CI do GitHub é `sqlite :memory:` efêmero, **sem KB** — lá o retriever não tem o que recuperar. Logo o eval real **não roda no CI per-PR**.

## Decisão

1. **Pipeline real reusável.** Extrair `KbAnswerService` de `KbAnswerTool` (retrieval em `mcp_memory_documents` + síntese `gpt-4o-mini` via `KbAnswerAgent`). Tool delega — comportamento preservado (SoC brutal §5). (PR #3516.)

2. **Comando `jana:ragas-real-eval`.** Por pergunta do gold-set, gera `answer` REAL (KbAnswerService) + `context` REAL (retriever) → julga com `RagasJudgeService`. **Antitautologia dura:** sem `OPENAI_API_KEY` ou sem contexto recuperado → **SKIP honesto** (exit 0 neutro), nunca score inventado nem `answer=ground_truth`.

3. **Roda no CT 100 staging, não no GitHub CI.** Agendado em `app/Console/Kernel.php` semanal (dom 07:00 BRT) com `environments(['staging'])` — **de propósito NÃO `['live']`**: o corpus (1153 docs) + `OPENAI_API_KEY` vivem no CT 100 staging (`APP_ENV=staging`). Em `'live'` (Hostinger shared hosting) não há Meilisearch/OPENAI → seria **ghost** como o `jana:recall-eval --mode=real` (gated `['live']`, dormant por construção). Aqui roda de verdade.

4. **Gate PR do GitHub = smoke de plumbing, não quality gate.** `jana-ragas-gate.yml` rebatizado e **forçado a MOCK em `pull_request`** (o secret `RAGAS_MODE=real` fazia todo PR Jana queimar ~$1,20/mês num 1.000 falso). Real só via dispatch/cron manual. Sem KB no CI, ele não pode medir qualidade — só que o plumbing roda.

5. **Baseline honesto.** `governance/jana-ragas-real-baseline.json` (N=51, CT 100 staging, modo real):

   | Métrica | Valor |
   |---|---|
   | faithfulness_avg | **0.6916** |
   | relevancy_avg | **0.8039** |
   | context_recall_avg | **0.3839** |
   | n_evaluated / no_context / synth_failed | 51 / 0 / 0 |

   Thresholds de regressão = baseline menos margem (**0.65 / 0.75**), não o 0.80/0.75 aspiracional (que falharia toda semana = ruído). Estado-da-arte: *"start lower, establish baseline, tighten iteratively"*.

## Consequências

**Positivas:**
- O eval mede a saída **de verdade** da Jana; a tautologia morreu.
- Já rendeu um achado real: **context_recall 0.38** — o retriever FULLTEXT cobre ~38% do que o ground_truth precisa (hybrid `docs_pipeline` off). O eval tautológico escondia isso atrás de ~1.0.
- Fim do desperdício de ~$1,20/mês no gate PR tautológico.
- Loop IA-OS #2 fechado **honesto** (não flipando "done" sobre teatro).

**Negativas / dívida assumida:**
- O eval real **não protege o PR** (não roda no CI) — a régua vive no cron staging. Aceito: sem KB no CI, um gate PR de qualidade é impossível; o smoke de plumbing + o cron real é o par honesto.
- v1 usa thresholds absolutos (baseline-menos-margem), não %-regressão vs baseline lida em runtime (como o canary faz). **Follow-up:** fazer o comando ler `jana-ragas-real-baseline.json` e alertar por delta%.
- **Follow-up separado (não bloqueia):** o gap de retrieval (`context_recall 0.38`) — ligar/avaliar o hybrid `copiloto.mcp_search.docs_pipeline` + tuning do FULLTEXT/rerank.

## Alternativas descartadas

- **Tornar o gate existente `required`** — trava o teatro (ADR 0271). Descartado.
- **Rodar o eval real no GitHub CI** — impossível sem KB no runner efêmero.
- **Gate `['live']`** (espelhar o recall-eval) — seria ghost (Hostinger não tem a infra). Escolhido `['staging']` pra rodar de verdade.
