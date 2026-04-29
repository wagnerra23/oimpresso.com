#!/bin/bash
# MEM-MCP-1.b (ADR 0053) — Bootstrap v2 (sem scp manual)
#
# Diferença vs v1: gera SSH key NOVA dentro do CT 100 + mostra pubkey
# pra você adicionar no Hostinger. Elimina a necessidade de scp da
# chave do PC pro CT.
#
# Uso (cola direto no console Proxmox CT 100):
#   curl -fsSL https://raw.githubusercontent.com/wagnerra23/oimpresso.com/main/docker/oimpresso-mcp/scripts/bootstrap-ct100-v2.sh | bash

set -euo pipefail

OIMP_BASE="/opt/oimpresso-mcp"
REPO_URL="https://github.com/wagnerra23/oimpresso.com.git"

log()   { echo ">>> $*"; }
warn()  { echo "!!! $*" >&2; }
title() { echo ""; echo "================================================================"; echo " $*"; echo "================================================================"; }

# Sanity
[ "$(id -u)" -eq 0 ] || { warn "Rode como root"; exit 1; }
command -v docker >/dev/null || { warn "Docker não encontrado — esse script assume CT 100 docker-host"; exit 1; }
docker ps >/dev/null || { warn "Docker daemon não roda ou sem permissão"; exit 1; }

title "Bootstrap MCP server v2 — modo guiado"

log "1/6: Criando estrutura $OIMP_BASE"
mkdir -p "$OIMP_BASE"/{ssh,storage,bootstrap-cache,logs}
chmod 700 "$OIMP_BASE/ssh"

log "2/6: Clonando ou atualizando o repo"
if [ ! -d "$OIMP_BASE/code/.git" ]; then
    git clone "$REPO_URL" "$OIMP_BASE/code"
else
    cd "$OIMP_BASE/code"
    git fetch --quiet origin
    git reset --hard origin/main
fi
cd "$OIMP_BASE/code"
git config core.fileMode false

log "3/6: Gerando SSH key nova (se não existir) pro tunnel ao Hostinger"
KEY_PATH="$OIMP_BASE/ssh/id_ed25519_oimpresso"
if [ ! -f "$KEY_PATH" ]; then
    ssh-keygen -t ed25519 -f "$KEY_PATH" -N "" -C "oimpresso-mcp@ct100" >/dev/null
    log "Chave gerada: $KEY_PATH"
else
    log "Chave já existe: $KEY_PATH (mantendo)"
fi
chmod 600 "$KEY_PATH"
chmod 644 "$KEY_PATH.pub"

log "4/6: Criando/preservando .env"
ENV_FILE="$OIMP_BASE/code/docker/oimpresso-mcp/.env"
ENV_EXAMPLE="$OIMP_BASE/code/docker/oimpresso-mcp/.env.example"

if [ ! -f "$ENV_FILE" ]; then
    cp "$ENV_EXAMPLE" "$ENV_FILE"
    # Gera SYNC_TOKEN automaticamente
    SYNC_TOKEN=$(openssl rand -hex 32)
    sed -i "s|GERAR_VIA_OPENSSL_RAND_HEX_32|$SYNC_TOKEN|" "$ENV_FILE"
    log ".env criado a partir do .env.example (SYNC_TOKEN gerado)"
else
    log ".env já existe (preservando)"
fi

log "5/6: Verificando rede Docker docker-host_default"
docker network inspect docker-host_default >/dev/null 2>&1 || {
    warn "Rede docker-host_default não existe — precisa Traefik configurado primeiro"
    exit 4
}

log "6/6: Verificando se .env está completo"
HAS_PLACEHOLDERS=0
if grep -q "COPIAR_DO_HOSTINGER" "$ENV_FILE" 2>/dev/null; then
    HAS_PLACEHOLDERS=1
fi

# ============================================================================
# Output final — mostra próximos passos manuais
# ============================================================================

title "Estrutura criada com sucesso!"

echo ""
echo "Status atual:"
echo "  ✓ /opt/oimpresso-mcp/        criado"
echo "  ✓ Repo clonado em /opt/oimpresso-mcp/code"
echo "  ✓ SSH key gerada em $KEY_PATH"
echo "  ✓ .env criado em $ENV_FILE"
if [ $HAS_PLACEHOLDERS -eq 1 ]; then
    echo "  ⚠ .env ainda com placeholders (precisa editar)"
else
    echo "  ✓ .env já preenchido"
fi
echo ""

title "PRÓXIMOS 3 PASSOS (faça nessa ordem)"

echo ""
echo "📋 PASSO 1: Adicionar a chave pública no Hostinger"
echo "    ─────────────────────────────────────────────"
echo "    Copie a chave abaixo:"
echo ""
echo "──── COPY START ────"
cat "$KEY_PATH.pub"
echo "──── COPY END ────"
echo ""
echo "    Faça login no Hostinger e adicione em ~/.ssh/authorized_keys:"
echo ""
echo "      ssh -p 65002 u906587222@148.135.133.115"
echo "      mkdir -p ~/.ssh && chmod 700 ~/.ssh"
echo "      echo 'COLE_A_LINHA_PUBKEY_AQUI' >> ~/.ssh/authorized_keys"
echo "      chmod 600 ~/.ssh/authorized_keys"
echo "      exit"
echo ""
echo "    Teste do CT 100:"
echo "      ssh -i $KEY_PATH -p 65002 u906587222@148.135.133.115 'echo OK'"
echo ""

if [ $HAS_PLACEHOLDERS -eq 1 ]; then
    echo "📋 PASSO 2: Editar .env (preencher 2 valores)"
    echo "    ─────────────────────────────────────────────"
    echo "      nano $ENV_FILE"
    echo ""
    echo "    Substituir os placeholders por:"
    echo "      APP_KEY=base64:...         (copiar EXATO do Hostinger)"
    echo "      DB_PASSWORD=...            (mesma senha do MySQL Hostinger)"
    echo ""
    echo "    Como pegar APP_KEY do Hostinger:"
    echo "      ssh -p 65002 u906587222@148.135.133.115 \\"
    echo "        'grep APP_KEY ~/domains/oimpresso.com/public_html/.env'"
    echo ""
    echo "    (SYNC_TOKEN já foi gerado automaticamente)"
    echo ""
fi

echo "📋 PASSO ${HAS_PLACEHOLDERS}: Build + up containers"
[ $HAS_PLACEHOLDERS -eq 0 ] && echo "    PASSO 2 (já que .env está completo)"
echo "    ─────────────────────────────────────────────"
echo "      cd $OIMP_BASE/code/docker/oimpresso-mcp"
echo "      docker compose build"
echo "      docker compose up -d"
echo "      docker compose logs -f tunnel    # aguarda healthcheck"
echo ""

echo "📋 SMOKE FINAL (de qualquer máquina):"
echo "    ─────────────────────────────────────────────"
echo "      curl https://mcp.oimpresso.com/api/mcp/health"
echo ""
echo "    Esperado:"
echo "      {\"status\":\"ok\",\"service\":\"oimpresso-mcp\",...}"
echo ""

title "Re-rodar este script é idempotente"
echo ""
echo "  Pode rodar quantas vezes quiser — só ajusta o necessário."
echo "  Quando terminar os 3 passos, container está up e respondendo."
echo ""
