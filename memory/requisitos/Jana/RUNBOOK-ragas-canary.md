---
title: RUNBOOK — Jana RAGAS Canary daily 06:00 UTC
type: runbook
us: US-COPI-116
status: ativo
last_updated: 2026-05-25
owners: ["@wagner"]
related:
  - .github/workflows/jana-ragas-canary.yml
  - .github/workflows/jana-ragas-gate.yml
  - .github/workflows/ragas-gate.yml
  - scripts/jana-ragas-runner.py
  - governance/jana-ragas-baseline.json
  - Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json
  - Modules/Jana/Console/Commands/JanaRagasCiCommand.php
adrs:
  - ADR 0035 (stack IA canônica)
  - ADR 0094 §4 (loop fechado por métrica)
  - ADR 0037 §GAP-2 (RAGAS gate em CI)
---

# RUNBOOK — Jana RAGAS Canary

> Canary diário detecta regressão de qualidade do RAG Jana fora-de-PR (drift silencioso após merges + mudanças de prompt + recall pipeline).

## Por que existe

Workflows pre-existentes cobrem apenas thresholds absolutos:

| Workflow | Trigger | Bloqueia merge? | Detecta drift fora-de-PR? |
|---|---|---|---|
| `jana-ragas-gate.yml` (W28-2) | PR `Modules/Jana/**` + Mon 08:00 UTC | SIM (faithfulness < 0.80 OU relevancy < 0.75) | Só Mon (weekly) |
| `ragas-gate.yml` (W22 MVP) | dispatch + Mon 09:00 UTC | NÃO (alerta) | Só Mon (weekly) |
| **`jana-ragas-canary.yml` (US-COPI-116)** | **Daily 06:00 UTC** + dispatch | **NÃO** (abre issue P0) | **SIM (daily relativo a baseline)** |

A diferença é o sinal: gate absoluto pega regressão *catastrófica* (caiu abaixo de 0.80); canary relativo pega regressão *insidiosa* (caiu de 0.92 → 0.84 em 3 dias — ainda passa o gate mas é drift real).

Estado-da-arte 2026 (Premai/DataVLab): "Set alerting on metric drift so quality degradation surfaces quickly. Alert when 7-day rolling faithfulness drops below 0.75 for production monitoring." Nosso canary é a versão CI desse pattern.

## Como funciona

```
Daily 06:00 UTC cron
  ↓
GitHub Actions runner ubuntu-latest
  ↓
php artisan jana:ragas-ci-eval --json  (mock default — $0)
  ↓ stdout JSON {faithfulness_avg, relevancy_avg, n_questions, cost_usd}
scripts/jana-ragas-runner.py
  ↓ lê governance/jana-ragas-baseline.json
  ↓ calcula delta_pct = (current - baseline) / baseline * 100
  ↓ se ANY delta < -5% → gate_status=fail
canary-result.json
  ↓
GITHUB_STEP_SUMMARY (tabela markdown) + artifact 90d retention
  ↓
Se fail E trigger=schedule → auto-open issue labels [ragas-regression, P0, jana]
```

## Métricas tracked e sua importância

| Ordem | Métrica | Por que importa | Threshold absoluto (gate W28-2) | Threshold relativo (canary) |
|---|---|---|---|---|
| 1 | **faithfulness** | Mede se a resposta gerada é suportada pelo contexto recuperado. Cair = alucinação subindo. **MATA O PRODUTO**. | ≥ 0.80 | -5% vs baseline |
| 2 | **answer_relevancy** | Mede se a resposta endereça a pergunta direta. Cair = generic/off-topic. | ≥ 0.75 | -5% vs baseline |
| 3 | context_precision | Mede chunks bem ranqueados. Info-only (cai com mudança Meilisearch/BGE reranker). | N/A (info) | N/A (não no canary atual) |
| 4 | context_recall | Cobertura ground truth. Info-only. | N/A (info) | N/A (não no canary atual) |

Quando expandir canary pra cobrir context_precision/recall: editar `TRACKED_METRICS` em `scripts/jana-ragas-runner.py` + popular baseline correspondente.

## Operações comuns

### 1. Popular baseline pela primeira vez (Wagner pós-merge)

O arquivo `governance/jana-ragas-baseline.json` nasce zerado. Enquanto values=0, runner trata como N/A e nunca alerta regressão (safe-by-default). Pra ativar de verdade:

```bash
# Mock (zero $, scores deterministicos do mock — útil só pra validar pipeline)
gh workflow run jana-ragas-canary.yml -f update_baseline=true -f mode=mock

# Real (consome ~$0.06 OpenAI judge — usa OPENAI_API_KEY secret)
gh workflow run jana-ragas-canary.yml -f update_baseline=true -f mode=real
```

O step `Update baseline (workflow_dispatch only)` commita o JSON atualizado via `github-actions[bot]` (push direto na branch — checar branch protection main se aplicável).

### 2. Adicionar nova pergunta ao golden set

Editar `Modules/Jana/Tests/Feature/Ai/fixtures/jana-gold-set.json` (V3.0, atualmente 100 questões em 6 buckets). Shape:

```json
{
  "question": "Pergunta em PT-BR (sem PII, biz=1 ou biz=99 only — ADR 0101)",
  "ground_truth": "Resposta canônica descritiva referenciando ADR/RUNBOOK",
  "tags": ["multi-tenant", "governance"]
}
```

Após adicionar:
1. Rodar `php artisan jana:ragas-ci-eval` local pra validar parsing
2. Rebalancear `bucket_coverage` no `_meta` se necessário
3. Recalibrar baseline (passo 1) após PR merged

### 3. Debugar fail CI

Em ordem de probabilidade:

1. **Regressão real** (mais comum) — algum PR mexeu em `Modules/Jana/Services/Retrieval/*`, prompts, ou `BgeReranker`. Investigar últimos 7 commits via `git log --since="7 days ago" Modules/Jana/`.
2. **Baseline desatualizada** — Wagner melhorou os scores (ex: tuning prompt) mas esqueceu de rodar `update_baseline=true`. Verifique se baseline `last_updated` > 30 dias.
3. **Mock mode flaky** — RagasJudgeService::enableMock retorna scores fixos (0.85/0.82), então mock NUNCA deveria regredir. Se canary mock falhou, é bug no runner ou ratchet conceitual no comando.
4. **Real mode + judge LLM instável** — OpenAI gpt-4o-mini retornou JSON malformado (DeepEval research mostra ~3% rate). Tente rerun. Se persistir, considere migrar judge pra DeepEval (mais robusto a NaN — ver search results PremAI 2026).

### 4. Atualizar threshold de regressão

Default é 5% (consensus 2026 — Premai/DataVLab). Pra recalibrar:

- Mudar default no input do workflow YAML (`regression_threshold_pct: '5.0'`)
- Documentar mudança no `_meta.regression_alert_pct_default` do baseline JSON
- ADR opcional se mudança permanente (Constituição §4 loop fechado por métrica)

### 5. Suprimir alerta temporariamente (incident response)

```bash
# Desabilita workflow (Wagner-only — registra incident em memory/sessions/)
gh workflow disable jana-ragas-canary.yml
# ... resolve causa raiz ...
gh workflow enable jana-ragas-canary.yml
```

NUNCA editar baseline pra "esconder" regressão. Append-only canon (Constituição §7 transparência).

## Custos prod

| Cenário | Custo/run | Frequência | Mensal |
|---|---|---|---|
| Daily mock (default) | $0 | 30/mês | $0 |
| Daily real (se Wagner habilitar via RAGAS_MODE=real) | ~$0.06 | 30/mês | ~$1.80 |
| Update baseline real (ocasional) | ~$0.06 | ~2/mês | ~$0.12 |

Total worst-case: ~$2/mês. Aceitável vs custo de regressão silenciosa em prod ($$$).

## Pendente Wagner pós-merge

1. **Popular baseline** — rodar workflow_dispatch `update_baseline=true mode=mock` no primeiro run pra estabelecer baseline. Re-rodar com `mode=real` quando OPENAI_API_KEY secret estiver disponível.
2. **GitHub Secret** — confirmar `OPENAI_API_KEY` configurado no repo (já usado por `jana-ragas-gate.yml`, então provavelmente existe).
3. **Branch protection** — se main exige PR, o step de update_baseline auto-commit precisa de bypass ou bot token com permissão. Alternativa: gerar PR auto em vez de push direto (refator futuro).
4. **(opcional) Slack/Discord webhook** — workflow atual abre GitHub Issue (cf. step `Auto-open issue on regression`). Pra notificação Slack, adicionar step com `slackapi/slack-github-action@v1` + secret `SLACK_WEBHOOK_URL`.

## Refs

- ADR 0094 §4 — loop fechado por métrica (Constituição v2)
- ADR 0035 — stack IA canônica (RagasJudgeService Camada B)
- ADR 0037 §GAP-2 — RAGAS gate em CI (W22 MVP origem)
- [Premai RAG Evaluation 2026](https://blog.premai.io/rag-evaluation-metrics-frameworks-testing-2026/)
- [DataVLab RAG Evaluation 2026](https://datavlab.ai/post/rag-evaluation-methods-metrics-2026-guide)
- [RAGAS docs metrics](https://docs.ragas.io/en/stable/concepts/metrics/available_metrics/)
- [Kalibra (Mar/2026) — statistical regression detection AI agents](https://github.com/khan5v/kalibra)
