---
title: "Snapshot diário do scorecard SDD no CT 100 (SDD P06 / GT-G7)"
module: "Infra"
owner: "W"
status: "ativo"
last_validated: "2026-07-12"
related_adrs:
  - "0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes"
  - "0062-separacao-runtime-hostinger-ct100"
preconditions:
  - "Acesso SSH root@ct100-mcp (Tailscale, BatchMode)"
  - "Container oimpresso-mcp up (healthy) — conecta na Hostinger prod DB"
  - "Host CT 100 com node v20 (/usr/bin/node) + checkout /opt/oimpresso-mcp/code fresco"
  - "Tabela mcp_sdd_scorecard_history aplicada em prod (P06 pré-req, já OK)"
steps:
  - "Re-rodar manual: /opt/oimpresso-governance/ct100-sdd-scorecard-snapshot.sh"
  - "Ver log: tail -f /opt/oimpresso-governance/snapshot.log"
  - "Conferir row: docker exec oimpresso-mcp php artisan tinker --execute='echo \\DB::table(\"mcp_sdd_scorecard_history\")->orderBy(\"snapshot_date\",\"desc\")->limit(3)->get([\"snapshot_date\",\"composta\"])->toJson();'"
---

# RUNBOOK — Snapshot diário do scorecard SDD no CT 100 (SDD P06 / GT-G7)

## TL;DR

O comando `governance:sdd-scorecard-snapshot` (GT-G7, [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) §1)
persiste 1 row/dia em `mcp_sdd_scorecard_history` (composta v1 + alertas). Sem o cron
diário, a **linha SDD do brief fica stale** (era refrescada à mão — última em 2026-07-01).

**Decisão Wagner 2026-07-01:** o cron roda no **CT 100**, não no Hostinger — `schedule:run`
não roda no shared hosting e IA/governança não vive lá ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)).

O `Kernel.php` (`app/Console/Kernel.php`) agenda o comando às **07:10 BRT** com
`->environments(['live'])` — esse gate só dispara se um `schedule:run` rodar num env
`live`, o que **não acontece**. Este RUNBOOK descreve o mecanismo que efetivamente roda
o comando no CT 100 (direto, sem `schedule:run`, então o gate de env não se aplica).

## Por que o mecanismo é "node no host + artisan no container"

| Fato | Consequência |
|------|--------------|
| O container `oimpresso-mcp` conecta na **Hostinger prod DB** (`u906587222_oimpresso@srv1818.hstgr.io`) | É lá que o comando precisa gravar — o brief lê dessa DB |
| O container `oimpresso-mcp` **NÃO tem node** | Re-medir dentro dele falharia; e re-medir no host divergia (ver "UMA composta" abaixo) |
| O **host** CT 100 tem o checkout `/opt/oimpresso-mcp/code` sincronizado (self-update 15min) | Copia o **artefato versionado** `governance/sdd-scorecard.json` e passa via `--input` (caminho canônico "sem node", ADR 0275 §3) |
| `--environments(['live'])` no Kernel | Só vale via `schedule:run` — rodando o comando direto, o gate não bloqueia |

### UMA composta — por que o wrapper NÃO re-mede (2026-07-13)

Até 2026-07-12 o wrapper rodava `node sdd-scorecard.mjs --json` no host — **sem** o
ambiente do CI (`GH_TOKEN` pro `drift_alarms`, materialização da branch órfã
`governance/nightly-floor`). Resultado: **composta fantasma não-reproduzível** — o
smoke de 12/jul gravou `64.1 (k=6, 7/13 vivas)` enquanto o recompute do artefato
versionado dava `41.0 (k=7)`. Pego pelo adversário da grade de réguas 2026-07-12.

Regra agora: **UMA medição (CI `sdd-scorecard-publish.yml`, diário 07:10 BRT +
push(main)) → UM artefato versionado (`governance/sdd-scorecard.json`, SSOT ADR 0279
Opção A) → o snapshot só faz JOIN com o baseline + persiste.** Composta do brief ==
recompute do repo, sha-rastreável. Lag máx ~1 dia (self-update 15min × publish diário).

Deconflito de nomes (3 números ≠ 1): **composta v1** (este snapshot — média das métricas
ARMADAS normalizadas, ADR 0275 §4) ≠ **composto do `/sdd-avaliar`** (juízo adversarial
de processo, 7 skeptics — ex: 69/100 em 12/jul) ≠ qualquer re-medição local parcial.
Ao citar, sempre rotular qual instrumento + k + fonte.

Fluxo do wrapper `ct100-sdd-scorecard-snapshot.sh`:

1. Valida e copia `/opt/oimpresso-mcp/code/governance/sdd-scorecard.json` (artefato
   versionado; loga sha do checkout + último commit do artefato) → 
   `/opt/oimpresso-mcp/storage/app/sdd-scorecard-input.json` (bind-mount rw
   `/opt/oimpresso-mcp/storage → /var/www/html/storage`, fora do checkout git ⇒ imune
   ao `git reset --hard` do self-update).
2. `docker exec oimpresso-mcp php artisan governance:sdd-scorecard-snapshot --input=/var/www/html/storage/app/sdd-scorecard-input.json`
   → grava a row do dia na prod DB (idempotente: delete+insert por `snapshot_date`).

## Instalação (host CT 100)

O script é **versionado** em `scripts/tests/ct100-sdd-scorecard-snapshot.sh` e a cópia em
`/opt/oimpresso-governance/ct100-sdd-scorecard-snapshot.sh` é **sincronizada mecanicamente
a cada 15min** pelo `self-update.sh` do MCP (mesma defesa anti-drift do fullsuite/ragas —
[pegadinha dos 13 dias](RUNBOOK-ct100-fullsuite.md)). O único passo manual é a linha de
crontab (1×, idempotente):

```bash
tailscale ssh root@ct100-mcp
( crontab -l 2>/dev/null | grep -v ct100-sdd-scorecard-snapshot; \
  echo '10 7 * * * flock -n /tmp/sdd-scorecard-snapshot.lock /opt/oimpresso-governance/ct100-sdd-scorecard-snapshot.sh >> /opt/oimpresso-governance/snapshot.log 2>&1' ) | crontab -
crontab -l | grep sdd-scorecard   # confirmar
```

Cron root: `10 7 * * *` — host TZ `America/Sao_Paulo` ⇒ **07:10 BRT** (mesmo horário
canônico do `Kernel.php`, 10min após `governance:scorecard-snapshot` 07:00).

## Honestidade (Tier 0)

Se o artefato estiver ausente/inválido ou o comando falhar, o script sai `!= 0` e o dia fica
**SEM row nova** (o brief mostra o snapshot de ontem) — a falha aparece no `snapshot.log` e
no `->onFailure()` do comando. **Nunca inventa row.** A staleness reaparece se o cron parar
→ sintoma: brief com linha SDD parada + `snapshot_date` máx defasada da data de hoje.

## Diagnóstico rápido (linha SDD do brief parece stale)

```bash
tailscale ssh root@ct100-mcp '
  crontab -l | grep sdd-scorecard || echo "CRON AUSENTE";
  tail -20 /opt/oimpresso-governance/snapshot.log;
  docker exec oimpresso-mcp php artisan tinker --execute="echo \DB::table(\"mcp_sdd_scorecard_history\")->max(\"snapshot_date\");"
'
```

Se `max(snapshot_date)` != hoje ⇒ cron parou ou falhou. Rodar manual (passo em `steps`) e
ver o log. Se a cópia em `/opt/oimpresso-governance/` sumiu, esperar ≤15min o self-update
recriar (ou rodar `bash /opt/oimpresso-mcp/code/docker/oimpresso-mcp/scripts/self-update.sh`).

## Validação histórica

- **2026-07-12** — mecanismo provado ponta-a-ponta (R1 smoke): `Snapshot SDD OK — 2026-07-12
  · composta 64.1 (k=6) · 7/13 vivas · 1 alertas`; row fresca confirmada na prod DB
  (antes: máx era 2026-07-01, composta 50.0 — 11 dias stale). Cron 07:10 BRT instalado.
- **2026-07-13** — ⚰️ **lápide do 64,1**: o adversário da grade de réguas (2026-07-12)
  provou que a re-medição no host era **não-reproduzível** (64,1 k=6 vs 41,0 k=7 do
  artefato versionado — 3 medidas a menos por falta de `GH_TOKEN` + transporte da órfã).
  Wrapper trocado pra consumir o artefato versionado (SSOT — seção "UMA composta" acima).
  Consequência esperada e CORRETA: a linha SDD do brief **cai ~64→~41** na primeira row
  nova — não é regressão do sistema, é o instrumento parando de contar só as fáceis; e o
  alerta armado real (`distiller_freshness` 0→6) passa a aparecer. Δ vs rows k=6 antigas
  NÃO é comparável (regimes diferentes).
