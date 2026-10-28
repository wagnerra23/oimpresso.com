#!/usr/bin/env bash
# Atualiza as vagas sozinho quando docker/ci-runner-leve/ muda no main.
# Roda pelo ci-runner-leve-atualiza.timer (a cada 15 min). Sem token: a
# credencial do runner (.runner, .credentials, .credentials_rsaparams) é
# copiada do container antigo para o novo, e o entrypoint pula o registro.
# Vaga ocupada com job espera ficar livre; vaga que não volta a rodar em 60 s
# é desfeita (o container antigo volta).
set -euo pipefail
exec 9>/run/ci-runner-leve-atualiza.lock
flock -n 9 || { echo "outra atualização em curso"; exit 0; }

SRC=/opt/oimpresso-ci-src
DIR="$SRC/docker/ci-runner-leve"
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:2.338.0}"
STAMP_DIR=/var/lib/ci-runner-leve
STAMP="$STAMP_DIR/arvore-implantada"
CREDS=(.runner .credentials .credentials_rsaparams)
mkdir -p "$STAMP_DIR"

cd "$SRC"
git fetch -q origin
git checkout -q -B main origin/main
ARVORE=$(git rev-parse HEAD:docker/ci-runner-leve)
if [ "$(cat "$STAMP" 2>/dev/null)" = "$ARVORE" ]; then exit 0; fi
echo "docker/ci-runner-leve mudou ($ARVORE); atualizando as vagas"

docker build -q -t "$IMAGE" "$DIR" >/dev/null
install -m 644 "$DIR/ci-runner-leve@.service" "$DIR/ci-runner-leve-atualiza.service" \
  "$DIR/ci-runner-leve-atualiza.timer" /etc/systemd/system/
systemctl daemon-reload

ocupada() { docker exec "ci-leve-$1" pgrep -f Runner.Worker >/dev/null 2>&1; }
rodando() { [ "$(docker inspect -f '{{.State.Running}}' "ci-leve-$1" 2>/dev/null)" = true ]; }

falhas=0
for C in $(docker ps -a --format '{{.Names}}' | grep -E '^ci-leve-[0-9]+$' | sort -V); do
  N=${C#ci-leve-}
  for _ in $(seq 1 60); do ocupada "$N" || break; sleep 30; done
  if ocupada "$N"; then echo "vaga $N segue ocupada após 30 min; fica para a próxima volta"; falhas=1; continue; fi

  tmp=$(mktemp -d)
  for f in "${CREDS[@]}"; do docker cp -a "$C:/home/runner/$f" "$tmp/$f"; done
  systemctl stop "ci-runner-leve@$N"
  docker rename "$C" "$C-anterior"
  bash "$DIR/cria-vaga.sh" "$N"
  for f in "${CREDS[@]}"; do docker cp -a "$tmp/$f" "$C:/home/runner/$f"; done
  systemctl start "ci-runner-leve@$N"
  sleep 60
  if rodando "$N"; then
    docker container remove "$C-anterior" >/dev/null
    echo "vaga $N atualizada"
  else
    echo "vaga $N não subiu com a imagem nova; voltando ao container anterior"
    systemctl stop "ci-runner-leve@$N"
    docker container remove --force "$C" >/dev/null
    docker rename "$C-anterior" "$C"
    systemctl start "ci-runner-leve@$N"
    falhas=1
  fi
  find "$tmp" -mindepth 1 -delete && rmdir "$tmp"
done

# Só marca como implantado quando todas as vagas foram trocadas; senão a
# próxima volta do timer tenta de novo.
[ "$falhas" = 0 ] && echo "$ARVORE" > "$STAMP"
exit "$falhas"
