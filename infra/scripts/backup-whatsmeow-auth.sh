#!/bin/bash
# backup-whatsmeow-auth.sh — backup diário CONSISTENTE do auth-state WhatsApp (wuzapi/whatsmeow) no CT 100
#
# Contexto (migração de daemon):
#   O daemon migrou de Baileys → wuzapi/whatsmeow (asternic/wuzapi, container `whatsapp-whatsmeow`).
#   O store deixou de ser um diretório de arquivos de sessão (Baileys) e passou a ser DOIS bancos
#   SQLite em WAL mode dentro de /srv/docker/whatsapp-whatsmeow/sessions (bind → /app/dbdata):
#     - main.db   → whatsmeow: whatsmeow_device / _identity_keys / _pre_keys / _sessions /
#                   _sender_keys / _app_state_sync_keys / _lid_map  (auth WhatsApp Web pareada)
#     - users.db  → wuzapi: tabela `users` (token→canal por business) + `message_history`
#   Por serem SQLite em WAL, um `cp`/`tar` do arquivo ABERTO não é consistente (pode copiar o
#   .db sem os frames do -wal → backup corrompido/atrasado). Este script usa a ONLINE BACKUP API
#   do SQLite (python3 stdlib, sqlite3.Connection.backup) que produz um snapshot íntegro mesmo
#   com o daemon escrevendo — SEM downtime, sem parar o container.
#
# Substitui: infra/scripts/backup-baileys-auth.sh (Baileys). O path antigo
#   /srv/docker/whatsapp-baileys/sessions ficou VAZIO após a migração → o backup falhava em
#   silêncio (log "source dir ... está vazio") desde ≥2026-06-30, /backups/baileys-auth/ vazio.
#
# SPOF de origem: incidente 2026-05-14 (perda de auth-state biz=1 prod → re-scan QR em cada canal
#   + perda do LID map + 90d de history sync). Este backup é a defesa contra reincidência.
#
# Roda em: CT 100 Proxmox (root @ ct100-mcp via Tailscale)
# Cron:    daily 03:00 BRT — ver infra/cron/baileys-backup
# Restore: memory/requisitos/Whatsapp/runbooks/restore-auth-state.md
#
# Deploy (Wagner-gated — NÃO instalar sem aprovação):
#   base64 infra/scripts/backup-whatsmeow-auth.sh | tailscale ssh root@ct100-mcp 'base64 -d > /opt/scripts/backup-whatsmeow-auth.sh'
#   tailscale ssh root@ct100-mcp 'chmod 755 /opt/scripts/backup-whatsmeow-auth.sh'
#   # cron: ver infra/cron/baileys-backup (substitui /etc/cron.d/baileys-backup)

set -euo pipefail
umask 077   # backups contêm chaves de criptografia + tokens wuzapi → arquivos root-only

SESSIONS_DIR="/srv/docker/whatsapp-whatsmeow/sessions"
DBS=("main.db" "users.db")
SENTINEL_DB="main.db"            # DB que precisa conter auth pareada (whatsmeow_device)
BACKUP_DIR="/srv/backup/whatsmeow"
RETENTION_DAYS=14
DATE=$(date -u +%Y%m%d-%H%M)
TIMESTAMP=$(date -u +"%Y-%m-%dT%H:%M:%SZ")

log() { echo "[$TIMESTAMP] $*"; }

# --- Pre-flight (falha ALTO — operador investiga, nunca segue com store suspeito) ---
if [ ! -d "$SESSIONS_DIR" ]; then
    log "ERROR: sessions dir $SESSIONS_DIR não existe — daemon down ou mudou de path?" >&2
    exit 1
fi
for db in "${DBS[@]}"; do
    if [ ! -s "$SESSIONS_DIR/$db" ]; then
        log "ERROR: $SESSIONS_DIR/$db ausente ou vazio — daemon down ou perdeu store?" >&2
        exit 1
    fi
done

mkdir -p "$BACKUP_DIR"
ARCHIVE="$BACKUP_DIR/whatsmeow-auth-$DATE.tar.gz"
if [ -f "$ARCHIVE" ]; then
    log "SKIP: archive $ARCHIVE já existe (idempotência cron-retry)" >&2
    exit 0
fi

STAGING=$(mktemp -d "${TMPDIR:-/tmp}/whatsmeow-auth.XXXXXX")
trap 'rm -rf "$STAGING"' EXIT

# --- Snapshot consistente (online backup API) + integrity_check por DB ---
for db in "${DBS[@]}"; do
    src="$SESSIONS_DIR/$db"
    dst="$STAGING/$db"
    python3 - "$src" "$dst" <<'PY'
import sqlite3, sys
src, dst = sys.argv[1], sys.argv[2]
# origem em read-only (mode=ro): garante ZERO escrita no store de produção.
s = sqlite3.connect(f"file:{src}?mode=ro", uri=True, timeout=30)
d = sqlite3.connect(dst)
try:
    s.execute("PRAGMA busy_timeout=30000")          # espera writer soltar lock (WAL concorrente)
    s.backup(d)                                      # snapshot íntegro página-a-página
    ic = d.execute("PRAGMA integrity_check").fetchone()[0]
    if ic != "ok":
        sys.stderr.write(f"integrity_check FAIL em {src}: {ic}\n")
        sys.exit(3)
finally:
    d.close(); s.close()
PY
    log "snapshot OK: $db ($(stat -c%s "$dst") bytes, integrity=ok)"
done

# --- Sanity SEMÂNTICO: auth realmente presente (a lição da falha silenciosa do Baileys) ---
# O script antigo só checava "dir não-vazio"; isso não pega store presente-porém-sem-auth.
# Aqui contamos linhas em whatsmeow_device: 0 = nenhum canal pareado = NÃO é backup válido.
DEVICES=$(python3 - "$STAGING/$SENTINEL_DB" <<'PY'
import sqlite3, sys
c = sqlite3.connect(sys.argv[1])
print(c.execute("SELECT count(*) FROM whatsmeow_device").fetchone()[0])
PY
)
if [ "$DEVICES" -lt 1 ]; then
    log "ERROR: whatsmeow_device tem 0 linhas — nenhum canal pareado, auth vazia. Abortando (não gravar backup 'verde' vazio)." >&2
    exit 4
fi
# COUNT apenas — nunca logar JID/telefone (PII). Multi-tenant: pode haver N canais por N business.
log "sanity: $DEVICES canal(is) pareado(s) em whatsmeow_device"

# --- Empacota os snapshots ÍNTEGROS (não os arquivos abertos do daemon) ---
tar -czf "$ARCHIVE" -C "$STAGING" "${DBS[@]}"
if [ ! -s "$ARCHIVE" ]; then
    log "ERROR: archive $ARCHIVE vazio — falha tar?" >&2
    rm -f "$ARCHIVE"
    exit 2
fi
ARCHIVE_SIZE=$(stat -c%s "$ARCHIVE")
log "OK: backup $ARCHIVE ($ARCHIVE_SIZE bytes, devices=$DEVICES)"

# --- Retention — apaga archives > RETENTION_DAYS dias ---
DELETED=$(find "$BACKUP_DIR" -name "whatsmeow-auth-*.tar.gz" -mtime +$RETENTION_DAYS -delete -print | wc -l)
if [ "$DELETED" -gt 0 ]; then
    log "cleanup: removidos $DELETED archives (>$RETENTION_DAYS dias)"
fi

# --- Audit log (sem PII: só size + count + timestamp) ---
echo "[$TIMESTAMP] DONE size=$ARCHIVE_SIZE devices=$DEVICES retention=$RETENTION_DAYS" >> "$BACKUP_DIR/.audit.log"

exit 0
