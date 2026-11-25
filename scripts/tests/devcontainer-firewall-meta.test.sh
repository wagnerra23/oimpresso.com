#!/usr/bin/env bash
# devcontainer-firewall-meta.test.sh — o passo do /meta do init-firewall.sh segue com
# aviso quando o GitHub não entrega as faixas, em vez de derrubar o script.
#
# Por que existe: em 2026-11-23 a run agendada 45249497636 do devcontainer-firewall.yml
# saiu com exit 1 logo depois de "faixas do GitHub (api.github.com/meta)…", sem aplicar
# as regras. O script prometia seguir com os A records quando o /meta falhasse, mas só
# cobria o curl sem conexão: com HTTP 4xx/5xx o curl sem -f sai 0, o corpo chega ao grep
# sem nenhuma faixa, o grep sai 1 e o pipefail derruba o script.
#
# Hermético: roda o bloco REAL do script (extraído do arquivo, do log do /meta até o
# "aplicando regras", exclusive) com curl, ipset e log trocados por funções. Zero rede,
# zero iptables, zero container. Exit 0 = passa.
#
# Casos:
#   0. o bloco foi achado no arquivo e não traz iptables (senão os casos testariam o vazio)
#   1. HTTP 200 com faixas         → exit 0, as faixas IPv4 entram no ipset (sem repetir)
#   2. HTTP 403 (limite de API)    → exit 0, aviso, nenhuma faixa
#   3. HTTP 200 sem nenhuma faixa  → exit 0, aviso, nenhuma faixa
#   4. curl sem conexão (exit 28)  → exit 0, aviso, nenhuma faixa
#   5. HTTP 500 com faixa no corpo → exit 0, aviso, NENHUMA faixa (corpo de erro não libera endereço)
#
# Prova de que morde: com o script de antes do conserto, os casos 2 e 3 falham. Sem o
# `-f`, falha o 5; sem o `|| true` do grep, falha o 3.
# Rodar contra outro arquivo: FIREWALL_SCRIPT=<caminho> bash <este teste>
set -euo pipefail

SCRIPT="${FIREWALL_SCRIPT:-$(cd "$(dirname "$0")/../.." && pwd)/.devcontainer/init-firewall.sh}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
FAILS=0
ok()   { echo "  ✓ $1"; }
fail() { echo "  ✗ $1"; FAILS=$((FAILS+1)); }

echo "devcontainer-firewall-meta: $SCRIPT"

# ── caso 0: o bloco existe ───────────────────────────────────────────────────
sed -n '/^log "faixas do GitHub/,/^log "aplicando regras/p' "$SCRIPT" | sed '$d' > "$TMP/bloco.sh"
if grep -q 'api.github.com/meta' "$TMP/bloco.sh" && ! grep -q 'iptables' "$TMP/bloco.sh"; then
  ok "bloco do /meta achado ($(wc -l < "$TMP/bloco.sh" | tr -d ' ') linhas, sem iptables)"
else
  fail "bloco do /meta não achado entre 'faixas do GitHub' e 'aplicando regras'"
  echo "FALHOU: $FAILS"; exit 1
fi

# ── os substitutos: só o que o bloco chama de fora ───────────────────────────
cat > "$TMP/stubs.sh" <<'EOF'
IFS=$'\n\t'
log() { echo "[init-firewall] $*"; }
ipset() { echo "$3" >> "$ADDS"; }   # chamado como: ipset add allowed-egress <faixa>
curl() {
  local f=0 a
  for a in "$@"; do case "$a" in --*) ;; -*f*) f=1 ;; esac; done
  [ "$CURL_RC" -eq 0 ] || return "$CURL_RC"
  if [ "$f" -eq 1 ] && [ "$STATUS" -ge 400 ]; then return 22; fi
  printf '%s' "$CORPO"
}
EOF
cat "$TMP/stubs.sh" "$TMP/bloco.sh" > "$TMP/caso.sh"

# $1 nome · $2 status HTTP · $3 corpo · $4 rc do curl · $5 faixas esperadas · $6 texto esperado no log
caso() {
  local rc out faixas
  : > "$TMP/adds"
  set +e
  out=$(STATUS="$2" CORPO="$3" CURL_RC="$4" ADDS="$TMP/adds" bash -euo pipefail "$TMP/caso.sh" 2>&1)
  rc=$?
  set -e
  faixas=$(grep -c . "$TMP/adds" || true)
  if [ "$rc" -eq 0 ] && [ "$faixas" -eq "$5" ] && printf '%s' "$out" | grep -qF "$6"; then
    ok "$1 → exit 0, $faixas faixa(s), log com '$6'"
  else
    fail "$1 → exit $rc, $faixas faixa(s) (esperado 0 e $5, log com '$6')"
    printf '%s\n' "$out" | sed 's/^/      /'
  fi
}

COM_FAIXAS='{"hooks":["192.30.252.0/22","185.199.108.0/22"],"web":["140.82.112.0/20","192.30.252.0/22"],"ipv6":["2a0a:a440::/29"]}'
LIMITE='{"message":"API rate limit exceeded for 203.0.113.9.","documentation_url":"https://docs.github.com/rest"}'
ERRO_COM_FAIXA='{"message":"upstream unavailable","tried":["198.51.100.0/24"]}'

caso "1. HTTP 200 com faixas"        200 "$COM_FAIXAS" 0  3 "+ faixas do GitHub"
caso "2. HTTP 403 (limite de API)"   403 "$LIMITE"     0  0 "aviso"
caso "3. HTTP 200 sem nenhuma faixa" 200 '{}'          0  0 "aviso"
caso "4. curl sem conexão (exit 28)" 0   ''            28 0 "aviso"
# Corpo de erro não é lista de faixas, mesmo que tenha algo com cara de faixa: é o `-f`
# que impede liberar no firewall um endereço vindo de uma resposta de erro.
caso "5. HTTP 500 com faixa no corpo" 500 "$ERRO_COM_FAIXA" 0 0 "aviso"

if [ "$FAILS" -eq 0 ]; then echo "OK: 6 casos"; else echo "FALHOU: $FAILS"; exit 1; fi
