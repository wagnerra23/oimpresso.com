#!/usr/bin/env bash
#
# get-secret.sh — leitor canônico de segredos do Vaultwarden (oimpresso) via `bw` CLI
# ---------------------------------------------------------------------------------
# Opção B do Tier 0 gap "Token Hostinger API inacessível ao agente autônomo"
# (memory/proibicoes.md §"Tier 0 gaps catalogados", 2026-05-28). ADR 0045 (DNS API
# canônica) + INFRA-ACESSO-CANON.md + _INDEX-SECRETS.md.
#
# O agente autônomo (Claude) roda isto no CT 100 pra ler QUALQUER segredo sem
# escalar pro Wagner e sem manusear valor em claro no chat:
#
#   tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh hostinger-api-token'
#
# ---------------------------------------------------------------------------------
# FRONTEIRA DE RESPONSABILIDADE
#   Claude (este script + skill + docs)  ->  LÊ segredos via service account.
#   Wagner (setup único, 1x)             ->  cria o user "claude-agent" no Vaultwarden,
#                                            gera a API key, e cola as 3 credenciais em
#                                            /root/.vaultwarden-agent-creds (chmod 600).
#
# O arquivo de credenciais (NUNCA versionado, NUNCA impresso) deve conter:
#   BW_CLIENTID=user.xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
#   BW_CLIENTSECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
#   BW_PASSWORD=<master password do user claude-agent>
# Opcional:
#   BW_SERVER=https://vault.oimpresso.com   (default abaixo)
# ---------------------------------------------------------------------------------
#
# Uso:
#   get-secret.sh <slug>                 -> imprime o campo "password" do item (default)
#   get-secret.sh <slug> --notes         -> imprime as notas (secure note body)
#   get-secret.sh <slug> --field <nome>  -> imprime um custom field pelo nome
#   get-secret.sh <slug> --item          -> imprime o item inteiro (JSON cru)
#   get-secret.sh --status               -> diagnóstico (server/auth/lock) sem ler segredo
#   get-secret.sh --logout               -> encerra sessão + apaga cache de sessão
#
# Códigos de saída:
#   0 sucesso · 2 uso incorreto · 3 NÃO CONFIGURADO (falta setup do Wagner) ·
#   4 auth/unlock falhou · 5 item não encontrado · 6 dependência ausente
#
set -euo pipefail

CREDS_FILE="${VAULTWARDEN_AGENT_CREDS:-/root/.vaultwarden-agent-creds}"
SESSION_CACHE="${BW_SESSION_CACHE:-/root/.bw-session}"
DEFAULT_SERVER="https://vault.oimpresso.com"

die()  { echo "get-secret: $*" >&2; exit "${2:-1}"; }
log()  { echo "get-secret: $*" >&2; }   # diagnóstico vai pro stderr; segredo só pro stdout

command -v bw >/dev/null 2>&1 || die "'bw' (Bitwarden CLI) não instalado. npm install -g @bitwarden/cli" 6

# ---- parse de argumentos -------------------------------------------------------
SLUG=""; MODE="password"; FIELD=""; ACTION="get"
while [ $# -gt 0 ]; do
  case "$1" in
    --status) ACTION="status" ;;
    --logout) ACTION="logout" ;;
    --notes)  MODE="notes" ;;
    --item)   MODE="item" ;;
    --field)  MODE="field"; FIELD="${2:-}"; [ -n "$FIELD" ] || die "--field exige um nome" 2; shift ;;
    -h|--help) sed -n '2,40p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*) die "flag desconhecida: $1" 2 ;;
    *)  [ -z "$SLUG" ] && SLUG="$1" || die "argumento extra: $1" 2 ;;
  esac
  shift
done

# ---- carrega credenciais do Wagner (setup único) -------------------------------
if [ ! -f "$CREDS_FILE" ]; then
  cat >&2 <<EOF
get-secret: NÃO CONFIGURADO — falta o setup único do Wagner.

  Este é o passo SÓ-WAGNER (o agente não pode fazer):
   1. Vaultwarden admin (https://vault.oimpresso.com/admin) -> criar user "claude-agent"
      (SIGNUPS_ALLOWED=false, então tem que ser via invite/admin).
   2. Logar como claude-agent -> Settings -> Security -> Keys -> "View API Key".
   3. Colar as 3 linhas em $CREDS_FILE no CT 100 (chmod 600), sem aspas:
        BW_CLIENTID=user.xxxx
        BW_CLIENTSECRET=xxxx
        BW_PASSWORD=<master password do claude-agent>
   4. Compartilhar (share) os itens de segredo com o user/coleção do claude-agent.

  Depois disso o agente lê sozinho: get-secret.sh <slug>
EOF
  exit 3
fi

# permissões: o arquivo tem que ser root-only
perms="$(stat -c '%a' "$CREDS_FILE" 2>/dev/null || echo '')"
case "$perms" in 600|400) : ;; *) log "AVISO: $CREDS_FILE deveria ser chmod 600 (está $perms)";; esac

# shellcheck disable=SC1090
set -a; . "$CREDS_FILE"; set +a
: "${BW_CLIENTID:?falta BW_CLIENTID em $CREDS_FILE}"
: "${BW_CLIENTSECRET:?falta BW_CLIENTSECRET em $CREDS_FILE}"
: "${BW_PASSWORD:?falta BW_PASSWORD em $CREDS_FILE}"
SERVER="${BW_SERVER:-$DEFAULT_SERVER}"

# ---- garante server + login (apikey) -------------------------------------------
current_server="$(bw config server 2>/dev/null || true)"
if [ "$current_server" != "$SERVER" ]; then
  bw config server "$SERVER" >/dev/null 2>&1 || die "não consegui setar server $SERVER" 4
fi

if ! bw login --check >/dev/null 2>&1; then
  log "login via API key (service account claude-agent)..."
  # bw login --apikey consome BW_CLIENTID/BW_CLIENTSECRET do ambiente
  bw login --apikey >/dev/null 2>&1 || die "bw login --apikey falhou (client_id/secret inválidos?)" 4
fi

# ---- obtém/reaproveita sessão (unlock) -----------------------------------------
get_session() {
  # tenta cache primeiro
  if [ -f "$SESSION_CACHE" ]; then
    local cached; cached="$(cat "$SESSION_CACHE" 2>/dev/null || true)"
    if [ -n "$cached" ] && BW_SESSION="$cached" bw unlock --check >/dev/null 2>&1; then
      printf '%s' "$cached"; return 0
    fi
  fi
  local sess
  sess="$(bw unlock --passwordenv BW_PASSWORD --raw 2>/dev/null || true)"
  [ -n "$sess" ] || return 1
  ( umask 077; printf '%s' "$sess" > "$SESSION_CACHE" )
  printf '%s' "$sess"
}

if [ "$ACTION" = "logout" ]; then
  rm -f "$SESSION_CACHE"
  bw logout >/dev/null 2>&1 || true
  log "sessão encerrada + cache removido"
  exit 0
fi

SESSION="$(get_session)" || die "unlock falhou (master password errada em $CREDS_FILE?)" 4
export BW_SESSION="$SESSION"

if [ "$ACTION" = "status" ]; then
  bw status 2>/dev/null | jq -r '. + {creds_file: "'"$CREDS_FILE"'"}' 2>/dev/null || bw status
  log "OK — server=$SERVER, autenticado + destrancado"
  exit 0
fi

[ -n "$SLUG" ] || die "faltou o <slug> do item. Ex: get-secret.sh hostinger-api-token" 2

# sync leve garante que itens recém-compartilhados apareçam (best-effort)
bw sync >/dev/null 2>&1 || true

# ---- lê o segredo pedido -------------------------------------------------------
case "$MODE" in
  password) out="$(bw get password "$SLUG" 2>/dev/null || true)" ;;
  notes)    out="$(bw get notes "$SLUG" 2>/dev/null || true)" ;;
  item)     out="$(bw get item "$SLUG" 2>/dev/null || true)" ;;
  field)
    item_json="$(bw get item "$SLUG" 2>/dev/null || true)"
    [ -n "$item_json" ] || die "item '$SLUG' não encontrado (compartilhado com o claude-agent?)" 5
    out="$(printf '%s' "$item_json" | jq -r --arg f "$FIELD" '.fields[]? | select(.name==$f) | .value' )"
    ;;
esac

[ -n "${out:-}" ] || die "campo vazio ou item '$SLUG' não encontrado / não compartilhado com claude-agent" 5
printf '%s\n' "$out"
