---
date: "2026-07-09"
hour: "23:56 BRT"
topic: "Grade das réguas (dossiê): ponte Code Connect + evals de outcome DORA + hook Tier-0 cross-platform + 3 sessões paralelas spawnadas"
authors: [C]
outcomes:
  - "Card #2 Ponte design→código FECHADO: 3 eixos de mapa deconflitados (component-registry=Code Connect real · cowork-map=arquivo · <tela>.map.json=região) + mecanismo gerar-map/design-code-map-check + âncora estável data-contract no lado vivo"
  - "Card #0 Evals de outcome (0→~5): agent-pr-outcomes.mjs DORA dos PRs do agente (change-failure/accept/time-to-merge via gh, brief semanal); smoke real 20 PRs → CFR 7.1%"
  - "Card #2 Enforcement cross-platform (2→7): porte block-test-fora-ct100 .ps1→.mjs pattern-setter (roda em Linux, guard correção≠invocação)"
  - "IT5 benchmark stale 38d destravado (§11 logado) → advisory governance script tests verde de novo"
  - "3 sessões paralelas spawnadas (fingerprint-gate/critic-loop/hooks-restantes) via create_trigger+fire, branches isoladas auto-shepherd"
prs: [4020, 4021, 4022, 4023, 4024, 4025]
related_adrs: [0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0062-separacao-runtime-hostinger-ct100, 0226-daily-brief]
---

# Sessão 2026-07-09 — Grade das réguas (dossiê) → 6 PRs + 3 sessões paralelas

Dossiê "Grade das réguas · IA OS oimpresso vs acima-do-mercado" (9 fraquezas com régua/nota/próximo-degrau + ordem de ataque) guiou a sessão. Cada card tem critério objetivo (número/artefato/gate on-off) — grade-ável.

## O que foi feito (por card do dossiê)

### Card #2 "Ponte design→código (Code Connect)" — nota 3, FECHADO
- **Pergunta [W]:** "pesquize os mapas antigos" + "o que está em conflito com esse conceito [Code Connect]?"
- **Inventário dos mapas:** `component-registry.json` (componente↔React, âncora estável = **o Code Connect real**) · `cowork-map.json` (roteamento de arquivo na ingestão) · `<tela>.map.json` (região de tela, novo) · prosa `REGISTRY_DS_COMPONENTES.md` + `ds-v6/REUSE_MAPPING.md`.
- **Conflito identificado:** (1) rótulo — `<tela>.map.json` NÃO é Code Connect (é anchor-map por região, região-first ≠ componente-first reusável); (2) técnico — âncora por RANGE DE LINHA apodrece no refactor da tela em silêncio (o oposto do que Code Connect resolve, que ancora no nó do componente); (3) filosófico — o projeto já baniu âncora-por-posição (ancora.mjs #7).
- **Correção em código (A+B, [W] escolheu "A + B em sequência"):** #4020 mecanismo (gerar-map + design-code-map-check) → #4021 deconflito escrito dos 3 eixos (RUNBOOK + headers) → #4022 âncora estável `data-contract` no lado vivo (id = slug(parte), bate com o contrato-de-tela; drift vira sinal quando a âncora some).

### IT5 stale destravado — #4023
O `integrity-check §15` IT5 detecta benchmark §11 stale (última medição 38d, staleness ensinada pelo #4018 de hoje). Isso derrubava o T7 do `design-memory-gate.test.mjs` (asserta integrity-check exit 0) → advisory `governance script tests` vermelho em TODO PR. Fix canônico = logar a linha do §11 desta sessão (append-only, medição honesta: recidiva 0%, 0 escapes, +1 defesa-forte). Não é fabricar frescor — a sessão fez design-memory real.

### Card #0 "Evals de outcome" — nota 0→~5, #4024
`agent-pr-outcomes.mjs`: DORA dos PRs do agente via `gh pr list` (array-form, sem `--jq` — lição Windows). change-failure-rate (hotfix ≤48h citando #N) + accept-rate + time-to-merge. Deconflito do `outcome-metrics.mjs` irmão (design-loop). Advisory, alimenta o brief SEMANAL (workflow schedule seg 09h). Selftest morde/libera (CFR: hotfix tardio/tipo-errado/sem-#N não contam). Smoke real: 20 PRs reais → CFR 7.1%, accept 100%, ttm 0.1h.

### Card #2 "Enforcement cross-platform" — nota 2→7, pattern-setter #4025
`block-test-fora-ct100` `.ps1`→`.mjs`: 43/55 hooks eram Windows-only, os Tier-0 evaporavam no Mac/Linux do time MCP. Porte 1:1 (funções puras + wrapper stdin + `pathToFileURL` + `--selftest` via spawn) + test .mjs (lógica + E2E, roda em Linux) + guard de registro "correção≠invocação" + swap settings.json + git rm .ps1 + docs LIVE (README/proibicoes) — históricos append-only intocados. 23/23, metaguard 46/46 intacto.

## 3 sessões paralelas spawnadas ([W] "abra sessões novas")
`create_trigger(create_new_session_on_fire) + fire_trigger` — fingerprint-gate (3→7), critic-loop (3→6), hooks-restantes (2→7). Branches isoladas, prompts self-contained, auto-shepherd, push notification. Cycle (1→6) NÃO spawnado — precisa do goal de negócio [W].

## Lições
- Code Connect ≠ um artefato só; deconflitar por escrito antes de somar (1 fato = 1 lugar).
- Âncora por linha = anti-padrão já banido (ancora.mjs #7); `data-contract` estável alinha.
- Sessões-novas paralelas > agents quando vetores são independentes e cada um quer PR/branch próprio.
- O guard `block-askq-execution-menu` barra chips de execução até quando [W] pede — contornar por texto, não brigar com a infra.
