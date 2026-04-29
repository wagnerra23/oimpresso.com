#!/bin/bash
# MEM-MCP-1.b (ADR 0053) — Bootstrap idempotente do oimpresso-mcp em CT 100
#
# Roda no CT 100 docker-host. Cria toda a estrutura e sobe o container.
# Idempotente: pode rodar quantas vezes quiser, só ajusta o necessário.
#
# Uso:
#   ssh root@<ct-100>   # ou pct enter 100 do host Proxmox
#   curl -fsSL https://raw.githubusercontent.com/wagnerra23/oimpresso.com/main/docker/oimpresso-mcp/scripts/bootstrap-ct100.sh | bash
#
# Ou clonar e rodar:
#   git clone https://github.com/wagnerra23/oimpresso.com.git /tmp/oimp && \
#     /tmp/oimp/docker/oimpresso-mcp/scripts/bootstrap-ct100.sh

set -euo pipefail

OIMP_BASE="/opt/oimpresso-mcp"
REPO_URL="https://github.com/wagnerra23/oimpresso.com.git"
HOSTINGER_HOST="148.135.133.115"
HOSTINGER_USER="u906587222"
HOSTINGER_PORT="65002"

log() {
    echo ">>> $*"
}

err() {
    echo "!!! $*" >&2
}

# Sanity check
if [ "$(id -u)" -ne 0 ]; then
    err "Rode como root (ou sudo)"
    exit 1
fi

if ! command -v docker >/dev/null 2>&1; then
    err "Docker não encontrado — esse script assume CT 100 docker-host."
    exit 1
fi

if ! docker ps >/dev/null 2>&1; then
    err "Docker daemon não está rodando ou sem permissão"
    exit 1
fi

log "1/8: Criando estrutura $OIMP_BASE"
mkdir -p "$OIMP_BASE"/{ssh,storage,bootstrap-cache,logs}
chmod 700 "$OIMP_BASE/ssh"

log "2/8: Clonando ou atualizando o repo"
if [ ! -d "$OIMP_BASE/code/.git" ]; then
    git clone "$REPO_URL" "$OIMP_BASE/code"
else
    cd "$OIMP_BASE/code"
    git fetch --quiet origin
    git reset --hard origin/main
fi
cd "$OIMP_BASE/code"
git config core.fileMode false

log "3/8: Validando SSH key pro tunnel ao Hostinger"
KEY_PATH="$OIMP_BASE/ssh/id_ed25519_oimpresso"
if [ ! -f "$KEY_PATH" ]; then
    err "Chave SSH ausente em $KEY_PATH"
    err "Copie ela manualmente:"
    err "    scp ~/.ssh/id_ed25519_oimpresso root@<este-ct>:$KEY_PATH"
    err "    chmod 600 $KEY_PATH"
    exit 2
fi
chmod 600 "$KEY_PATH"

# Tunnel test rápido (não-fatal — se falhar, container ainda sobe e tenta retry)
if ! ssh -o BatchMode=yes -o ConnectTimeout=10 -o StrictHostKeyChecking=accept-new \
       -i "$KEY_PATH" -p "$HOSTINGER_PORT" "${HOSTINGER_USER}@${HOSTINGER_HOST}" \
       'echo OK' >/dev/null 2>&1; then
    err "AVISO: SSH test pro Hostinger falhou. Verifique key/permissões."
    err "Container vai tentar reconectar em loop."
fi

log "4/8: Validando .env"
ENV_FILE="$OIMP_BASE/code/docker/oimpresso-mcp/.env"
ENV_EXAMPLE="$OIMP_BASE/code/docker/oimpresso-mcp/.env.example"

if [ ! -f "$ENV_FILE" ]; then
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    err ".env CRIADO a partir do .env.example"
    err "EDITE manualmente antes de continuar:"
    err "    nano $ENV_FILE"
    err ""
    err "Você precisa setar:"
    err "  APP_KEY        — copiar do Hostinger (.env line APP_KEY=base64:...)"
    err "  DB_PASSWORD    — copiar do Hostinger (mesma senha do MySQL)"
    err "  COPILOTO_MCP_SYNC_TOKEN — gerar com: openssl rand -hex 32"
    err ""
    err "Quando .env estiver completo, rode este script de novo."
    exit 3
fi

# Validações mínimas do .env
if grep -q "COPIAR_DO_HOSTINGER" "$ENV_FILE" 2>/dev/null; then
    err ".env ainda tem placeholders 'COPIAR_DO_HOSTINGER'"
    err "Edite $ENV_FILE manualmente"
    exit 3
fi

log "5/8: Verificando rede Docker docker-host_default"
if ! docker network inspect docker-host_default >/dev/null 2>&1; then
    err "Rede docker-host_default não existe — Traefik não foi configurado?"
    err "Rode primeiro o setup do CT 100 (auto-memória project_infra_padrao_empresa)"
    exit 4
fi

log "6/8: Build da imagem oimpresso/mcp"
cd "$OIMP_BASE/code/docker/oimpresso-mcp"
docker compose build --pull

log "7/8: Iniciando containers (mcp + tunnel)"
docker compose up -d
echo ""
echo "Aguardando healthcheck do tunnel (pode demorar 30-60s)..."
for i in $(seq 1 20); do
    sleep 3
    if docker compose ps --format json 2>/dev/null | grep -q '"Health":"healthy".*tunnel'; then
        log "Tunnel saudável ✓"
        break
    fi
    if [ "$i" -eq 20 ]; then
        err "AVISO: tunnel ainda não healthy após 60s — veja logs"
        echo "    docker compose logs tunnel"
    fi
done

log "8/8: Smoke test"
sleep 5
echo ""
echo "--- /api/mcp/health (interno via container nginx) ---"
docker compose exec -T mcp curl -fsS http://localhost/api/mcp/health 2>&1 | head -5

echo ""
echo "--- /healthz (nginx-only, sem PHP) ---"
docker compose exec -T mcp curl -fsS http://localhost/healthz 2>&1 | head -3

echo ""
echo "==============================================================="
echo " Bootstrap concluído"
echo "==============================================================="
echo ""
echo "Containers rodando:"
docker compose ps
echo ""
echo "Próximos passos:"
echo "  1. Verificar Traefik labels picked up:"
echo "       docker logs traefik 2>&1 | grep mcp.oimpresso.com"
echo ""
echo "  2. De fora (após DNS + cert R12, ~30-60s):"
echo "       curl https://mcp.oimpresso.com/api/mcp/health"
echo ""
echo "  3. Gerar primeiro token (no Hostinger, DB compartilhado):"
echo "       php artisan mcp:token:gerar --user=1 --name='Wagner'"
echo ""
echo "  4. Configurar Claude Code (.claude/settings.local.json):"
cat <<'JSONEOF'
       {
         "mcpServers": {
           "oimpresso": {
             "url": "https://mcp.oimpresso.com/api/mcp",
             "headers": {"Authorization": "Bearer mcp_..."}
           }
         }
       }
JSONEOF
echo ""
