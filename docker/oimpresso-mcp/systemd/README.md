# systemd units do CT 100 — `oimpresso-git-sync`

> **Estes arquivos são a FONTE.** O host `ct100-mcp` recebe cópia. Fecha o **DR-06** da
> [AUDITORIA-OPS-DR-2026-07](../../../memory/requisitos/Infra/AUDITORIA-OPS-DR-2026-07.md)
> (*"Versionar systemd units do CT100 (`oimpresso-git-sync.*`) no repo — hoje ad-hoc no host"*).

## O que estas units fazem

O webhook do GitHub só chega no **Hostinger**. O CT 100 se mantém sozinho por este timer, que a
cada janela: `git fetch` → **grava o SHA de main** (`deploy-latest-main-sha.txt`, lido pelo
`DeployDriftChecker` da [ADR 0216](../../../memory/decisions/0216-governance-drift-framework-driftchecker-plugavel.md)
e pela [sentinela de frescor do staging](../../oimpresso-staging/staging-freshness-sentinel.sh))
→ `git pull --ff-only` → `mcp:sync-memory`.

A ordem do `ExecStart` é deliberada: o SHA é gravado **antes** do pull e do sync, para refletir o
que main realmente é mesmo quando os passos pesados falham.

## Aplicar no host

Transporte por **base64** — o conteúdo tem acento e crase, e escape de shell colapsa par de barra
invertida no caminho. O `sha256` nos dois lados é o recibo de que o transporte foi fiel.

```bash
BK=/root/ct100-backups/timer-$(date +%Y%m%d)
tailscale ssh root@ct100-mcp "mkdir -p $BK && cp -a /etc/systemd/system/oimpresso-git-sync.* $BK/"
```

Depois envie o unit versionado em base64, decodifique no destino, **confira o `sha256` contra o
local** e só então `systemctl daemon-reload && systemctl restart oimpresso-git-sync.timer`.
Divergiu o hash, aborte sem recarregar.

## Verificar — **consequência, nunca declaração**

`systemctl is-active` disse `active` para um timer que não disparava havia 38 dias; foi ele que
mentiu no incidente de 2026-09-21. O que vale:

```bash
# (a) o campo de runtime CERTO. OnCalendar é relógio de parede => Realtime preenchido.
#     Num timer MONOTONICO (OnBootSec/OnUnitActiveSec) ele vem VAZIO por construção,
#     e ler esse vazio como "morreu" foi exatamente o erro de 2026-09-21.
tailscale ssh root@ct100-mcp "systemctl show oimpresso-git-sync.timer -p NextElapseUSecRealtime -p Persistent"

# (b) A PROVA: o arquivo reescrito SOZINHO, sem ninguém disparar.
tailscale ssh root@ct100-mcp "stat -c '%y %n' /opt/oimpresso-mcp/storage/app/deploy-latest-main-sha.txt"
```

## Cadência — 6h é deliberada, não esquecimento

Veio do [#5663](https://github.com/wagnerra23/oimpresso.com/pull/5663) (2026-08-12) como
**contenção**: com 5min o `mcp:sync-memory` recomeçava antes de terminar e punha o Meilisearch em
**100,3% de CPU** contínuo.

**O churn de CPU foi curado** (medido 2026-09-21: meilisearch **0,13%**), mas o pré-requisito para
voltar à cadência curta **ainda não está cumprido**: o run de 2026-09-21 09:37 morreu com
`status=137` (SIGKILL/OOM) aos **10min25s**. Encurtar agora troca churn de CPU por churn de OOM.
Reabrir exige medir um run que termine com `Result=success`.

## Histórico

| data | o quê |
|---|---|
| 2026-05-12 | timer criado (`OnBootSec=2min` + `OnUnitActiveSec=5min`) — [PEGADINHA](../../../memory/requisitos/Infra/PEGADINHA-ct100-mcp-git-pull-cron.md) |
| 2026-05-29 | `.service` passa a gravar o `deploy-latest-main-sha.txt` (ADR 0216) |
| 2026-08-12 | `override.conf` baixa a cadência para 6h (contenção do #5663) |
| 2026-09-21 | **`OnCalendar` + `Persistent`** — os dois anteriores eram monotônicos e o timer passou **38 dias armado e mudo**. Units versionados aqui (DR-06) |
