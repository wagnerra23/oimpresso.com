---
title: "Full-suite Pest MySQL nightly no CT 100 (FV-F3 — diagnostica, nunca required)"
module: "Infra"
owner: "W"
status: "ativo"
last_validated: "2026-07-12"
preconditions:
  - "Acesso SSH root@100.99.207.66 (Tailscale, BatchMode)"
  - "Container mysql-workers up na rede docker-host_default"
  - "Imagem oimpresso/mcp:latest presente (PHP 8.4 + pdo_mysql)"
  - "/opt/oimpresso-fullsuite/.env.local com creds da DB de TESTE (chmod 600)"
steps:
  - "Re-rodar manual: nohup /opt/oimpresso-fullsuite/ct100-fullsuite.sh &"
  - "Acompanhar: tail -f /opt/oimpresso-fullsuite/runs/latest/run.log"
  - "Coletar summary: cat /opt/oimpresso-fullsuite/runs/latest/summary.json"
  - "Atualizar script: AUTOMÁTICO via self-update.sh do MCP (sync mecânico a cada 15min desde 2026-07-02 — o passo manual driftou 13 dias e segurou o P07); manual só em emergência: scp scripts/tests/ct100-fullsuite.sh root@100.99.207.66:/opt/oimpresso-fullsuite/"
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
---

# RUNBOOK — full-suite Pest MySQL nightly no CT 100

> **FV-F3 do plano SDD** ([sessão 2026-06-12](../../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md)): nenhum run full-repo MySQL jamais foi salvo — esta nightly produz o **1º número real** que alimenta `full_suite_pass_rate` no scorecard (ADR 0275). É **diagnóstica: NUNCA vira required**; quem promove é a catraca Q1 depois do funil de quarentena.

## O que roda

`/opt/oimpresso-fullsuite/ct100-fullsuite.sh` (cópia de [`scripts/tests/ct100-fullsuite.sh`](../../../scripts/tests/ct100-fullsuite.sh) — o versionado é a fonte; desde 2026-07-02 o [`self-update.sh`](../../../docker/oimpresso-mcp/scripts/self-update.sh) do MCP sincroniza a cópia mecanicamente a cada 15min com `mv` atômico. **Pegadinha catalogada:** o passo manual "atualizar após merge" driftou 13 dias (18/jun→01/jul) — as nightlies rodaram sem o P07 coverage mesmo com pcov já na imagem, 0 clover.xml em 4 runs; foi o gap "feito que depende de algo que nunca rodou" da avaliação adversarial 2026-07-01):

1. `git fetch/reset` do clone público em `/opt/oimpresso-fullsuite/code` (origin/main);
2. `composer install` na imagem `composer:2` (a `oimpresso/mcp` não tem composer/git e `myfatoorah/*` é source-only; `--no-scripts` + `--ignore-platform-reqs` — só baixa deps, o runtime é o mcp, cujo entrypoint octane é sempre sobrescrito com `--entrypoint php`);
3. **recria** a DB dedicada `oimpresso_fullsuite_test` no container `mysql-workers` (usuário `fullsuite` com GRANT **só** nesse schema — Tier 0 por construção);
4. `.env` testing idêntico ao canon CI (`.github/actions/pest-mysql-setup`) + `migrate` via schema baseline (`database/schema/mysql-schema.sql`) + seed mínimo biz=1/biz=2;
5. **pest SHARDED** (V1/V3 do fix de OOM — ADR proposta [`2026-07-12-nightly-fullsuite-sharding-harness`](../../decisions/proposals/2026-07-12-nightly-fullsuite-sharding-harness.md), consome o chip node [#4166](https://github.com/wagnerra23/oimpresso.com/pull/4166) SDD P04): [`shards-plan.mjs`](../../../scripts/tests/shards-plan.mjs) descobre os dirs de teste (`tests/` + `Modules/`) e os particiona em **N shards** (`FULLSUITE_SHARDS`, default 8) bin-packed por contagem de arquivo; um **universe-gate** (`--verify`) prova que nenhum dir some no particionamento. O laço roda **1 processo PHP FRESCO por shard** (heap reset ⇒ pico de memória cai pra fração da suite — a suite inteira num processo morria por OOM a ~53%), escrevendo `junit-shard-<i>.xml` → `shard-<i>.summary.json` ([`junit-summary.mjs`](../../../scripts/tests/junit-summary.mjs) FV-F1). Container por shard com **`mariadb-client` + `/etc/my.cnf.d/*-no-ssl-verify.cnf`** (US-GOV-018 A.1; A.2 FK-off REVERTIDO). Loader-blocker (`uses(TestCase)` file-level / redeclare / parse) é quarentenado **só no clone descartável** e re-tentado **por-shard**. **Um shard que morre por OOM perde SÓ ele, não a noite;**
6. **merge** via [`shards-merge.mjs`](../../../scripts/tests/shards-merge.mjs) — funde os `shard-<i>.summary.json` **vivos** no `summary.json` da noite (schema `fullsuite-summary-sharded/v1`: `coherent` = ≥1 shard vivo; `all_shards_measured` = 0 mortos). [`floor-compute.mjs`](../../../scripts/tests/floor-compute.mjs) **v2** lê `all_shards_measured` — noite parcial NÃO vira burn-down fake (guard anti-mascaramento). Retenção dos últimos 14 runs.

## Duas lanes no mesmo run: FLOOR (run 1) + COVERAGE (run 2) — V4

O script roda **duas invocações do Pest, sequenciais e isoladas**, contra o mesmo clone/DB seedados:

| Lane | Invocação | Instrumentação | `memory_limit` | Timeout | Artefato | Métrica |
|---|---|---|---|---|---|---|
| **FLOOR** (run 1) | passo 6 (laço de shards, 1 processo/shard) | **sem pcov** | 4G/shard | `FULLSUITE_SHARD_TIMEOUT` (1h/shard) | `junit-shard-<i>.xml` → `shard-<i>.summary.json` → `summary.json` (merge) | `full_suite_pass_rate` (floor · ADR 0279) |
| **COVERAGE** (run 2) | bloco `[P07 coverage]` | **pcov** (2ª invocação) | 6G | `FULLSUITE_COV_TIMEOUT` (4h) | `clover.xml` | `coverage_pct` (C2 · ADR 0275 §2) |

**Por que separadas** (SDD P07 · [#3622](https://github.com/wagnerra23/oimpresso.com/pull/3622)): a 1ª nightly com pcov no **mesmo** processo do diagnóstico morreu silenciosa aos 53% e zerou o `junit.xml` (incidente `20260702-073601`) — coverage e floor competiam pelo mesmo processo. Agora o floor roda **primeiro, sem pcov**; o clover só é tentado **depois** do junit salvo. **Falha na lane de coverage nunca derruba o floor.**

**Como coexistem no scheduler (V4 — [PR desta seção]):** a lane de coverage (pcov single-core na suíte inteira) leva **16h+** e chegou a **travar a ~77%** em jul/2026. Sem `timeout -k`, o `timeout -s TERM` **não matava** o `docker run` do pcov (o PHP ignora TERM dentro do C do pcov) → o container `oimpresso-fullsuite-cov` virava **runaway de 16h** e segurava o `.lock` do script → a nightly seguinte pulava por *"outro run em andamento"* (**3 skips catalogados**; `20260707` sumiu). O fix:

- **`timeout -k "$KILL_GRACE_S"`** (120s) nas DUAS lanes → após o TERM, `SIGKILL` no `docker run`; o `docker rm -f` seguinte é o kill definitivo do container. Assim o run inteiro (floor + coverage) **termina em ~2×timeout (~8h) « 24h** → nunca segura o `.lock` além do próximo cron 02:00. A **cadência do floor (métrica-mãe) fica protegida**.
- **Coverage `killed` no teto** (exit 124/137) deixa o clover **truncado**; `coverage-compute.mjs` **rejeita** clover sem `</coverage>` de fechamento → `coverage_pct` fica `not_yet_measured` (**nunca mente baixo**; a catraca C2 só-sobe travaria um número falso).
- A lane de coverage **só vira produtiva** (clover completo → `coverage_pct` real) quando o **sharding por módulo (V1)** fizer o pcov caber num nightly. Até lá ela roda **bounded** e honestamente sem número. Ao ligar V1, subir `FULLSUITE_COV_TIMEOUT` pro tempo real da suíte sharded.

**Transporte (órfã `governance/nightly-floor`):** ambos os JSONs (`nightly-floor.json` + `nightly-coverage.json`) são computados no fim do run e publicados na mesma órfã via deploy key (`/root/.ssh/oimpresso_floor_deploy`) com `[skip ci]`. `coverage-compute` falhar (sem clover) **não** derruba a publicação do floor.

## Onde ficam os artefatos

```
/opt/oimpresso-fullsuite/
├── ct100-fullsuite.sh      # cópia do versionado
├── .env.local              # creds DB de TESTE — NUNCA no repo (chmod 600)
├── .composer-cache/        # cache composer entre runs
├── code/                   # clone público, reset a cada run
├── .lock                   # flock do run (floor + coverage) — anti-overlap com o cron
├── cron.log                # stdout do cron
└── runs/<YYYYMMDD-HHMMSS>/ # run.log + sha.txt + (FLOOR sharded:)
    │                       #   shards-plan.json          # plano de N shards (bin-pack)
    │                       #   junit-shard-<i>.xml        # 1 por shard vivo
    │                       #   shard-<i>.summary.json     # junit-summary por-shard
    │                       #   summary.json              # MERGE da noite (sharded/v1) <- shards-merge
    │                       #   pest-events-shard-<i>.txt  # post-mortem (apagado se 0 mortos)
    │                       # + clover.xml + cov-out.txt (COVERAGE, se pcov na imagem)
    └── latest -> símlink pro run mais recente
```

## Cron (02:00 BRT — host já é America/Sao_Paulo)

```
0 2 * * * /opt/oimpresso-fullsuite/ct100-fullsuite.sh >> /opt/oimpresso-fullsuite/cron.log 2>&1
```

Instalado no crontab do root do CT 100. Conferir: `ssh root@100.99.207.66 crontab -l`.

## Como coletar o resultado (consumo pelo scorecard)

```bash
ssh -o BatchMode=yes root@100.99.207.66 cat /opt/oimpresso-fullsuite/runs/latest/summary.json
```

`summary.json` traz contagens por arquivo de teste (sem mensagens de falha — repo é público, anti-PII por construção). `sha.txt` diz contra qual commit de main o run rodou. Se `summary.json` não existir, o run morreu antes do flush — ver `run.log` do mesmo diretório.

## Guard-rails anti-prod (ADR 0062)

- Script **aborta** se `DB_DATABASE` não terminar em `_test`;
- usuário MySQL `fullsuite` tem GRANT apenas em `oimpresso_fullsuite_test.*` — mesmo um teste mal-comportado não alcança `oimpresso_workers` nem qualquer outra base;
- nada em `/opt` existente foi tocado; containers existentes intactos (suite roda em containers descartáveis `--rm`);
- Hostinger fora do circuito por completo.

## Troubleshooting

| Sintoma | Ação |
|---|---|
| "outro run em andamento" | lock ativo (`/opt/oimpresso-fullsuite/.lock`); se órfão: `docker rm -f oimpresso-fullsuite-run` e re-rodar |
| **container `oimpresso-fullsuite-cov` "Up N horas (unhealthy)" + script preso + nightlies pulando** | **V4 (jul/2026) — runaway da lane de coverage.** Diagnóstico: `docker ps \| grep fullsuite` mostra o cov up >8h; `pgrep -af ct100-fullsuite` com `etime` alto; `cron.log` com *"outro run em andamento"*. Causa histórica: `timeout` sem `-k` não matava o `docker run` do pcov. **Já corrigido no script** (`timeout -k`); se reaparecer (cópia driftada): `docker rm -f oimpresso-fullsuite-cov` + `kill <pid do script>` pra liberar o `.lock`, e conferir que `/opt/oimpresso-fullsuite/ct100-fullsuite.sh` tem `timeout -k` (senão o `self-update.sh` ainda não sincronizou o merge). |
| **`clover.xml` truncado / `coverage_pct` some** | esperado quando a lane de coverage é morta no teto (`[P07 coverage] TIMEOUT` no `run.log`, exit 124/137) — `coverage-compute` rejeita clover sem `</coverage>` → `not_yet_measured` honesto. Só volta a medir quando o sharding (V1) fizer o pcov completar dentro do `FULLSUITE_COV_TIMEOUT`. |
| **summary.json com `coherent:false`** (com sharding, RARO) | só se **TODOS** os shards morrerem → `[ALERT] fullsuite_run_invalid` no `run.log`. Ver `shard-<i>.summary.json` (quais `{invalid}`) + `pest-events-shard-<i>.txt` (último teste em voo). Antes do sharding, **1** morte zerava a noite. |
| **1 shard específico morre por OOM toda noite** (`summary.json.shards_missing` recorrente + `all_shards_measured:false`) | esse shard é grande demais pra 4G. Curto prazo: subir `FULLSUITE_SHARDS` (mais shards = shards menores) ou `FULLSUITE_SHARD_TIMEOUT`. O floor daquele shard só encolhe conservador (não mente) e `all_shards_measured:false` marca a noite como parcial (floor-compute v2 não conta como completa). |
| migrate falha | schema baseline mudou em main — rodar de novo (DB é recriada do zero a cada run) |
| disco | retenção automática mantém 14 runs; clone+vendor ~2,5 GB |
| **suite roda em sqlite e não MySQL** (`no such table` em massa, `near "MODIFY": syntax error`) | **C1 (corrigido 2026-06-13):** `phpunit.xml:72-73` tem `<env DB_CONNECTION=sqlite>` **sem `force=`** — o PHPUnit só seta se a var ainda não existir. O `docker run` do pest agora passa `-e DB_CONNECTION=mysql` + `DB_*` reais (passo 6), então o `<env>` sqlite é ignorado e o pest usa o MySQL seedado. Se reaparecer: conferir que os `-e DB_*` continuam no `docker run` do pest. Ver triage Q2 `memory/sessions/2026-06-13-sdd-f2b-triage-q2.md`. |
| **`Base table or view not found` em massa NO MySQL** (`business`/`users`/`activity_log` somem mid-run) + `mysql: not found` OU `ERROR 2026 ... Certificate verification failure` no `pest-out.txt` | **US-GOV-018 Frente A.1 (2026-06-13) — fix em 3 partes:** testes com `RefreshDatabase`/`migrate:fresh` rodam "Loading stored database schemas", que chama o CLI `mysql` pra recarregar `database/schema/mysql-schema.sql`. (1) a imagem `oimpresso/mcp` **não tinha** o binário (só `pdo_mysql`) → 72× `mysql: not found` → `mariadb-client` no `Dockerfile` (durável, exige rebuild via `bootstrap-ct100.sh`) + `apk add` no `docker run` (fallback imediato). (2) **o mariadb-client verifica TLS por default e o comando de load do Laravel não passa flag de ssl → `ERROR 2026 Certificate verification failure` → o dump não recarrega** (refutação adversarial ADR 0276 — só o binário NÃO basta): o passo 6 escreve `/etc/my.cnf.d/zz-fullsuite-no-ssl-verify.cnf` com `ssl-verify-server-cert=0` no container do pest (provado no CT100: bare load TLS-fail → OK). Se reaparecer: conferir o binário (`command -v mysql`) **e** o `.cnf` de ssl-off no passo 6. |
| **`Cannot drop table ... referenced by a foreign key constraint` (errno 3730)** (`drop table if exists` de tabela core num teste) | **US-GOV-018 A.2 — TENTADO E REVERTIDO (2026-06-13).** FK-off (`FULLSUITE_FK_OFF`) tornava o `Schema::dropIfExists()` de tabela CORE compartilhada (business/users…) BEM-SUCEDIDO → a tabela sumia → cascata `Base table not found` (floor 1928 no run 115507). **DECISÃO: NÃO ligar FK-off** — deixar o drop falhar-seguro (3730 só no teste ofensor; a tabela core sobrevive pro resto). O conserto certo é **isolar os ~30 testes era-sqlite** que dropam tabela compartilhada (US-GOV-021 front-2), não mascarar com FK-off. `Tests\TestCase::setUp` segue inerte (gated em `getenv('FULLSUITE_FK_OFF')`, nunca setado). |
| **`migrate:fresh` carrega dump INCOMPLETO** (`Loading stored database schemas ... FAIL`; `ERROR 1419`/`ERROR 1227 ... SET_USER_ID`; ~188/364 tabelas; depois `Base table not found` em core) | **US-GOV-020 Frente C (2026-06-13):** o dump tem triggers com **DEFINER de prod** (`u906587222_oimpresso@localhost`, ex `trg_mcp_audit_log_no_update`). O setup carrega via root (OK), mas o `migrate:fresh` do RefreshDatabase carrega via o usuário `fullsuite` (não-SUPER) → 1419 (binlog) / 1227 (DEFINER) → aborta o load. Fix: passo 3 (root) faz `SET GLOBAL log_bin_trust_function_creators=1` + `GRANT SET_USER_ID ON *.* TO <fullsuite>`. Provado: load do fullsuite 188→377 tabelas, 0→4 triggers. Se reaparecer: conferir os 2 grants no passo 3; servidor MySQL 8 reset de GLOBAL no restart (o passo 3 re-seta a cada run). |

**Modo sharded (agora DEFAULT — era o "chunked" de fallback):** desde a V1 do fix de OOM (ADR proposta [`2026-07-12-nightly-fullsuite-sharding-harness`](../../decisions/proposals/2026-07-12-nightly-fullsuite-sharding-harness.md) + chip node [#4166](https://github.com/wagnerra23/oimpresso.com/pull/4166)) o passo 5 SEMPRE roda em shards: `shards-plan.mjs` particiona os dirs, o laço roda 1 processo php fresco por shard, e `shards-merge.mjs` funde os summaries vivos. Não há mais "run inteiro". **Debug de 1 shard:** `node scripts/tests/shards-plan.mjs --roots tests,Modules --shards 8` (vê a partição) e rode o(s) dir(s) daquele shard num container só.

> ⚠️ **Ainda não validado numa nightly real do CT100** (DoD R1): o wiring foi provado em integração local (shards-plan universe-gate + junit-summary por-shard + shards-merge → `summary.json` `fullsuite-summary-sharded/v1` com `all_shards_measured`), mas a 1ª nightly sharded que produz `summary.json` non-stale depende do relógio do CT100 (Wagner desbloqueia). Até lá, `last_validated` do frontmatter permanece `2026-07-02`.

## Transporte irmão: trend do RAGAS real semanal (ADR 0318)

Mesmo pattern de transporte da órfã (ADR 0279 Opção A), outra métrica: o cron `jana:ragas-real-eval` (dom 07:00 BRT, container `oimpresso-staging` via Kernel.php) persiste o report em `storage/app/governance/ragas-real-eval-latest.json`; o host roda [`scripts/tests/ct100-ragas-publish.sh`](../../../scripts/tests/ct100-ragas-publish.sh) (cron root `30 8 * * 0`, cópia em `/opt/oimpresso-ragas/` sincronizada mecanicamente pelo `self-update.sh` do MCP — mesma defesa anti-drift da cópia do fullsuite) que faz merge idempotente no trend ([`ragas-trend-compute.mjs`](../../../scripts/tests/ragas-trend-compute.mjs)) e publica na órfã **`governance/ragas-real-trend`** (órfã PRÓPRIA — a `governance/nightly-floor` é force-pushed toda noite; o trend acumula). Read-side: `measureRagasRealUptime()` em `sdd-scorecard.mjs` → métrica `ragas_real_uptime` (% de semanas com run válido; SKIP honesto ou semana ausente = inválida). Mesma deploy key do floor (`/root/.ssh/oimpresso_floor_deploy` — deploy key é repo-wide).
