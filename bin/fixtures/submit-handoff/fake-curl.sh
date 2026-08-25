#!/usr/bin/env bash
set -euo pipefail
out=''
log="${FAKE_CURL_LOG:?}"
scenario="${FAKE_CURL_SCENARIO:-success}"
count=0
[ -f "${log}.count" ] && count="$(cat "${log}.count")"
count=$((count + 1))
printf '%s' "$count" > "${log}.count"

printf '%s\0' "$@" >> "$log"
while [ "$#" -gt 0 ]; do
  case "$1" in
    -o) out="$2"; shift 2 ;;
    --data-binary) printf 'BODY=%s\n' "$2" >> "$log"; shift 2 ;;
    *) shift ;;
  esac
done

if [ "$scenario" = 'network' ]; then exit 7; fi
if [ "$scenario" = 'first-fail' ] && [ "$count" -eq 1 ]; then scenario='http500'; fi
case "$scenario" in
  success) printf '%s' '{"result":{"isError":false,"content":[{"text":"pending"}]}}' > "$out"; printf '200' ;;
  iserror) printf '%s' '{"result":{"isError":true,"content":[{"text":"sig inválida"}]}}' > "$out"; printf '200' ;;
  rpcerror) printf '%s' '{"error":{"code":-32000,"message":"sem scope"}}' > "$out"; printf '200' ;;
  invalid) printf '%s' 'não-json' > "$out"; printf '200' ;;
  empty) : > "$out"; printf '200' ;;
  http401) printf '%s' '{"error":{"message":"unauthorized"}}' > "$out"; printf '401' ;;
  http403) printf '%s' '{"error":{"message":"forbidden"}}' > "$out"; printf '403' ;;
  http422) printf '%s' '{"error":{"message":"invalid"}}' > "$out"; printf '422' ;;
  http500) printf '%s' '{"error":{"message":"server"}}' > "$out"; printf '500' ;;
  first-fail) printf '%s' '{"result":{"isError":false,"content":[{"text":"pending"}]}}' > "$out"; printf '200' ;;
  *) exit 9 ;;
esac
