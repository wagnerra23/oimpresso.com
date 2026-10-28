#!/usr/bin/env bash
# Hook ACTIONS_RUNNER_HOOK_JOB_STARTED: roda antes de cada job, antes do actions/checkout.
# O runner é persistente, então o workspace sobrevive entre jobs. Um job anterior com
# sparse-checkout deixava o índice marcado; o checkout seguinte desligava o sparse, mas
# arquivos versionados ficavam fora da árvore e o job caía com "Cannot find module"
# (medido 2026-10-28: 14 de 25 falhas nos ct100-leve-*). Apagar o workspace inteiro
# devolve ao job a árvore limpa que o ubuntu-latest dá.
set -euo pipefail
ws="${GITHUB_WORKSPACE:-}"
case "$ws" in
  /home/runner/_work/*) ;;
  *) echo "limpa-workspace: GITHUB_WORKSPACE fora de /home/runner/_work ('$ws'), nada apagado"; exit 0 ;;
esac
if [ -d "$ws" ]; then
  find "$ws" -mindepth 1 -maxdepth 1 -exec rm -rf {} +
  echo "limpa-workspace: $ws esvaziado"
fi
