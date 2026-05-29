---
slug: pegadinha-ct100-mcp-git-pull-cron
type: pegadinha
module: Infra
status: resolved 2026-05-12 14:23 BRT
related: [ADR 0053, ADR 0062, RUNBOOK-acesso-ct100]
incidents: ["2026-05-12 sync atrasado ~3h — ADR 0143 LIVE prod mas brief não listava", "2026-05-29 deploy-latest-main-sha.txt stale (89f6952e) — DeployDriftChecker cego porque NADA escrevia o arquivo no CT 100 (webhook só chega na Hostinger)"]
---

# Pegadinha — CT 100 MCP server: `mcp:sync-memory` lia filesystem antigo (faltava cron `git pull`)

## TL;DR

`mcp:sync-memory --reason=cron` roda no container `oimpresso-mcp` a cada 5 minutos via Laravel scheduler. Lê do filesystem do host CT 100 montado em `/var/www/html` (bind `/opt/oimpresso-mcp/code`). **Mas o host nunca tinha um `git pull` periódico** — o repo no host estava ~50 commits atrás de `origin/main`.

Resultado: brief diário mostrava ADRs antigas + sync indexava conteúdo antigo + MCP search retornava conteúdo "canônico" stale.

**Fix:** systemd timer `oimpresso-git-sync.timer` (5 min) → unit `.service` que faz `git pull --ff-only` no host + `mcp:sync-memory --reason=cron` no container.

## Sintoma

- `brief-fetch` retorna brief recente (gerado /4-6h via `brief:generate` cron) mas **sem ADRs novas dos últimos 24h**
- Em `tinker` no container: `\DB::table('mcp_memory_documents')->orderBy('updated_at','desc')->limit(5)` mostra `updated_at` parados em timestamp de horas/dias atrás
- `cd /opt/oimpresso-mcp/code && git log -1 --oneline` mostra commit antigo, mas `git fetch origin main && git log origin/main` mostra MUITOS commits a frente
- Após PR merge no GitHub, MCP server fica "cego" pra novo conteúdo até alguém SSH e rodar `git pull` manual

## Root cause

- ADR 0053 (MCP server canônico) prevê sync via webhook GitHub → POST `/api/mcp/sync-memory`
- **Mas o controller `sync-memory` SÓ INDEXA o filesystem; NÃO faz `git pull`**. Confirmar lendo o controller — payload do webhook é ignorado pra git ops
- Não há cron/timer/systemd unit no host CT 100 que faça `git pull /opt/oimpresso-mcp/code`
- Laravel `php artisan schedule:run` roda dentro do container — container não tem `git` instalado (`sh: git: not found`) E não tem acesso write ao bind-mount em modo seguro

## Recovery em ataque agudo (esqueceu rodar pull)

```bash
tailscale ssh root@ct100-mcp
cd /opt/oimpresso-mcp/code
git status -uno  # checar drift antes — se houver mods uncommitted, git stash primeiro
git pull --ff-only origin main
docker exec oimpresso-mcp php artisan mcp:sync-memory --reason=manual
docker exec oimpresso-mcp php artisan brief:generate  # opcional — força brief fresco
```

## Fix permanente (instalado 2026-05-12 14:23 BRT)

systemd timer + oneshot service no host CT 100:

**`/etc/systemd/system/oimpresso-git-sync.service`**

```ini
[Unit]
Description=Pull latest oimpresso main + trigger mcp:sync-memory
After=network-online.target docker.service
Wants=network-online.target

[Service]
Type=oneshot
WorkingDirectory=/opt/oimpresso-mcp/code
ExecStart=/bin/bash -c "/usr/bin/git fetch origin main && /usr/bin/git pull --ff-only origin main && /usr/bin/docker exec oimpresso-mcp php artisan mcp:sync-memory --reason=cron"
StandardOutput=journal
StandardError=journal
```

**`/etc/systemd/system/oimpresso-git-sync.timer`**

```ini
[Unit]
Description=Run oimpresso-git-sync every 5 minutes

[Timer]
OnBootSec=2min
OnUnitActiveSec=5min
Unit=oimpresso-git-sync.service

[Install]
WantedBy=timers.target
```

```bash
systemctl daemon-reload
systemctl enable --now oimpresso-git-sync.timer
systemctl status oimpresso-git-sync.timer
```

## Limitação do fix

- **`git pull --ff-only` falha se host tem drift uncommitted** (ex: MCP server escreveu em SPEC.md programaticamente). Logs em `journalctl -u oimpresso-git-sync.service` mostram a falha.
- **Solução longo prazo:** controller `sync-memory` deveria **não escrever arquivos no host** (canônico = git; MCP só lê). Se precisa state, usar tabela `mcp_memory_documents` ou outra tabela MCP-owned. **Próxima sessão**: investigar quem escreve em `memory/requisitos/*/SPEC.md` no host CT 100 — provável bug em algum job ou comando artisan que tenta "atualizar SPEC" e grava no filesystem em vez de via PR.
- **2026-05-12 incidente:** 3 SPECs tinham +1072 linhas uncommitted no host (Infra/Jana/Whatsapp) → stashed `stash@{0}: drift-ct100-2026-05-12-1710-host-edits` no host CT 100 (recoverable, NÃO destruído). Wagner decide próxima sessão se commit-back ou descarta.

## Diagnóstico rápido (próxima vez que MCP parecer stale)

```bash
tailscale ssh root@ct100-mcp 'cd /opt/oimpresso-mcp/code && git log --oneline HEAD -1 && echo "vs origin:" && git fetch origin main -q && git log --oneline origin/main -1'
# Se diferem ⇒ webhook/timer parou; checar journalctl -u oimpresso-git-sync.service
```

## 2026-05-29 — Extensão: o timer também grava o `deploy-latest-main-sha.txt` (ADR 0216)

### Sintoma
`DeployDriftChecker` (ADR 0216) flagava drift `high` permanente: `storage/app/deploy-latest-main-sha.txt` no CT 100 estava congelado num **commit ancestral** (89f6952e) enquanto main já estava 6 merges à frente. Reconcile manual do arquivo "resolvia", mas voltava a ficar stale.

### Root cause (investigado 2026-05-29)
O webhook GitHub é **um só** e aponta pra `https://oimpresso.com/api/mcp/sync-memory` = **Hostinger** (confirmado via `gh api repos/:owner/:repo/hooks` → `last_response.code: 200`). O `SyncMemoryWebhookController` grava o SHA em `storage/app/` da **máquina que recebe o POST** — ou seja, na Hostinger. Mas o `DeployDriftChecker` roda no **container `oimpresso-mcp` (CT 100)**, lendo o `storage/app/` do CT 100, que o webhook **nunca toca**.

Pontos descartados na investigação (não eram a causa):
- ❌ Permissão / read-only no bind-mount: `/opt/oimpresso-mcp/storage → /var/www/html/storage` é **rw=true**; write testado OK (owner `www-data`/uid 82, world-readable).
- ❌ `@file_put_contents` falhando: o código do webhook está correto — só roda na Hostinger.
- ❌ GitHub não entregando: entrega 200 normalmente — só não chega no CT 100.

➡️ **A causa real: no CT 100, NADA escrevia o arquivo automaticamente.** O webhook ia pra Hostinger; o `oimpresso-git-sync.timer` fazia `git pull` mas **não gravava o SHA**.

### Fix (instalado 2026-05-29)
Estendido o `ExecStart` do `oimpresso-git-sync.service` pra gravar o SHA a partir de `git rev-parse origin/main`, **logo após o `git fetch` e ANTES do `pull`/`sync`** — assim o arquivo reflete o que main realmente é mesmo se o `pull` der drift ou o `mcp:sync-memory` sofrer **OOM (status=137)**, que acontece ~2×/dia no passo pesado de indexação:

```ini
ExecStart=/bin/bash -c "/usr/bin/git fetch origin main && /usr/bin/git rev-parse origin/main > /opt/oimpresso-mcp/storage/app/deploy-latest-main-sha.txt; /usr/bin/git pull --ff-only origin main && /usr/bin/docker exec oimpresso-mcp php artisan mcp:sync-memory --reason=cron"
```

Com isso o CT 100 fica **auto-suficiente** (gera seu próprio sinal "o que é main" a cada 5min, via root no host, zero dependência do webhook). `governance:audit --check=deploy_drift` validado **clean** (deployed == main) após o fix. Nenhuma mudança no código PHP do checker.

> **Nota de design:** o `DeployDriftChecker::latestMainSha()` prefere o arquivo sobre `refs/remotes/origin/main`. Como o timer agora mantém o arquivo fresco, isso fica correto. (Alternativa considerada e descartada por ser menos alinhada ao contrato do checker: deletar o arquivo no CT 100 e deixar cair no fallback do ref `origin/main`, que o timer já mantém fresco via `git fetch`.)

### Follow-ups
- ⚠️ **OOM no `mcp:sync-memory` (status=137, ~2×/dia):** pré-existente, separado deste fix (o SHA agora é gravado ANTES do passo que mata). Vale investigar limite de memória do container / chunking da indexação.
- ⚠️ **Unit systemd não versionada:** `oimpresso-git-sync.{service,timer}` vivem só no host (criadas ad-hoc 2026-05-12, agora editadas ad-hoc). Um rebuild do CT 100 via bootstrap perde o fix. Mover a definição da unit pro `docker/oimpresso-mcp/scripts/bootstrap-ct100*.sh` (ou systemd unit no repo) pra durabilidade real.

## Histórico

- **2026-05-12 14:08 BRT:** último sync OK (gerado por trigger desconhecido — provavelmente webhook que funcionou)
- **2026-05-12 14:08-17:00 BRT:** drift ~3h, ~6 PRs novas no GitHub não chegaram ao MCP server
- **2026-05-12 17:00 BRT:** detectado durante sessão Wave A+B fechamento via `brief-fetch` não listar ADR 0143
- **2026-05-12 17:23 BRT:** git pull manual + sync (535 indexados, 0 atualizados — porque arquivos não mudaram, mas re-indexação completa rodou) + brief regenerou
- **2026-05-12 14:23 BRT:** systemd timer instalado, primeira execução 2min após (14:25 BRT pulled 11c3f1b1..b52c5ec2 — PR #678 Whatsapp Reparse Media commit pegou)

Detalhes na sessão: [`memory/sessions/2026-05-12-1700-wave-ab-inventory-comvis-v0.md`](../../sessions/2026-05-12-1700-wave-ab-inventory-comvis-v0.md).
