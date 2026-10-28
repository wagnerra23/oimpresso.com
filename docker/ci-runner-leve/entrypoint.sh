#!/usr/bin/env bash
# Primeira partida: registra o runner com o token de registro (vale 1 hora,
# serve só para registrar). Daí em diante o runner usa a credencial própria
# que o config.sh grava no container, e o token não é mais lido.
set -euo pipefail
cd /home/runner
if [ ! -f .runner ]; then
  : "${REG_TOKEN:?runner ainda não registrado e sem REG_TOKEN (rode install.sh)}"
  ./config.sh --unattended --replace \
    --url "https://github.com/${REPO}" --token "$REG_TOKEN" \
    --name "$RUNNER_NAME" --labels "$LABELS" --work _work
fi
unset REG_TOKEN
exec ./run.sh
