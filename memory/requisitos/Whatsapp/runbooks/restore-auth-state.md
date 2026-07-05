# Runbook — Restore auth-state WhatsApp (wuzapi/whatsmeow) CT 100

> Companion do backup automatizado em [`infra/scripts/backup-whatsmeow-auth.sh`](../../../../infra/scripts/backup-whatsmeow-auth.sh).
> Cron daily 03:00 BRT mantém 14 dias de tar.gz em `/srv/backup/whatsmeow/` no CT 100 (ver [`infra/cron/baileys-backup`](../../../../infra/cron/baileys-backup)).
>
> **Substitui** o runbook Baileys (arquivado em [`_archive/restore-auth-state.md`](_archive/restore-auth-state.md)) — o daemon migrou Baileys → wuzapi/whatsmeow.

## O que é o auth-state agora (mudou com a migração)

O daemon `whatsapp-whatsmeow` (imagem `asternic/wuzapi`) **não** guarda arquivos de sessão num diretório (como o Baileys). Ele guarda **dois bancos SQLite em WAL mode** em `/srv/docker/whatsapp-whatsmeow/sessions` (bind mount → `/app/dbdata` no container):

| Arquivo | Dono | Conteúdo | Perder = |
|---|---|---|---|
| `main.db` | whatsmeow | `whatsmeow_device`, `_identity_keys`, `_pre_keys`, `_sessions`, `_sender_keys`, `_app_state_sync_keys`, **`_lid_map`** | canais desconectam → re-scan QR em cada um + perde LID map + history sync |
| `users.db` | wuzapi | tabela `users` (token→canal por business) + `message_history` | API não roteia mais os canais (mesmo com main.db intacto) |

> ⚠️ **Sempre restaure os DOIS DBs juntos.** `main.db` sozinho = auth WhatsApp Web sem o roteamento wuzapi; `users.db` sozinho = roteamento sem as chaves. O backup empacota ambos.
>
> ⚠️ **SQLite em WAL:** por isso o backup usa a *online backup API* (não `cp`/`tar` do arquivo aberto). No restore, o inverso importa: **apague os arquivos `-wal`/`-shm` residuais** antes de subir o daemon (Passo 4), senão o SQLite reaplica um WAL velho por cima do DB restaurado → inconsistência.

## Quando usar este runbook

- Container `whatsapp-whatsmeow` não conecta / loop de reconexão nos logs
- `device_removed` / auth inválida / canal caiu e pede QR sozinho
- Após corrupção FS, `docker volume`/`rm` acidental, ou upgrade da imagem que zoou o store
- Rollback após mudança de número/canal que corrompeu mappings (classe do incident **2026-05-14**, biz=1 prod — o SPOF que motivou este backup)

⚠️ **NÃO** usar se a causa for **ban** WhatsApp — restaurar auth banida = re-ban imediato. Ver [`_archive/baileys-troubleshoot-ban.md`](_archive/baileys-troubleshoot-ban.md) / agent `whatsapp-doctor` primeiro.

## Pré-requisitos

- SSH CT 100 via Tailscale (`tailscale ssh root@ct100-mcp`) — 1ª conexão pede re-auth via URL (Wagner aprova manual)
- Backup disponível em `/srv/backup/whatsmeow/whatsmeow-auth-YYYYMMDD-HHMM.tar.gz`
- Wagner ciente do downtime (~1-2min daemon offline durante o swap dos DBs)

## Passo 1 — Escolher o backup target

```bash
tailscale ssh root@ct100-mcp 'ls -la /srv/backup/whatsmeow/whatsmeow-auth-*.tar.gz | tail -20'
tailscale ssh root@ct100-mcp 'tail -20 /srv/backup/whatsmeow/.audit.log'
# .audit.log mostra size + devices= de cada backup. Preferir o mais recente ANTES do incident,
# com devices>=1 (o script nunca grava backup com devices=0). Anotar:
TARGET=/srv/backup/whatsmeow/whatsmeow-auth-YYYYMMDD-HHMM.tar.gz
```

## Passo 2 — Parar o daemon

```bash
tailscale ssh root@ct100-mcp 'docker stop whatsapp-whatsmeow'
```

## Passo 3 — Preservar o estado atual (evidência pós-mortem)

```bash
tailscale ssh root@ct100-mcp 'tar -czf /srv/backup/whatsmeow/PRE-RESTORE-$(date -u +%Y%m%d-%H%M).tar.gz \
    -C /srv/docker/whatsapp-whatsmeow sessions'
tailscale ssh root@ct100-mcp 'ls -lh /srv/backup/whatsmeow/PRE-RESTORE-*.tar.gz | tail -1'
# Nome PRE-RESTORE-* NÃO casa com o pattern da retention (whatsmeow-auth-*) → não é apagado.
```

## Passo 4 — Restaurar os DBs (e limpar WAL/SHM residual)

```bash
# Remove os DBs atuais + QUALQUER -wal/-shm residual (crítico: WAL velho corrompe o restore)
tailscale ssh root@ct100-mcp 'rm -f /srv/docker/whatsapp-whatsmeow/sessions/main.db* \
                                     /srv/docker/whatsapp-whatsmeow/sessions/users.db*'

# Extrai os snapshots íntegros do backup (contém main.db + users.db, sem -wal/-shm)
tailscale ssh root@ct100-mcp "tar -xzf $TARGET -C /srv/docker/whatsapp-whatsmeow/sessions/"

tailscale ssh root@ct100-mcp 'ls -la /srv/docker/whatsapp-whatsmeow/sessions/'
# Deve mostrar main.db + users.db (sem -wal/-shm — serão recriados pelo daemon ao subir)
```

## Passo 5 — Ownership

O daemon escreve o store como **root:root** (estado atual observado). Se o `tar` restaurou outro owner, ajustar:

```bash
tailscale ssh root@ct100-mcp 'chown root:root /srv/docker/whatsapp-whatsmeow/sessions/main.db \
                                               /srv/docker/whatsapp-whatsmeow/sessions/users.db'
```

## Passo 6 — Subir o daemon

```bash
tailscale ssh root@ct100-mcp 'docker start whatsapp-whatsmeow'
tailscale ssh root@ct100-mcp 'docker ps --filter name=whatsapp-whatsmeow --format "{{.Status}}"'
# Aguardar "Up ... (healthy)"
```

## Passo 7 — Validar (evidência, não narração)

```bash
# (a) auth presente no DB restaurado (COUNT — nunca imprima o JID, é PII):
tailscale ssh root@ct100-mcp 'python3 -c "import sqlite3;print(\"devices:\",sqlite3.connect(\"file:/srv/docker/whatsapp-whatsmeow/sessions/main.db?mode=ro\",uri=True).execute(\"select count(*) from whatsmeow_device\").fetchone()[0])"'

# (b) logs do daemon sem loop de reconexão:
tailscale ssh root@ct100-mcp 'docker logs --tail 40 whatsapp-whatsmeow'

# (c) status de conexão via API wuzapi (token admin em Vaultwarden — NÃO colar no chat/git):
#     GET /session/status por canal deve retornar Connected=true, LoggedIn=true
```

Só declarar restaurado após (a) `devices>=1` **e** (b) logs limpos **e** (c) canal `Connected/LoggedIn`. Registrar o incident em `memory/sessions/YYYY-MM-DD-whatsapp-incident-<slug>.md` (agent `whatsapp-doctor`).

## Notas

- **Mídia (`/app/files` → `/srv/docker/whatsapp-whatsmeow/files`)** NÃO faz parte do auth-state e não está neste backup — é cache regenerável. Fora de escopo do restore de auth.
- **Segredos:** o backup contém chaves de criptografia (main.db) + tokens wuzapi (users.db). `/srv/backup/whatsmeow` é root-only (`umask 077` no script). Recomendado FS encrypted (LUKS) no CT 100 — ver [`whatsmeow-daemon-deploy-ct100.md`](whatsmeow-daemon-deploy-ct100.md).
- **Multi-tenant:** `whatsmeow_device` / `users.users` podem ter N canais de N business. O restore é do store inteiro (todos os canais juntos) — não há restore parcial por business.
