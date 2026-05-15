# Runbook — Restore auth_state Baileys CT 100

> Companion do backup automatizado em [`infra/scripts/backup-baileys-auth.sh`](../../../../infra/scripts/backup-baileys-auth.sh).
> Cron daily 03:00 BRT mantém 14 dias de tar.gz em `/backups/baileys-auth/` no CT 100.

## Quando usar este runbook

Sintomas que justificam restore:

- Daemon `whatsapp-baileys-{instance}` não conecta (`docker logs` mostra loop "Connection Failure")
- Log do daemon mostra `device_removed` ou `auth state invalid`
- History sync infinito de 90d disparou sozinho (sinal: re-pareamento implícito)
- Após upgrade Baileys quebrar auth (ver `baileys-upgrade-lib.md`)
- Após `docker volume rm` acidental ou corrupção FS no CT 100
- Manual rollback após mudança de número/canal que zoou mappings (incident 2026-05-14)

⚠️ NÃO usar este runbook se a causa é ban WhatsApp — ver `baileys-troubleshoot-ban.md` primeiro. Restore com auth banida = re-ban imediato.

## Pré-requisitos

- Acesso SSH CT 100 via Tailscale (`tailscale ssh root@ct100-mcp`) — primeira conexão pede re-auth via URL (Wagner aprova manual)
- Permissão docker no CT 100 (root tem)
- Backup disponível em `/backups/baileys-auth/baileys-auth-YYYYMMDD-HHMM.tar.gz`
- Wagner ciente do downtime estimado (~5min daemon offline durante restore)

## Passo 1 — Parar daemon Baileys

```bash
tailscale ssh root@ct100-mcp 'docker ps --filter name=whatsapp-baileys --format "{{.Names}}"'
# Pega nome exato (ex: whatsapp-baileys-1 pra biz=1)

tailscale ssh root@ct100-mcp 'docker stop whatsapp-baileys-1'
# Aguardar "whatsapp-baileys-1" no stdout (~5s)
```

## Passo 2 — Backup do estado corrompido atual (preservar evidência)

ANTES de sobrescrever, preserve estado atual pra post-mortem:

```bash
tailscale ssh root@ct100-mcp 'tar -czf /backups/baileys-auth-PRE-RESTORE-$(date -u +%Y%m%d-%H%M).tar.gz \
    -C /srv/docker/whatsapp-baileys sessions'

tailscale ssh root@ct100-mcp 'ls -lh /backups/baileys-auth-PRE-RESTORE-*.tar.gz | tail -1'
# Confirma archive criado, anota size pra audit
```

Esse archive PRE-RESTORE NÃO é apagado pela retention (nome diferente do pattern do cron).

## Passo 3 — Escolher backup target

```bash
tailscale ssh root@ct100-mcp 'ls -la /backups/baileys-auth/baileys-auth-*.tar.gz | tail -20'
# Lista últimos 20 backups (cron gera 1 por dia, então ~20 dias retroativos pós-retention)

tailscale ssh root@ct100-mcp 'tail -20 /backups/baileys-auth/.audit.log'
# Audit log mostra size de cada backup — preferir o MAIOR (mais cheio = mais auth state)
```

Heurística: escolher o backup mais recente ANTES do incident detectado. Ex: incident 2026-05-14 14:00 → escolher `baileys-auth-20260514-0600.tar.gz` (último válido pré-incident).

Anotar variável pro próximo passo:

```bash
TARGET=/backups/baileys-auth/baileys-auth-YYYYMMDD-HHMM.tar.gz
```

## Passo 4 — Restore (substitui sessions/ inteiro)

```bash
tailscale ssh root@ct100-mcp "rm -rf /srv/docker/whatsapp-baileys/sessions"
tailscale ssh root@ct100-mcp "tar -xzf $TARGET -C /srv/docker/whatsapp-baileys/"
tailscale ssh root@ct100-mcp 'ls -la /srv/docker/whatsapp-baileys/sessions/'
# Confirma diretório repovoado (deve ter sub-pastas por instance ou arquivos *.json)
```

## Passo 5 — Validar permissões/ownership

Container Baileys roda como UID 1000 (node user). Se restore preservou ownership errado, daemon não consegue read/write:

```bash
tailscale ssh root@ct100-mcp 'chown -R 1000:1000 /srv/docker/whatsapp-baileys/sessions/'
tailscale ssh root@ct100-mcp 'ls -la /srv/docker/whatsapp-baileys/sessions/ | head -5'
# Coluna owner deve mostrar "1000 1000" ou "node node"
```

## Passo 6 — Subir daemon

```bash
tailscale ssh root@ct100-mcp 'docker start whatsapp-baileys-1'
tailscale ssh root@ct100-mcp 'docker ps --filter name=whatsapp-baileys-1 --format "{{.Status}}"'
# Esperar "Up X seconds"
```

## Passo 7 — Confirmar conexão

```bash
tailscale ssh root@ct100-mcp 'docker logs --tail=50 whatsapp-baileys-1'
```

Esperando linhas tipo:

- `auth ready` ou `creds loaded`
- `connection.update: open`
- `[INFO] Connected to WhatsApp`

⚠️ Se aparecer `device_removed` ou `Connection Failure` em loop → backup escolhido tá corrompido OU auth foi invalidada pelo WhatsApp (re-pareamento manual necessário, ver `baileys-daemon-deploy-ct100.md` §"Re-pair"). Vá pro passo "Se falhar".

## Passo 8 — Smoke test

1. Manda 1 msg WhatsApp de número PESSOAL Wagner (não cliente) pro número do canal restaurado: `"teste restore $(date)"`
2. Aguarda 30s
3. Verifica chegada no DB Hostinger:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    'mysql oimpresso -e "SELECT id, business_id, status, created_at FROM whatsapp_messages \
     WHERE direction=\"inbound\" ORDER BY id DESC LIMIT 3"'
```

Última row deve ter timestamp recente (<2min) e content esperado.

## Pós-restore checklist

- [ ] `whatsapp_channels.status='active'` no DB Hostinger (canal não ficou "disconnected")
- [ ] Rows `lid_phone_map` preservadas (`SELECT COUNT(*) FROM lid_phone_map WHERE business_id=1` mantém valor pré-incident)
- [ ] Sem alarme `whatsapp_baileys_ban_detected_total` nas próximas 2h
- [ ] Inbox WhatsApp UI carregando mensagens normalmente
- [ ] OTel metric `whatsapp_baileys_connection_state` reporting "open"
- [ ] Anotar restore em [incident log](../INCIDENT-LOG.md) (data, backup usado, root cause)

## Se falhar

Backup escolhido tá corrompido OU auth invalidada pelo WhatsApp:

1. **Tentar backup mais antigo** — volte ao Passo 3 e pegue archive do dia anterior
2. **Rollback total** — restaurar o PRE-RESTORE archive do Passo 2:
   ```bash
   tailscale ssh root@ct100-mcp 'docker stop whatsapp-baileys-1'
   tailscale ssh root@ct100-mcp 'rm -rf /srv/docker/whatsapp-baileys/sessions'
   tailscale ssh root@ct100-mcp 'tar -xzf /backups/baileys-auth-PRE-RESTORE-YYYYMMDD-HHMM.tar.gz \
       -C /srv/docker/whatsapp-baileys/'
   tailscale ssh root@ct100-mcp 'chown -R 1000:1000 /srv/docker/whatsapp-baileys/sessions/'
   tailscale ssh root@ct100-mcp 'docker start whatsapp-baileys-1'
   ```
3. **Re-pareamento manual** — se nenhum backup serve, seguir `baileys-daemon-deploy-ct100.md` §"Re-pair" (QR code pelo celular Wagner) — ATENÇÃO: mappings LID perdidos, 90d history sync vai disparar
4. **Abrir incident** — criar entrada em [INCIDENT-LOG.md](../INCIDENT-LOG.md) + notificar Wagner via Telegram/sinal direto
5. **Post-mortem obrigatório** — após 24h, registrar root cause + action items em `memory/sessions/YYYY-MM-DD-incident-baileys-restore.md`

## Referências

- Backup script: [`infra/scripts/backup-baileys-auth.sh`](../../../../infra/scripts/backup-baileys-auth.sh)
- Cron config: [`infra/cron/baileys-backup`](../../../../infra/cron/baileys-backup)
- Runbook companion (ban): [`baileys-troubleshoot-ban.md`](baileys-troubleshoot-ban.md)
- Runbook deploy daemon: [`baileys-daemon-deploy-ct100.md`](baileys-daemon-deploy-ct100.md)
- ADR 0062 — Separação runtime Hostinger ≠ CT 100
- Incident origem 2026-05-14 — sessão `2026-05-15-estudo-whatsapp-protocol-vs-oimpresso.md` §6 P1-3
