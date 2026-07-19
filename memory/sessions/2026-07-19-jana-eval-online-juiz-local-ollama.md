---
date: "2026-07-19"
topic: "Liga eval online da Jana (US-COPI-137): juiz local Ollama + fix do wiring copiloto.*"
hour: "11:54 BRT"
authors: [C]
prs: [4536]
related_adrs:
  - "0318-ragas-eval-real-mata-tautologia-ct100-staging"
  - "0093-multi-tenant-isolation-tier-0"
---

## TL;DR

Chip da grade de réguas 2026-07-18 (eixo `qualidade-drift-IA-prod` 4,0 — "zero eval no tráfego real"). Objetivo: **ligar** a medição de qualidade sobre a resposta que a Jana serve ao cliente, com **juiz local** no Ollama do CT 100 (não provider pago). Entregue em [PR #4536](https://github.com/wagnerra23/oimpresso.com/pull/4536), **parado no PR** (não mergeado — ligar em prod é decisão LGPD do [W]).

## O que estava quebrado (achado verificado)

1. **Juiz local não existia** — `judge=local` (default LGPD, zero-egress) só fazia `SKIP`. A única medição era offline (gold-set).
2. **Bug de wiring** — Job e Listener liam `config('jana.online_eval.*')`, mas o bloco `online_eval` mora em `Modules/Jana/Config/config.php` → merged como **`copiloto.*`** (o namespace `jana.*` só recebe `retention`/`memoria`). Ou seja: **`enabled=true` no `config.php` não ligava nada** — os readers caíam no default `false`/`local`. "Ligar a medição" era impossível sem esse fix. Provado por arquivo (não há `config/jana.php`, nenhum outro merge de `online_eval` sob `jana`).

## O que foi feito

- **`OllamaRagasJudge`** (`extends RagasJudgeService`) — juiz RAGAS local no Ollama self-host CT 100 (`/api/chat`), ZERO egress. Só troca o transporte (OpenAI → Ollama); herda os prompts do pai (SoC — sem duplicar prompt). Serve o eval online (faithfulness) e `jana:ragas-real-eval --judge=local` (4 métricas).
- **Honestidade > cobertura** — falha (Ollama down / sem modelo de chat / JSON inválido) → lança `JudgeUnavailableException` → o consumidor **PULA sem gravar `0.0`** (que numa queda de infra viraria falso alarme de "resposta infiel"). Difere do pai, que devolve 0.0 em erro.
- **`JudgeTraceOnlineJob`** — `judge=local` (default) agora mede de verdade. `PiiRedactor` roda **ANTES** do juiz nos 2 caminhos (LGPD Tier 0, defesa em profundidade — mesmo local).
- **Wiring corrigido** — Job/Listener leem `copiloto.online_eval.*`. Editar o `config.php` passa a ser a fonte real.
- **`jana:ragas-real-eval --judge=local`** — roteia o julgamento pro juiz local (a síntese segue OpenAI). A extensão de comando que o chip pediu.
- **Config-as-code** (sem novo `env()` — baseline Larastan de 93 env() no `config.php`): `online_eval.local.{url,model,timeout}` hardcoded.
- **Lane fix (US-COPI-138):** os 2 testes (Http::fake, DB-less → sqlite-safe) entraram no `.github/ci-sqlite-pest.list` — antes o `JudgeTraceOnlineJobTest` não rodava em NENHUMA lane per-PR (guard que nenhum workflow executa = mentira).

## Evidência (CT 100 — real, não narração)

A **classe PHP `OllamaRagasJudge`** rodada contra o Ollama REAL do CT 100 (modelo `qwen2.5:3b`, puxado no `ollama-embedder`, antes só tinha embedders) deu:

```
FIEL score (0..1)      = 1.000
ALUCINADA score (0..1) = 0.000
OK: discrimina (fiel > alucinada)
```

Produz score real e **discrimina** (resposta fiel → 1.0; alucinada → 0.0), com o exato shape (`message.content` = JSON com `score`) que o código parseia. ~16–22s/chamada em CPU — ok pro Job async (amostra 5%). Fatos neutros (sem PII, sem BRL). PII redigida antes do juiz provada por teste (PiiRedactor real).

## CI + notas

- **Todos os checks required verdes** (Pest Unit, Pest Jana MySQL, Larastan, PII scan, anchor-lint, Tier-0 guards, memory-health, Governance Gate). Snapshot final: 90 pass · 4 skipping · 1 fail.
- 1 red = `module-grades-gate` (**advisory**, ADR 0314 demoveu de required; regressão de 1 ponto na nota composta da Jana, não-Tier-0). Label `module-grades-allowed-regression` aplicado.
- **1 bug pego pelo CI e corrigido:** `OllamaRagasJudgeTest` chamava `Http::fake()` 2× na mesma URL num teste só (o 1º stub ganha → o caso `score=-0.5` recebia o `1.7` e voltava 1.0). Split em 2 testes.

## Escopo NÃO feito (de propósito)

- **Não mergeado** (DoD = para no PR). Ligar `enabled=true` em prod é decisão LGPD do [W].
- **Calibração vs humano** ([W]/[E], 100–300 labels) é chip separado (#4). O mecanismo nasce **sem gate** — só publica número no trace (ressalva do adversário na US: RAGAS não-calibrado pode trocar teatro por teatro).
- **Não rodei o comando `--judge=local` end-to-end no CT 100** — exigiria checkout do branch inteiro no staging (que está numa branch WIP de outra sessão). O juiz que ele usa está provado ao vivo; a extensão é glue fino.

## Refs

- [PR #4536](https://github.com/wagnerra23/oimpresso.com/pull/4536) · US-COPI-137 · [ADR 0318](../decisions/0318-ragas-eval-real-mata-tautologia-ct100-staging.md) · [ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md)
- Arquivos: `Modules/Jana/Services/Ragas/OllamaRagasJudge.php` · `JudgeUnavailableException.php` · `Jobs/Telemetry/JudgeTraceOnlineJob.php` · `Listeners/Telemetry/LangfuseAgentTelemetryListener.php` · `Config/config.php` · `Console/Commands/JanaRagasRealEvalCommand.php`
