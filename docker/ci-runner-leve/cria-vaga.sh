#!/usr/bin/env bash
# Cria o container de UMA vaga (sem iniciar). Uso: cria-vaga.sh <N> [REG_TOKEN]
# Sem REG_TOKEN o container nasce sem registro e precisa receber a credencial
# de um runner já registrado (é o que o atualiza.sh faz).
# Isolamento: sem mount do host, sem capabilities, sem sudo (no-new-privileges),
# usuário não-root da imagem, rede bloqueada para LAN/Tailscale/host
# (firewall.sh), teto de CPU/memória/processos.
set -euo pipefail
N="$1"
REG_TOKEN="${2:-}"
REPO="${CI_LEVE_REPO:-wagnerra23/oimpresso.com}"
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:2.338.0}"
docker create --name "ci-leve-$N" --network ci-leve \
  --cap-drop ALL --security-opt no-new-privileges \
  --cpus 2 --memory 3g --pids-limit 2048 \
  -e REPO="$REPO" -e LABELS=ct100-leve -e RUNNER_NAME="ct100-leve-$N" \
  ${REG_TOKEN:+-e REG_TOKEN="$REG_TOKEN"} "$IMAGE" >/dev/null
