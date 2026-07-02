---
date: "2026-07-02"
time: "16:19 BRT"
slug: ragas-real-transport-mergeado-aguarda-cron
tldr: "Transporte ragas_real_uptime (nightly-floor ADR 0279) mergeado em PR #3612 e em prod. measureRagasRealUptime() lê órfã governance/ragas-real-trend e devolve not_yet_measured honesto até o 1º run real do cron jana:ragas-real-eval em 2026-07-05. Deploys travados/falhos do dia se curaram; prod HTTP 200."
prs: [3612]
related_adrs: [0318-ragas-eval-real-mata-tautologia-ct100-staging, 0279-sdd-medir-governar-floor-nightly, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
next_steps: ["A partir de 2026-07-05 (dom): checar se o cron rodou e publicou na órfã — tailscale ssh root@ct100-mcp \"docker exec oimpresso-staging tail -30 storage/logs/ragas-real-eval.log\" + git ls-remote origin refs/heads/governance/ragas-real-trend"]
---

# RAGAS real — transporte mergeado, aguarda 1º run do cron (2026-07-05)

## Estado MCP no momento do fechamento

- **Cycle ativo:** nenhum (`brief-fetch` #298 → "Cycle: —")
- **HITL pending Wagner:** 2 (FIN-004 cobrança ROTA LIVRE · runbook on-prem pós-Gold)
- **Decisões 24h:** ADRs 0316/0317/0318 · 112 commits · 0 incidentes
- **main HEAD:** `0d131577e7` (#3624)

## O que aconteceu

Sessão curta de **verificação + fechamento de loop**. O entregável — transporte da métrica `ragas_real_uptime` do scorecard SDD — já havia sido implementado e **mergeado em [PR #3612](https://github.com/wagnerra23/oimpresso.com/pull/3612)** (`fcb9319a56`, 11:59Z). Esta sessão confirmou o estado real (não o plano):

1. **Código em `main` confere com o escopo** — `measureRagasRealUptime()` em `scripts/governance/sdd-scorecard.mjs` lê `governance/ragas-real-trend.json` (transporte publicado pela órfã `governance/ragas-real-trend`, write-side `scripts/tests/ct100-ragas-publish.sh` dom 08:30 BRT), calcula % de semanas com run válido (target ≥95%). `baseline_rule: '1ª medição real da fonte, nunca do plano (anti-stale)'`.
2. **Estado atual = `not_yet_measured` (honesto)** — a órfã `governance/ragas-real-trend` **ainda não existe** e `storage/logs/ragas-real-eval.log` **não existe** no CT100. Correto: o cron `jana:ragas-real-eval` (dom 07:00 BRT, `environments(['staging'])`) tem **1ª execução em 2026-07-05**. Ausente/JSON inválido/sem `weeks[]` → todos caem em `notYet` (nunca mente "0%").
3. **Deploys do dia se curaram** — 1 deploy falhou (12:18Z, PR #3621 loop IA-OS #3); os seguintes (12:32/12:41) e o de 16:06Z (`0d13157`) deram **success**. Watcher do PR #3612 travou ~18min em `UNKNOWN/DIRTY` mas destravou e mergeou.
4. **Prod saudável** — `https://oimpresso.com/login` → **HTTP 200**.

## Artefatos gerados nesta sessão

- Nenhum código novo (entregável já mergeado em #3612). Apenas este handoff.

## Persistência

- **git:** #3612 já em `main` + prod. Este handoff via PR próprio.
- **MCP:** propaga via webhook GitHub→MCP após push (~2min).

## Próximos passos pra retomar

- **Gatilho de relógio (2026-07-05, dom):** verificar 1º run real do cron.
  - `tailscale ssh root@ct100-mcp "docker exec oimpresso-staging tail -30 storage/logs/ragas-real-eval.log"`
  - `git ls-remote origin refs/heads/governance/ragas-real-trend` (órfã deve nascer)
  - Se rodou sem `OPENAI_API_KEY` → SKIP honesto exit 0 = **run inválido** naquela semana (reportar fiel, não inflar).
- **Armar baseline (ADR 0275 §3):** só após **3 medições válidas consecutivas** (≥ 2026-07-05 / 07-12 / 07-19). Aí PR editando `governance/sdd-scorecard-baseline.json` na receita dos PRs #3586/#3603 (floor + `nota_armamento` com hashes + counterfactual exit 1 local).

## Lições catalogadas

- Sessão resumida herdou tarefa **já concluída em background** — antes de "continuar", conferir `origin/main` fresco (`git log --oneline origin/main | grep <tema>`) evitou reimplementar o que já estava mergeado.
- `not_yet_measured` é o estado **correto e honesto** enquanto o write-side não publicou — não é bug, é o anti-stale funcionando (mentir "0" ou copiar do plano seria a falha).

## Pointers detalhados

- Read-side: `scripts/governance/sdd-scorecard.mjs` (`measureRagasRealUptime`, ~L186)
- Write-side: `scripts/tests/ct100-ragas-publish.sh` + `scripts/tests/ragas-trend-compute.mjs`
- Comando eval: `Modules/Jana/Console/Commands/JanaRagasRealEvalCommand.php` · cron em `app/Console/Kernel.php` (~:492)
- Baseline honesto: `governance/jana-ragas-real-baseline.json` · ADR 0318 · pattern nightly-floor ADR 0279
