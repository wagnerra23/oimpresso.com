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
STAMP_DIR=/var/lib/ci-runner-leve
STAMP="$STAMP_DIR/arvore-implantada"
CREDS=(.runner .credentials .credentials_rsaparams)
mkdir -p "$STAMP_DIR"

cd "$SRC"
git fetch -q origin
git checkout -q -B main origin/main
ARVORE=$(git rev-parse HEAD:docker/ci-runner-leve)

# Versão do runner: a última release do actions/runner. As vagas rodam com
# --disableupdate, então é esta rotina que acompanha a versão; o GitHub recusa
# runner que fica velho demais. Sem rede para a API, mantém a versão implantada
# (ou a default do Dockerfile) em vez de falhar.
VERSAO=$(curl -fsS --max-time 20 https://api.github.com/repos/actions/runner/releases/latest \
  | jq -r '.tag_name // empty' | sed 's/^v//') || VERSAO=""
if ! [[ "$VERSAO" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  VERSAO=$(awk '{print $2}' "$STAMP" 2>/dev/null || true)
  [[ "$VERSAO" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || VERSAO=$(sed -n 's/^ARG RUNNER_VERSION=//p' "$DIR/Dockerfile")
  echo "API de releases indisponível; seguindo com a versão $VERSAO"
fi
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:$VERSAO}"
export CI_LEVE_IMAGE="$IMAGE"

if [ "$(cat "$STAMP" 2>/dev/null)" = "$ARVORE $VERSAO" ]; then exit 0; fi
echo "docker/ci-runner-leve ($ARVORE) ou versão do runner ($VERSAO) mudou; atualizando as vagas"

docker build -q --build-arg RUNNER_VERSION="$VERSAO" -t "$IMAGE" "$DIR" >/dev/null
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

  # Tudo o que é só leitura vem ANTES da última checagem, para que entre "livre" e
  # "parada" não sobre nada além do systemctl stop. Medido 2026-11-24: com fila cheia
  # a vaga fica livre ~2 s entre um job e o seguinte (vaga 7: job terminou 20:22:44Z,
  # o próximo chegou 20:22:46Z). Um restart nesse intervalo mata o job recém-entregue:
  # ele fica 10 min "em andamento" sem nenhum passo e cai com "The self-hosted runner
  # lost communication with the server" (jobs 138740819248 e 138749776154).
  tmp=$(mktemp -d)
  for f in "${CREDS[@]}"; do docker cp -a "$C:/home/runner/$f" "$tmp/$f"; done
  # Registro antigo pode não ter --disableupdate; marca na credencial copiada,
  # que é o mesmo campo que o config.sh grava (RunnerSettings.DisableUpdate).
  jq '.disableUpdate = true' "$tmp/.runner" > "$tmp/.runner.novo" && cat "$tmp/.runner.novo" > "$tmp/.runner"
  rm -f "$tmp/.runner.novo"
  desde=$(date -u -d '-2 sec' +%Y-%m-%dT%H:%M:%SZ)
  if ocupada "$N"; then
    echo "vaga $N pegou job durante a cópia da credencial; fica para a próxima volta"
    find "$tmp" -mindepth 1 -delete && rmdir "$tmp"; falhas=1; continue
  fi
  systemctl stop "ci-runner-leve@$N"
  # A janela não chega a zero: o GitHub pode entregar o job entre a checagem e o stop.
  # Quando acontece, o runner já imprimiu "Running job:"; avisa para re-rodar.
  perdido=$(docker logs --since "$desde" "$C" 2>&1 | sed -n 's/^.*Z: Running job: //p' | tail -1)
  [ -n "$perdido" ] && echo "AVISO vaga $N: o job '$perdido' chegou no instante da parada e vai cair em ~10 min; re-rode com gh run rerun <run> --failed"
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
[ "$falhas" = 0 ] && echo "$ARVORE $VERSAO" > "$STAMP"
exit "$falhas"
