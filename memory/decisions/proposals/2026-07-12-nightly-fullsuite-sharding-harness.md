---
tipo: proposta-mudanca-arquitetura
titulo: "Nightly full-suite CT100 — harness single-process → sharded (consome o chip node #4166)"
status: proposta
proposto_por: [C]
proposto_em: "2026-07-12"
decide: [W]
module: infra
related_adrs: [0062-separacao-runtime-hostinger-ct100, 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes, 0279-sdd-medir-governar-floor-nightly, 0101-tests-business-id-1-nunca-cliente]
---

# Proposta — harness do nightly full-suite: single-process → **sharded** (parte BASH/CT100)

> **Origem:** [avaliação adversarial SDD 2026-07-12](../../sessions/2026-07-12-sdd-avaliacao-adversarial-processo.md) (composto 69/100). Risco sistêmico nº1 — o **gargalo-mãe**: a nightly full-suite morre por **OOM mid-suite** → `junit.xml` 0-byte em **9/10 noites** → floor congelado → estrangula R1/C2/T1/P13. Wagner: **"Sim, e planeje e construa."** Esta é a **parte BASH/CT100** (V1 sobrevivência do junit + V3 OOM root); a **mecânica node já landou** como chip separado ([#4166](https://github.com/wagnerra23/oimpresso.com/pull/4166) SDD P04 + [#4170](https://github.com/wagnerra23/oimpresso.com/pull/4170) V4 coverage).

## Contexto (o problema, com números)

O `ct100-fullsuite.sh` passo `[6/7]` rodava a suite **inteira num único processo PHP** (`php -d memory_limit=4G vendor/bin/pest --log-junit /artifacts/junit.xml`). O heap acumulava e o processo era **SIGKILL-ado pelo LXC** por pressão de memória a **~53%** dos ~11.144 testes. Como o Pest só **grava o junit no fim**, um kill a 53% deixa `junit.xml` **0 bytes** — a noite de floor **perdida**. O chip node #4166 já entregou o **mecanismo** (`shards-plan.mjs` + `shards-merge.mjs` + `floor-compute` v2 shard-aware), mas o **harness continuava single-process** (comentário vivo: *"sharding V1 pendente"*). Esta proposta **fecha o elo**: fazer o harness CONSUMIR o mecanismo landado.

## Decisão

Trocar o passo `[6/7]` de **1 processo/suite-inteira** por um **laço de shards, 1 processo PHP fresco por shard**, consumindo o contrato landado:

1. **Plano (`shards-plan.mjs` — chip #4166).** `--roots tests,Modules --shards N` descobre os dirs com `*Test.php` e os particiona em **N shards** (bin-pack por contagem de arquivo; `FULLSUITE_SHARDS`, default **8**). O harness roda o **universe-gate** (`--verify`) e **aborta** se algum dir de teste sumir no particionamento (senão a noite mede menos e mente non-stale).
2. **Laço, processo fresco por shard.** Para cada shard, um **container efêmero** roda `pest <dirs-do-shard> --log-junit junit-shard-<i>.xml --log-events-text pest-events-shard-<i>.txt`. Cada `php` termina entre shards ⇒ **heap zera** ⇒ pico por processo cai pra fração da suite. Teto de 4G por shard (`FULLSUITE_SHARD_TIMEOUT` 1h + `timeout -k`). Mesmos fixes provados A.1 (mariadb-client + TLS-off) e a quarentena de loader-blocker (folder/redeclare/parse, `grep -oP` no **host** — o laço roda no host, então os detectores originais valem sem porte pra busybox).
3. **Summary por-shard.** `junit-summary.mjs junit-shard-<i>.xml --out shard-<i>.summary.json` — shard morto (junit 0-byte) vira marcador `{invalid}`.
4. **Merge (`shards-merge.mjs` — chip #4166).** Funde os `shard-<i>.summary.json` **vivos** no `summary.json` da noite (`fullsuite-summary-sharded/v1`): `coherent` = ≥1 shard vivo, `all_shards_measured` = 0 mortos. `floor-compute` v2 lê `all_shards_measured` — **noite parcial NÃO vira burn-down fake** (guard anti-mascaramento do chip node). **1 shard OOM perde só ele, não zera a noite.**
5. **Coverage inalterado** (#4170): 2ª invocação separada (6G, `--coverage-clover`, `timeout -k`) — nunca refém do floor.
6. **Alerta.** `[ALERT] fullsuite_run_invalid` dispara só quando `coherent=false` (TODOS os shards mortos), nomeando `shards_missing` + o teste em voo do último shard-events.

Contrato travado em CI barato: [`tests/fullsuiteHarness.spec.ts`](../../../tests/fullsuiteHarness.spec.ts) (FV-F1 lockstep, vitest). Os `.mjs` do chip node já têm selftests no `sdd-scorecard.yml` (#4166) — **este PR não os toca**.

## Por que sharding cura (e não só subir memória)

O OOM é **acumulação de heap ao longo da suite**, não um teste gigante. Processo fresco por shard = pico por processo vira o pico do **maior shard** (~1/8 da suite com N=8), não da suite inteira. Subir `memory_limit` só empurra o teto (escala com a suite, come RAM do host apertado). Sharding **desacopla** o pico do tamanho total.

## Consequências

**Positivas**
- `summary.json` volta a materializar **toda noite** (a menos que ~todos os shards morram) ⇒ floor **non-stale** ⇒ destrava R1/C2/T1/P13.
- Isolamento real: 1 shard OOM/loader-blocker não contamina os outros; `all_shards_measured` marca noite parcial honestamente.
- Post-mortem por-shard (`shard-<i>.summary.json` + `pest-events-shard-<i>.txt`).

**Negativas / residuais honestos**
- **Cobertura por descoberta de filesystem (chip node), não phpunit.xml:** `shards-plan` acha TODO `*Test.php` sob `tests/`+`Modules/` — pode incluir dirs fora dos `<testsuite>` do phpunit (ex `tests/Browser`). Se um desses quebrar, aquele shard morre (registrado), não a noite. Alinhar a fonte de descoberta ao phpunit.xml é follow-up do chip node, não deste wiring.
- **Overhead de container por shard:** N=8 ⇒ 8 `apk add` + setup (~alguns seg cada). Desprezível num nightly de horas.
- **Sem teto único da noite:** cada shard tem timeout próprio; o `.lock` evita overlap com o cron. Pior caso N×1h é patológico (típico ~30-60min total).
- **Shard gigante:** se um shard ainda OOM-ar a 4G, subir `FULLSUITE_SHARDS` (shards menores). O floor daquele shard só encolhe conservador (nunca mente).

## Alternativas rejeitadas

- **Só subir `memory_limit`.** Não escala; come RAM do host. Mantido só como paliativo por-shard.
- **Reimplementar o particionamento no bash (ou por phpunit.xml).** Rejeitada — o chip #4166 já é canon com universe-gate + selftests. Duplicar violaria "não duplicar" (Tier 0). **Este chip CONSOME o landado.**
- **Shards em paralelo.** Multiplicaria o pico no host (swap apertado) + disputa a conexão MySQL compartilhada. Sequencial é o certo pro V1.

## Prova (DoD R1) — o que já está provado e o que falta

- ✅ **Integração local:** `shards-plan --shards 8` → 158 dirs descobertos, universe-gate OK (0 perdidos); simulação com 1 shard OOM ⇒ `shards-merge` produz `summary.json` `fullsuite-summary-sharded/v1` **coherent=true, all_shards_measured=false, shards_missing=[2]** (7/8 vivos). Os comandos EXATOS de extração do passo 7 (`COHERENT`/`ALL_MEASURED`/`MISSING`) validados. `bash -n` + `sh -n` no harness. 24 assertions do lockstep FV-F1 passam contra o harness real.
- ⏳ **PENDENTE (bloqueia declarar "funcionando"):** **≥1 nightly REAL no CT100** produzindo `summary.json` non-stale (coherent) com sharding + floor computado. Depende do **relógio do CT100** — **Wagner desbloqueia** (`nohup /opt/oimpresso-fullsuite/ct100-fullsuite.sh &` ou cron 02:00 BRT após o `self-update.sh` sincronizar a cópia). Sem essa prova, esta proposta **não é declarada funcional** (R1).

## Reconciliação (sessão paralela)

Este chip nasceu com um design próprio (particionar via phpunit.xml + merge de junit-xml). Ao rebasear em `origin/main` descobri que o **chip node #4166 já landou** um design diferente (descoberta de filesystem + bin-pack + universe-gate + merge de **summaries**). **Descartei meu design divergente e reescrevi a parte BASH pra consumir o contrato landado** — o correto por Tier 0 "não duplicar". Este PR toca só: harness (`ct100-fullsuite.sh`), lockstep (`fullsuiteHarness.spec.ts`), RUNBOOK e esta ADR. **Não** toca `shards-plan.mjs`/`shards-merge.mjs`/seus testes/scorecard (canon #4166).

## Follow-ups (fora deste PR)

V2 isolamento DB (57% dos fails = cascata numa conexão compartilhada) · V4 sharding do coverage · V5 watchdog de frescor do floor · V6 gate de corretude (`--junit`/check-verde dormente) · alinhar a fonte de descoberta do `shards-plan` ao phpunit.xml.
