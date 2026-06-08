#!/bin/bash
# backup-baileys-auth.sh — backup daily volume auth_state Baileys CT 100
#
# Roda em: CT 100 Proxmox host (root @ ct100-mcp via Tailscale)
# Cron: daily 03:00 BRT (UTC-3 = 06:00 UTC) — ver infra/cron/baileys-backup
# Incident origem: 2026-05-14 (Wagner re-pareou canal Baileys, perdeu mapping LID
#                  + 90d history sync — single-point-of-failure inaceitável biz=1 prod)
#
# Deploy:
#   tailscale ssh root@ct100-mcp 'mkdir -p /opt/scripts /backups/baileys-auth'
#   scp backup-baileys-auth.sh root@ct100-mcp:/opt/scripts/
#   tailscale ssh root@ct100-mcp 'chmod 755 /opt/scripts/backup-baileys-auth.sh'
#
# Restore: ver memory/requisitos/Whatsapp/runbooks/restore-auth-state.md

set -euo pipefail

SOURCE_DIR="/srv/docker/whatsapp-baileys/sessions"
BACKUP_DIR="/backups/baileys-auth"
RETENTION_DAYS=14
DATE=$(date -u +%Y%m%d-%H%M)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

# Validações pre-flight — NÃO seguir adiante se falhar (operador investiga)
if [ ! -d "$SOURCE_DIR" ]; then
    echo "[$TIMESTAMP] ERROR: source dir $SOURCE_DIR não existe — daemon pode estar down" >&2
    exit 1
fi

# Sanity check — diretório vazio é red flag (daemon pode ter wipado auth_state)
if [ -z "$(ls -A "$SOURCE_DIR" 2>/dev/null)" ]; then
    echo "[$TIMESTAMP] ERROR: source dir $SOURCE_DIR está vazio — daemon perdeu auth?" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR"

ARCHIVE="$BACKUP_DIR/baileys-auth-$DATE.tar.gz"

# Idempotência — se já existe archive do mesmo minuto, sai limpo (cron retry safety)
if [ -f "$ARCHIVE" ]; then
    echo "[$TIMESTAMP] SKIP: archive $ARCHIVE já existe (idempotência)" >&2
    exit 0
fi

# Backup atômico — tar.gz com proprietário/permissões preservadas
# -C dirname + basename garante caminho relativo dentro do archive (facilita restore)
tar -czf "$ARCHIVE" -C "$(dirname "$SOURCE_DIR")" "$(basename "$SOURCE_DIR")"

# Validar archive — se vazio, abortar e remover
if [ ! -s "$ARCHIVE" ]; then
    echo "[$TIMESTAMP] ERROR: archive $ARCHIVE vazio — falha tar?" >&2
    rm -f "$ARCHIVE"
    exit 2
fi

ARCHIVE_SIZE=$(stat -c%s "$ARCHIVE")
echo "[$TIMESTAMP] OK: backup $ARCHIVE ($ARCHIVE_SIZE bytes)"

# Retention — apaga archives > RETENTION_DAYS dias
DELETED=$(find "$BACKUP_DIR" -name "baileys-auth-*.tar.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    echo "[$TIMESTAMP] cleanup: removed $DELETED old archives (>$RETENTION_DAYS days)"
fi

# Audit log — fácil pra grep histórico (sem PII, só size + timestamp)
echo "[$TIMESTAMP] DONE size=$ARCHIVE_SIZE retention=$RETENTION_DAYS" >> "$BACKUP_DIR/.audit.log"

exit 0
