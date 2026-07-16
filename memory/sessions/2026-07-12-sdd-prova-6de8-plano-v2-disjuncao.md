---
date: "2026-07-12"
topic: "SDD P04 — prova sharded pousou 6/8 + causa-raiz da classe 1062 achada e mergeada (plano de shards não-disjunto → v2)"
authors: [W, C]
related_adrs: [0279-sdd-medir-governar-floor-nightly, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes]
---

# Sessão 2026-07-12 — Prova 6/8 da nightly sharded + shards-plan v2 (disjunção recursiva)

**Worktree:** `nightly-posfix-burndown` (fresco, `origin/main` @ 42648e68dc). Continuação direta do handoff [2026-07-13-0030](../handoffs/2026-07-13-0030-sdd-shard-survival-8de8-proof-em-voo.md) — "próximo turno DEVE conferir a prova 8/8 em voo".

## TL;DR

A prova 8/8 (run `20260712-212018`) pousou **6/8 shards vivos** — e os 2 mortos (shards 2 e 4) tinham o MESMO modo de morte novo, que diagnostiquei ao vivo, corrigi e mergeei na mesma noite ([PR #4199](https://github.com/wagnerra23/oimpresso.com/pull/4199), Wagner autorizou o merge): **o plano de shards não era disjunto sob a semântica recursiva do pest**.

## O modo de morte 4 (não catalogado no #4188)

- Sintoma: shard morre em **COLETA** com `Test case [Tests\TestCase] can not be used. The folder [...] already uses the test case` e a quarentena gasta as 12 tentativas movendo a pasta inteira sem curar (shard 2: `Modules/Jana/Tests/Feature/TaskRegistry/` · shard 4: `tests/Feature/Domain/Fsm/`).
- Causa: o `shards-plan.mjs` particionava por "arquivos DIRETOS do dir" (disjunto NESSE modelo), mas o executor passa o dir pro `pest`, que varre **recursivo** → pai misto engole as filhas. Medido no repo real: **99/143 unidades re-varridas, 14 pais mistos**.
- Efeito duplo: (a) pai+filha no MESMO shard → arquivo carregado 2× → `uses()` per-file duplicado → coleta morre; (b) pai num shard e filha noutro → teste **executado 2× na noite** contra o MySQL persistente → re-insert de linha unique → **a classe SQLSTATE 1062 da "cascata de isolamento"** (57% dos fails na avaliação de 2026-07-12) + dupla contagem no merge.

## Fix (PR #4199 — MERGED `39801d5850`, 60 checks verdes)

- `shards-plan.mjs` → schema **v2**: `shards[].paths` = args reais do pest (unidade mista expande pros arquivos diretos; folha continua dir). `verifyPlan` ganha checagem `nested` — universe-gate agora FALHA plano aninhado (regressão impossível em silêncio).
- `ct100-fullsuite.sh` → consome `.paths` (fallback `.dirs`); quarentena remove o arquivo movido da lista de args antes do retry.
- Contratos: `shards-plan.test.mjs` 20/20 · `fullsuiteHarness.spec.ts` 17/17. Repo real: 143 unidades → 410 args (281 arquivos + 129 dirs), 0 aninhados.

## Números da noite (medição parcial honesta — 6 shards vivos, `all_shards_measured=false`)

- **889 testcases falhando** (495 failed + 394 errors) em **303 arquivos** · 1.755 skipped · 29 loader-blockers quarentenados (24 = vítimas do plano nos shards 2/4).
- Shard 7 provou o `killer_from_events` do #4188 em produção: `InterWebhookTest` travou o processo em voo → quarentena → retry fechou vivo.
- Números ainda **inflados pela dupla-execução do plano v1** — a régua limpa é a 1ª nightly 100% v2.

## Lições

1. **"Disjunto" depende da semântica do consumidor.** A partição era correta no modelo "arquivos diretos" e errada no modelo real (pest recursivo). Gate que valida o plano tem que validar na semântica de EXECUÇÃO — por isso o `nested` entrou no universe-gate, não só no teste.
2. **Quarentena não cura erro de PLANO.** 24 das 29 quarentenas da noite foram tentativas inúteis de curar coleta duplicada movendo arquivo. Erro na fase de coleta com `already uses` = bug de harness, não killer-test.
3. A fatia 1062 do floor era em parte **auto-infligida pelo harness** (dupla execução), não só isolamento de teste — confirma a regra "MEDIR cada passo": o número de 57% da noite morta não descrevia a noite sharded.
