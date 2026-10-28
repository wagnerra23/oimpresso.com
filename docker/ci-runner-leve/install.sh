#!/usr/bin/env bash
# Instala (ou completa) as vagas no host. Idempotente: vaga que já existe fica.
# Lê da ENTRADA PADRÃO um token de REGISTRO de runner (vale 1 hora), gerado por
# quem tem admin no repo:
#   gh api -X POST repos/wagnerra23/oimpresso.com/actions/runners/registration-token --jq .token \
#     | ssh root@CT100 'VAGAS=8 /opt/oimpresso-ci-src/docker/ci-runner-leve/install.sh'
# Nenhum token fica guardado no host. O token sobra só na config dos containers
# criados (docker inspect) e já está expirado uma hora depois.
set -euo pipefail

DIR=$(cd "$(dirname "$0")" && pwd)
VAGAS="${VAGAS:-8}"
REPO="${CI_LEVE_REPO:-wagnerra23/oimpresso.com}"
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:2.338.0}"
read -r REG_TOKEN
[ -n "$REG_TOKEN" ] || { echo "token de registro vazio na entrada padrão" >&2; exit 2; }

docker build -q -t "$IMAGE" "$DIR" >/dev/null
bash "$DIR/firewall.sh"
mkdir -p /opt/oimpresso
ln -sfn "$DIR" /opt/oimpresso/ci-runner-leve
install -m 644 "$DIR/ci-runner-leve@.service" /etc/systemd/system/
systemctl daemon-reload

for N in $(seq 1 "$VAGAS"); do
  if ! docker inspect "ci-leve-$N" >/dev/null 2>&1; then
    # Isolamento: sem mount do host, sem capabilities, sem sudo
    # (no-new-privileges), usuário não-root da imagem, rede bloqueada para
    # LAN/Tailscale/host (firewall.sh), teto de CPU/memória/processos.
    docker create --name "ci-leve-$N" --network ci-leve \
      --cap-drop ALL --security-opt no-new-privileges \
      --cpus 2 --memory 3g --pids-limit 2048 \
      -e REPO="$REPO" -e LABELS=ct100-leve -e RUNNER_NAME="ct100-leve-$N" \
      -e REG_TOKEN="$REG_TOKEN" "$IMAGE" >/dev/null
  fi
  systemctl enable --now "ci-runner-leve@$N" >/dev/null
done
systemctl --no-pager --plain list-units 'ci-runner-leve@*' | grep -c running | xargs echo "vagas rodando:"
