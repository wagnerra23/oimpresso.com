---
date: "2026-07-01"
time: "16:45 BRT"
slug: sdd-trilho-b-p02-armado-p04-piloto
tldr: "Trilho B do SDD avançou: P02 armou o baseline da full-suite (floor 298, armed:true — catraca da métrica-mãe morde no main). Diagnóstico da nightly achou o root-cause do floor (~57% = cascata FK de isolamento: RefreshDatabase apaga o seed biz=1). Piloto P04 (#3507) ship: self-healing seed no TestCase. Nightly manual 20260701-132941 EM ANDAMENTO pra medir floor 298→?."
prs: [3500, 3503, 3505, 3507]
related_adrs: [0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0279-sdd-medir-governar-floor-nightly, 0101-tests-business-id-1-nunca-cliente]
next_steps: ["Ler floor_count do run 20260701-132941 (monitor bg bn5eug0jl avisa) vs baseline 298; se cair com skipped estável → escalar clusters restantes (business/owner 121, nfe_certificados 22); se não → reabrir hipótese via run.log"]
---

# SDD Trilho B — P02 armado + P04 piloto (self-heal seed) + nightly medindo

## Estado MCP no momento
- **Cycle:** nenhum ATIVO em COPI (sessão OFF-CYCLE — governança/SDD, não toca goals de receita).
- **my-work:** 8 tasks REVIEW (US-TR-305/306/307/309/310/311, US-PG-008, US-FIN-023) — nenhuma desta sessão.
- Trabalho todo em branches off `origin/main` (worktree `eloquent-mestorf-6d9cf1`).

## O que aconteceu
Pergunta de abertura: *"o trilho B do SDD já pode começar? o que falta?"*. Trilho B (full-suite → `required`/R1) = `P01→P02·P03→P04→R1`. P01/P03 já executados (01-jul); faltavam **P02** (armar baseline) e **P04** (burn-down).

1. **P02 executado + merged (#3500):** armei `full_suite_pass_rate` no baseline — `not_yet_measured/armed:false/valid:0` → `measured/value:298/armed:true/valid:3/direction:down`. Valor capturado da fonte real (branch órfã `nightly-floor@554047dd06`, interseção de 3 runs 28/30-jun+01-jul). Counterfactual provado local (floor 298 armado → exit 0; floor 320 → 🔴 exit 1; pré-arme → impune; `sdd-floor-read.test.mjs` 8/8). Gate CI `SDD scorecard ratchet (GT-G3)` verde. **A catraca da métrica-mãe agora MORDE.**

2. **Diagnóstico P04 (read-only, CT100 junit 20260701):** o floor 298 = 1120 falhas de testcase, **~57% erros de banco** (593 QueryException + 32 unique + 19 model-not-found). Fundo: **454 FK "Cannot add or update a child row" (73%)** em `fk_vehicles_business`/`roles`/`users_business_id_foreign`. **ROOT-CAUSE:** no nightly full-suite (MySQL persistente), o 1º teste **RefreshDatabase** dá `migrate:fresh` e **apaga o seed biz=1**; os testes que dependem do seed persistente (hardcoded `business_id=1`) quebram FK. **OficinaAuto (132)/roles(52)/users(29) são VÍTIMAS, não causa.** Registrado em #3505.

3. **Piloto P04 ship + merged (#3507):** fix cirúrgico — `FullSuiteMinimalTenantSeeder` (espelha `ct100-fullsuite.sh`) + `TestCase::setUp()::healCanonicalTenantIfWiped()` que recompõe biz=1 quando sumiu. **Discriminador seguro `transactionLevel()`:** testes RefreshDatabase (level>0) gerenciam o próprio DB → NÃO tocados; dependentes do seed (level 0) → curados. mysql-only + idempotente + try/catch. **Não-regressivo por construção** (só age onde já falhava).

4. **Nightly manual disparado (`20260701-132941`, PID 2811932):** rodando `origin/main` com o fix (seeder + método confirmados no código sincronizado). Fase [6/7] pest ao fechar a sessão. **Monitor bg `bn5eug0jl`** avisa quando fechar.

## Artefatos gerados (4 PRs merged)
- **#3500** `governance/sdd-scorecard-baseline.json` (+8/-6) — P02 arma o floor.
- **#3503** `memory/requisitos/_Governanca/roadmap/P02-*.md` — bookkeeping proposed→executed.
- **#3505** `memory/requisitos/_Governanca/roadmap/P04-*.md` (+23) — distribuição real + root-cause.
- **#3507** `database/seeders/FullSuiteMinimalTenantSeeder.php` (novo) + `tests/TestCase.php` (+50) — piloto self-heal.
- Task spawnada `task_c412ad21` (recall-eval drift) → resolvida por outra sessão em **#3511** (golden-set estava errado; os 4 ADRs estão vivos).

## Persistência
- git: 4 PRs merged + este handoff (webhook→MCP ~2min).
- Estado externo VIVO: run CT100 `20260701-132941` + monitor bg `bn5eug0jl` (efêmeros — a prova durável é o `floor_count` que o run empurra pra branch órfã `governance/nightly-floor`).

## Próximos passos pra retomar
1. **Ler o resultado do nightly:** `tailscale ssh root@ct100-mcp "cat /opt/oimpresso-fullsuite/runs/20260701-132941/summary.json | python3 -c 'import json,sys;print(json.load(sys.stdin)[\"totals\"])'"` + floor novo em `git show origin/governance/nightly-floor:governance/nightly-floor.json`.
2. Comparar vs baseline (floor **298**; run 01-jul-0200 failed 336/errors 784/skipped 2789). **Anti-trapaça: `skipped` NÃO pode subir.**
3. Se floor cair proporcional → escalar pros clusters restantes (`business/owner` 121, `nfe_certificados` 22, cauda). Se não cair → reabrir hipótese (checar no run.log se o self-heal disparou).

## Lições catalogadas
- **O floor NÃO é 298 bugs independentes** — é 1 cascata de isolamento (RefreshDatabase misto vs seed persistente). Diagnóstico por assinatura de exceção (junit) > adivinhar por módulo. O `file` do summary.json mistura suite-name com path (buckets garbled) — usar junit pra causa.
- **`transactionLevel()` é o discriminador limpo** entre teste RefreshDatabase (>0) e dependente-de-seed (0) — permite fix não-regressivo sem tocar os 84 RefreshDatabase.
- **Apples-to-apples:** disparei o nightly com o script instalado como-está (não atualizei) pra isolar a variável do fix — mudar script+código confundiria o delta.
- **AskUserQuestion de arquitetura técnica é bloqueada** (hook `block-askq-execution-menu`) — decisão técnica se RECOMENDA e executa; Wagner valida, não calcula.

## Pointers detalhados
- Roadmap: `memory/requisitos/_Governanca/roadmap/_ROADMAP.md` (P01-P13) + P02/P04 docs.
- Diagnóstico completo: PR #3505 body + este handoff §"O que aconteceu".
- Baseline armado: `governance/sdd-scorecard-baseline.json` `full_suite_pass_rate.nota_armamento`.
