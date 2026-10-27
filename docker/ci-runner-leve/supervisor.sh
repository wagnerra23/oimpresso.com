#!/usr/bin/env bash
# Mantém UMA vaga de runner ephemeral viva. Cada volta:
#   1. pede ao GitHub um JIT config (vale para UM job e morre),
#   2. sobe um container descartável com ele,
#   3. quando o job termina o container some, e a volta recomeça.
# O PAT fica SÓ no host (arquivo 600) e nunca entra no container: o container
# recebe apenas o JIT config de uso único.
# Uso: supervisor.sh <numero-da-vaga>   (chamado pelo ci-runner-leve@.service)
set -euo pipefail

SLOT="${1:?numero da vaga}"
REPO="${CI_LEVE_REPO:-wagnerra23/oimpresso.com}"
LABEL="${CI_LEVE_LABEL:-ct100-leve}"
IMAGE="${CI_LEVE_IMAGE:-oimpresso/ci-runner-leve:2.338.0}"
NETWORK="${CI_LEVE_NETWORK:-ci-leve}"
PAT_FILE="${CI_LEVE_PAT_FILE:-/root/.config/ci-runner-leve/pat}"

[ -r "$PAT_FILE" ] || { echo "sem PAT em $PAT_FILE (ver README)" >&2; exit 2; }
command -v jq >/dev/null || { echo "jq ausente no host" >&2; exit 2; }

while true; do
  NAME="ct100-leve-${SLOT}-$(date +%s)"
  RESP=$(curl -fsS -X POST \
    -H "Authorization: Bearer $(cat "$PAT_FILE")" \
    -H "Accept: application/vnd.github+json" \
    "https://api.github.com/repos/${REPO}/actions/runners/generate-jitconfig" \
    -d "{\"name\":\"${NAME}\",\"runner_group_id\":1,\"labels\":[\"self-hosted\",\"${LABEL}\"],\"work_folder\":\"_work\"}") \
    || { echo "falha ao pedir JIT config; nova tentativa em 30s" >&2; sleep 30; continue; }
  JIT=$(printf '%s' "$RESP" | jq -er .encoded_jit_config) \
    || { echo "resposta sem encoded_jit_config; nova tentativa em 30s" >&2; sleep 30; continue; }

  # Isolamento: sem mount do host, sem capabilities, sem sudo (no-new-privileges),
  # usuário não-root da imagem, rede própria bloqueada para a LAN e o Tailscale
  # (firewall.sh), teto de CPU/memória/processos.
  docker run --rm --name "$NAME" \
    --network "$NETWORK" \
    --cap-drop ALL --security-opt no-new-privileges \
    --cpus 2 --memory 3g --pids-limit 2048 \
    "$IMAGE" ./run.sh --jitconfig "$JIT" \
    || echo "job da vaga ${SLOT} saiu com erro (o veredito vai pro GitHub); seguindo" >&2
done
