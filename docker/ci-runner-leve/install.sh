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
install -m 644 "$DIR/ci-runner-leve@.service" "$DIR/ci-runner-leve-atualiza.service" \
  "$DIR/ci-runner-leve-atualiza.timer" /etc/systemd/system/
systemctl daemon-reload

for N in $(seq 1 "$VAGAS"); do
  if ! docker inspect "ci-leve-$N" >/dev/null 2>&1; then
    CI_LEVE_REPO="$REPO" CI_LEVE_IMAGE="$IMAGE" bash "$DIR/cria-vaga.sh" "$N" "$REG_TOKEN"
  fi
  systemctl enable --now "ci-runner-leve@$N" >/dev/null
done
systemctl --no-pager --plain list-units 'ci-runner-leve@*' | grep -c running | xargs echo "vagas rodando:"

# Daqui em diante a atualização é sozinha: o timer confere o main a cada 15 min
# e troca as vagas quando docker/ci-runner-leve/ muda (atualiza.sh). O clone
# acompanha o main, não o branch de um PR (que é apagado depois do merge).
git -C "$DIR/../.." config remote.origin.fetch "+refs/heads/main:refs/remotes/origin/main"
mkdir -p /var/lib/ci-runner-leve
git -C "$DIR/../.." rev-parse HEAD:docker/ci-runner-leve > /var/lib/ci-runner-leve/arvore-implantada
systemctl enable --now ci-runner-leve-atualiza.timer >/dev/null
