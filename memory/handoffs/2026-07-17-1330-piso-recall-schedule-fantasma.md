---
date: "2026-07-17"
time: "1330"
slug: piso-recall-schedule-fantasma
tldr: O piso de context_recall (US-COPI-136) foi entregue — o comando media o recall e jogava fora; agora ele morde e o baseline é dono único dos pisos. Achado que a US não previa (US-COPI-140) — os 2 evals de qualidade da Jana nunca dispararam sozinhos no CT 100 (nada invoca schedule:run); instalei o invocador e provei disparando sozinho, mas o DoD só fecha domingo. Errei a contagem de schedules 3× parseando código em vez de perguntar ao runtime.
owners: [W]
prs: [4412, 4426]
us: [US-COPI-136, US-COPI-140, US-COPI-137]
related_adrs: [0318-ragas-eval-real-mata-tautologia-ct100-staging, 0302-doneness-lint, 0062-separacao-runtime-hostinger-ct100]
---

# Handoff — piso de context_recall + schedule fantasma

## O que fechou (2 PRs MERGED, CI verde nos dois)

- **[#4412](https://github.com/wagnerra23/oimpresso.com/pull/4412) — US-COPI-136 (piso de context_recall).** O `jana:ragas-real-eval` media `context_recall` e **jogava fora** (impresso `(info)`, fora do `gatePass`); podia cair de 0,3839 → 0,20 sem alarme, comando com **zero testes**. Piso **0.36** (= `min(3 runs reais) × (1−6%)`, mesma margem dos irmãos, folga 2,1× o spread). Baseline virou **dono único** dos pisos (`resolveThresholds()` lê `thresholds_regressao` em runtime — fecha follow-up da ADR 0318; antes decorativo + duplicado no signature `0.80` que fabricava `fail`). Bite-test `RagasRealEvalGateTest` (`gateVerdict()` pura): **7 passed** no Pest real do CT 100, mordida provada.
- **[#4426](https://github.com/wagnerra23/oimpresso.com/pull/4426) — US-COPI-140 (invocador) + errata + 2 lições.** Ver abaixo.

## O achado que a US não previa (US-COPI-140)

Os **2** schedules de qualidade `environments(['staging'])` (`ragas-real-eval`, `recall-eval --mode=real`) **nunca dispararam sozinhos**: `schedule:run` = 0 em todo cron do CT 100; container sem cron/supervisord. Todo número do baseline veio de run **manual**. O transporte (`ct100-ragas-publish.sh`) está vivo e relê o report velho toda semana → órfã `ragas-real-trend` congelada em 1 semana desde 04/07.

**Fix (caminho A, decisão [W]):** `scripts/tests/ct100-jana-evals.sh` + cron `0 6 * * 0` no host CT 100 + sync anti-drift no `self-update.sh` + selftest hermético no CI. **Provado disparando sozinho** (cron de teste one-shot, 11:27:01 → report escrito pelo cron). `drift-sentinel` fora (é `['live']`, já roda em prod).

**DoD pendente por construção:** "semana nova na órfã que ninguém rodou à mão" só existe **domingo 2026-07-19 06:00**. US-140 = `doing`/`_pendente_`. **Confirmar domingo.**

## Aberto pro próximo (prioridade)

1. **US-COPI-140 DoD (domingo 19/07):** conferir que `governance/ragas-real-trend` ganhou semana nova **sem** run manual. Se sim, flip US-140 → `done` + âncora `_parcial_`→real (aí o doneness-lint aceita).
2. **Chip C3 — `drift-sentinel` é alarme cego.** Roda toda semana em prod (`['live']`) mas compara contra baseline mock `0.85` ± `0.25` (de 16/05, nunca regravado) vs real ~0,70 → **verde por construção, falsa segurança**. Regravar via `jana:drift-sentinel --update-baseline` real no CT 100 (não depende de infra — já roda). Subiu de higiene pra conserto.
3. **US-COPI-137 — eval online 5% (não feito, provado VIÁVEL).** Prod tem worker (`QUEUE_CONNECTION=database`, `jobs`=0) → job amostrando traces roda de fato. Tier 0: `PiiRedactor` antes do juiz (biz≠1, LGPD).
4. **Chip drift staging (`task_0259bea3`, rodando em sessão paralela):** checkout do CT 100 com correção hand-aplicada, 4d atrás da main.

## Lições catalogadas (§5 proibicoes.md, no #4426)

- **Deduzir quem roda parseando código quando o runtime sabe** — errei a contagem de schedules 3× (o nº errado embasou a pergunta A/B ao [W]); acertei na 1ª que perguntei a `Event::runsInEnvironment()`. "Varri o arquivo" ≠ "varri o sistema" (módulos registram schedules próprios: 82 eventos, não 65).
- **`crontab -l` vazio em host gerenciado** ≠ ausência (o binário nem existe na Hostinger); medir pela consequência.

## Estado MCP no momento do fechamento

- `cycles-active` (COPI): **nenhum cycle ativo**.
- `my-work` (@wagner): 30 tasks (10 review, 8 blocked incl. dormentes NFe Gold, 12 todo — várias p0 de ROTA LIVRE/RecurringBilling). As US-COPI-135/136/137/140 vivem no `SPEC.md` (git), não no backlog MCP.
- `decisions-search`: não consultado (sessão não criou ADR — as decisões couberam em US existentes + §5).
