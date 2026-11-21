#!/usr/bin/env bash
# Primeira partida: registra o runner com o token de registro (vale 1 hora,
# serve só para registrar). Daí em diante o runner usa a credencial própria
# que o config.sh grava no container, e o token não é mais lido.
#
# --disableupdate: medido 2026-11-19, as 8 vagas (imagem 2.338.0, com a 2.338.2 já
# publicada) estavam SEM a pasta /home/runner/bin e em laço "Runner.Listener: No such
# file or directory" (exit 127). A pasta só some assim numa auto-atualização que não
# terminou; o registro no GitHub dava as 8 offline. Quem atualiza a versão agora é o
# atualiza.sh, reconstruindo a imagem — o runner não se atualiza sozinho.
set -euo pipefail
cd /home/runner
if [ ! -x bin/Runner.Listener ]; then
  echo "bin/Runner.Listener ausente: container quebrado, recrie a vaga (atualiza.sh ou cria-vaga.sh)" >&2
  exit 3
fi
if [ ! -f .runner ]; then
  : "${REG_TOKEN:?runner ainda não registrado e sem REG_TOKEN (rode install.sh)}"
  ./config.sh --unattended --replace --disableupdate \
    --url "https://github.com/${REPO}" --token "$REG_TOKEN" \
    --name "$RUNNER_NAME" --labels "$LABELS" --work _work
fi
unset REG_TOKEN
exec ./run.sh
