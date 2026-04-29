#!/bin/sh
# MEM-MCP-1.b (ADR 0053) — Sidecar SSH tunnel pro Hostinger MySQL
#
# Roda dentro do container `tunnel` (image kroniak/ssh-client + alpine).
# Mantém autossh forever, reconectando se cair.
#
# Bind mount: /opt/oimpresso-mcp/ssh:/root/.ssh:ro (chave do CT 100)
# Expõe: 0.0.0.0:3306 → Hostinger 127.0.0.1:3306 (via SSH)

set -e

echo "[tunnel] Instalando autossh + netcat..."
apk add --no-cache autossh openssh-client netcat-openbsd >/dev/null 2>&1 || true

KEY="/root/.ssh/id_ed25519_oimpresso"
HOST_USER="u906587222"
HOST_IP="148.135.133.115"
HOST_PORT="65002"

if [ ! -f "$KEY" ]; then
    echo "[tunnel] ERRO: chave SSH ausente em $KEY"
    echo "[tunnel] Verifique bind mount: /opt/oimpresso-mcp/ssh:/root/.ssh:ro"
    exit 1
fi

echo "[tunnel] Garantindo permissões da chave..."
chmod 600 "$KEY" 2>/dev/null || true

echo "[tunnel] Adicionando host ao known_hosts (não-interativo)..."
mkdir -p /root/.ssh
ssh-keyscan -p "$HOST_PORT" "$HOST_IP" 2>/dev/null > /root/.ssh/known_hosts || true
chmod 600 /root/.ssh/known_hosts

echo "[tunnel] Validando conexão SSH ao Hostinger (test rápido)..."
if ssh -i "$KEY" -p "$HOST_PORT" \
       -o BatchMode=yes \
       -o ConnectTimeout=10 \
       -o StrictHostKeyChecking=accept-new \
       "${HOST_USER}@${HOST_IP}" 'echo "[tunnel] SSH test OK"'; then
    echo "[tunnel] SSH validado com sucesso. Iniciando autossh..."
else
    echo "[tunnel] ERRO: SSH test falhou. Verifique que a pubkey foi adicionada"
    echo "[tunnel] em ~/.ssh/authorized_keys do user $HOST_USER no Hostinger."
    echo "[tunnel] Vai ficar tentando reconectar..."
fi

echo "[tunnel] autossh -M 0 -N -L 0.0.0.0:3306:127.0.0.1:3306 ..."

# Variáveis de env do autossh (mais limpo que -o no comando)
export AUTOSSH_GATETIME=0
export AUTOSSH_POLL=30
export AUTOSSH_LOGLEVEL=4

exec autossh -M 0 \
    -o ServerAliveInterval=30 \
    -o ServerAliveCountMax=3 \
    -o ExitOnForwardFailure=yes \
    -o StrictHostKeyChecking=accept-new \
    -o UserKnownHostsFile=/root/.ssh/known_hosts \
    -i "$KEY" \
    -p "$HOST_PORT" \
    -N \
    -L "0.0.0.0:3306:127.0.0.1:3306" \
    "${HOST_USER}@${HOST_IP}"
