---
title: "Full-suite Pest MySQL nightly no CT 100 (FV-F3 — diagnostica, nunca required)"
module: "Infra"
owner: "W"
status: "ativo"
last_validated: "2026-06-12"
preconditions:
  - "Acesso SSH root@100.99.207.66 (Tailscale, BatchMode)"
  - "Container mysql-workers up na rede docker-host_default"
  - "Imagem oimpresso/mcp:latest presente (PHP 8.4 + pdo_mysql)"
  - "/opt/oimpresso-fullsuite/.env.local com creds da DB de TESTE (chmod 600)"
steps:
  - "Re-rodar manual: nohup /opt/oimpresso-fullsuite/ct100-fullsuite.sh &"
  - "Acompanhar: tail -f /opt/oimpresso-fullsuite/runs/latest/run.log"
  - "Coletar summary: cat /opt/oimpresso-fullsuite/runs/latest/summary.json"
  - "Atualizar script: scp scripts/tests/ct100-fullsuite.sh root@100.99.207.66:/opt/oimpresso-fullsuite/"
related_adrs:
  - "0062-separacao-runtime-hostinger-ct100"
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
---

# RUNBOOK — full-suite Pest MySQL nightly no CT 100

> **FV-F3 do plano SDD** ([sessão 2026-06-12](../../sessions/2026-06-12-plano-reestruturacao-sdd-ondas-paralelas.md)): nenhum run full-repo MySQL jamais foi salvo — esta nightly produz o **1º número real** que alimenta `full_suite_pass_rate` no scorecard (ADR 0275). É **diagnóstica: NUNCA vira required**; quem promove é a catraca Q1 depois do funil de quarentena.

## O que roda

`/opt/oimpresso-fullsuite/ct100-fullsuite.sh` (cópia de [`scripts/tests/ct100-fullsuite.sh`](../../../scripts/tests/ct100-fullsuite.sh) — o versionado é a fonte; atualizar a cópia após merge, passo no frontmatter):

1. `git fetch/reset` do clone público em `/opt/oimpresso-fullsuite/code` (origin/main);
2. `composer install` na imagem `composer:2` (a `oimpresso/mcp` não tem composer/git e `myfatoorah/*` é source-only; `--no-scripts` + `--ignore-platform-reqs` — só baixa deps, o runtime é o mcp, cujo entrypoint octane é sempre sobrescrito com `--entrypoint php`);
3. **recria** a DB dedicada `oimpresso_fullsuite_test` no container `mysql-workers` (usuário `fullsuite` com GRANT **só** nesse schema — Tier 0 por construção);
4. `.env` testing idêntico ao canon CI (`.github/actions/pest-mysql-setup`) + `migrate` via schema baseline (`database/schema/mysql-schema.sql`) + seed mínimo biz=1/biz=2;
5. `vendor/bin/pest --log-junit` (suite inteira, timeout 4h, lock anti-overlap). Arquivo que mata o **loader** da suite (`uses(TestCase)` file-level em pasta já vinculada no `tests/Pest.php` — 4 casos conhecidos em `tests/Feature` em 2026-06-12) é posto de lado **só no clone descartável**, registrado em `loader-blockers.txt` (dado pro triage Q2) e o run re-tenta — consertar os arquivos é das lanes de burn-down;
6. summary via [`scripts/tests/junit-summary.mjs`](../../../scripts/tests/junit-summary.mjs) (FV-F1 — tripwire de artefato 0 bytes) + retenção dos últimos 14 runs.

## Onde ficam os artefatos

```
/opt/oimpresso-fullsuite/
├── ct100-fullsuite.sh      # cópia do versionado
├── .env.local              # creds DB de TESTE — NUNCA no repo (chmod 600)
├── .composer-cache/        # cache composer entre runs
├── code/                   # clone público, reset a cada run
├── cron.log                # stdout do cron
└── runs/<YYYYMMDD-HHMMSS>/ # run.log + junit.xml + summary.json + sha.txt
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
| junit.xml 0 bytes / ausente | run morto antes do flush (OOM/timeout) — ver fim do `run.log`; subir `FULLSUITE_TIMEOUT` ou rodar chunked (abaixo) |
| migrate falha | schema baseline mudou em main — rodar de novo (DB é recriada do zero a cada run) |
| disco | retenção automática mantém 14 runs; clone+vendor ~2,5 GB |

**Modo chunked** (fallback se a suite inteira estourar memória): rodar por diretório com um junit por chunk, ex. `--log-junit /artifacts/junit-unit.xml tests/Unit`, depois `Modules/<X>/Tests` um a um, e somar os summaries.
