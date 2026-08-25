#!/usr/bin/env bash
set -euo pipefail
if [ "${FAKE_SIGN_FAIL:-0}" = "1" ]; then
  echo 'assinador fake falhou' >&2
  exit 1
fi
printf '%s' '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"handoff-submit","arguments":{"slug":"fixture"}}}'
