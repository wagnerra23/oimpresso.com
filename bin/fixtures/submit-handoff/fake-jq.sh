#!/usr/bin/env bash
set -euo pipefail
file="${@: -1}"
expr="${*:1:$#-1}"
# Fixture deliberadamente mínima: o parser REAL é jq; aqui controlamos apenas as
# respostas que exercitam os branches HTTP do transportador.
grep -q '^{' "$file" || exit 4
if [[ "$expr" == *'result.isError'* ]]; then
  if grep -q '"isError":false' "$file"; then printf 'false\n'; else printf 'true\n'; fi
elif [[ "$expr" == *'content[0].text'* ]]; then
  if grep -q 'pending' "$file"; then printf 'pending\n';
  elif grep -q 'sig inválida' "$file"; then printf 'sig inválida\n';
  else printf '%s\n' "$(cat "$file")"; fi
fi
