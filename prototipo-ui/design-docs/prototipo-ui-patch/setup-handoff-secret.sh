#!/usr/bin/env bash
#
# setup-handoff-secret.sh — liga o HANDOFF_SECRET nos DOIS lados de uma vez.
#
# Soberania (ADR 0283): VOCÊ roda, na SUA máquina. O segredo é gerado localmente,
# vai direto pro servidor (via SSH, canal cifrado) e pro repo secret (via gh) —
# NUNCA é impresso na tela, NUNCA vai pro histórico, NUNCA passa por chat.
#
# Pré-requisitos: ssh configurado pro app server + `gh auth status` ok.
#
# Uso:
#   1. edite os 3 valores no bloco EDITE-UMA-VEZ
#   2. bash setup-handoff-secret.sh
#
set -euo pipefail

# ───────────── EDITE UMA VEZ ─────────────
SSH_HOST="usuario@seu-servidor-hostinger"          # acesso SSH ao app server (onde roda handoff:ingest)
ENV_PATH="/home/USUARIO/oimpresso.com/.env"        # caminho ABSOLUTO do .env no servidor
REPO="wagnerra23/oimpresso.com"                    # repo do GitHub
# ─────────────────────────────────────────

echo "→ Gerando HANDOFF_SECRET (não será impresso)…"
SECRET="$(openssl rand -hex 32)"

echo "→ Instalando no servidor ($SSH_HOST)…"
# O segredo viaja por stdin (não por argumento) — não aparece em 'ps' nem em log.
ssh "$SSH_HOST" "ENV='$ENV_PATH'; read -r S; \
  sed -i '/^HANDOFF_SECRET=/d' \"\$ENV\"; \
  printf 'HANDOFF_SECRET=%s\n' \"\$S\" >> \"\$ENV\"; \
  cd \"\$(dirname \"\$ENV\")\" && php artisan config:clear >/dev/null 2>&1 || true; \
  echo '  .env atualizado + config:clear'" <<<"$SECRET"

echo "→ Instalando como repo secret (gh)…"
printf '%s' "$SECRET" | gh secret set HANDOFF_SECRET --repo "$REPO"

unset SECRET
echo
echo "✅ Pronto. HANDOFF_SECRET gerado e instalado nos dois lados — mesmo valor, nunca impresso."
echo "   Próximo: cole a Parte B (PR-6) no Claude Code. A Onda 3 dele valida que a chave bate."
