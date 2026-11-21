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
VERSAO_PADRAO=$(sed -n 's/^ARG RUNNER_VERSION=//p' "$(dirname "$0")/Dockerfile")
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:$VERSAO_PADRAO}"
# _work em tmpfs (RAM): checkout, node_modules e _tool não tocam o disco do CT 100,
# que é dividido com a produção. Medido 2026-11-19 com as 8 vagas no disco: pressão de
# I/O 55-85% e health do MCP até 4,9 s. O espelho da história (/home/runner/espelho.git,
# ver limpa-workspace.sh) fica fora do tmpfs, em disco, e quase não muda.
# A RAM do tmpfs conta no teto do container, por isso --memory 8g (era 3g):
# _work medido 1,3-2,0 GB por vaga, job leve ~1 GB.
docker create --name "ci-leve-$N" --network ci-leve \
  --cap-drop ALL --security-opt no-new-privileges \
  --cpus 2 --memory 8g --pids-limit 2048 \
  --tmpfs /home/runner/_work:rw,exec,size=4g,uid=1001,gid=1001,mode=0755 \
  -e REPO="$REPO" -e LABELS=ct100-leve -e RUNNER_NAME="ct100-leve-$N" \
  ${REG_TOKEN:+-e REG_TOKEN="$REG_TOKEN"} "$IMAGE" >/dev/null
