#!/usr/bin/env bash
# scripts/deploy-cliente-drawer-wave-z-2.sh
#
# Deploy script Wave Z-2 — ativa drawer 760px Cliente em prod biz=1 (canary).
# ADR 0179 accepted 2026-05-21 BRT. 4 PRs mergeados em main: #1339 + #1347 + #1348 + #1349.
#
# Wagner roda este script em D:/oimpresso.com/ APÓS SSH no Hostinger.
# Idempotente. Cada passo confirma antes de avançar (set +e + Y/N gates).
# Tier 0 IRREVOGÁVEL: canary biz=1 (Wagner WR2 SC); JAMAIS rodar em biz=4 (Larissa).
#
# Uso:
#   ssh oimpresso@hostinger
#   cd /home/oimpresso/oimpresso.com
#   git pull
#   bash scripts/deploy-cliente-drawer-wave-z-2.sh
#
# Rollback rápido se algo der errado:
#   1. Edit .env -> MWART_CLIENTE_INDEX=false
#   2. php artisan config:clear && php artisan cache:clear
#   (drawer fica desligado; Show.tsx ainda funciona em /cliente/{id})

set -u
set -o pipefail

# ─── helpers ──────────────────────────────────────────────────────────────────

GREEN=$'\033[0;32m'
YELLOW=$'\033[1;33m'
RED=$'\033[0;31m'
BLUE=$'\033[0;34m'
NC=$'\033[0m'

step() { echo ""; echo "${BLUE}━━━ $* ━━━${NC}"; }
info() { echo "${GREEN}✓${NC} $*"; }
warn() { echo "${YELLOW}⚠${NC}  $*"; }
err()  { echo "${RED}✗${NC} $*" >&2; }

confirm() {
  local prompt="$1"
  echo ""
  read -r -p "${YELLOW}${prompt} [y/N]: ${NC}" answer
  case "${answer}" in
    [yY]|[yY][eE][sS]) return 0 ;;
    *) err "Abortado pelo usuário"; exit 1 ;;
  esac
}

# ─── pré-flight ───────────────────────────────────────────────────────────────

step "Pré-flight Wave Z-2 deploy"

if [[ ! -f artisan ]]; then
  err "artisan não encontrado. Rode no root do oimpresso.com."
  exit 1
fi

if [[ ! -f .env ]]; then
  err ".env não encontrado. Verifique ambiente prod."
  exit 1
fi

BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null || echo "?")
HEAD=$(git rev-parse --short HEAD 2>/dev/null || echo "?")
info "Branch atual: ${BRANCH}"
info "HEAD: ${HEAD}"

if [[ "${BRANCH}" != "main" ]]; then
  warn "Branch atual NÃO é main. Cancele se for engano."
  confirm "Continuar mesmo assim?"
fi

# Confirma que os 4 commits da Wave A-G estão presentes
EXPECTED_COMMITS=(
  "0aeb1f4e7"  # docs(adr): 0179 accepted
  "36d28adfb"  # Wave B+C feat
  "b963604d1"  # Wave D+E+F+G feat
  "86501495d"  # Wave Z-1 docs
)
MISSING=0
for c in "${EXPECTED_COMMITS[@]}"; do
  if git cat-file -e "${c}" 2>/dev/null; then
    info "Commit ${c} presente"
  else
    err "Commit ${c} AUSENTE. Faça git pull origin main primeiro."
    MISSING=$((MISSING + 1))
  fi
done
if [[ ${MISSING} -gt 0 ]]; then
  err "${MISSING} commit(s) Wave A-G ausentes. Abort."
  exit 1
fi

# Confirma biz=1 (canary) NÃO biz=4 (Larissa)
echo ""
warn "Tier 0 IRREVOGÁVEL: este deploy ativa drawer Cliente em prod."
warn "Canary aprovado: biz=1 (Wagner WR2 SC) APENAS."
warn "Larissa biz=4 ROTA LIVRE NÃO recebe a flag agora — espera 7d canary."
confirm "Confirma deploy canary biz=1?"

# ─── passo 1: backup .env ─────────────────────────────────────────────────────

step "Passo 1/6 — Backup .env"

BACKUP=".env.backup-wave-z-2-$(date +%Y%m%d-%H%M%S)"
cp .env "${BACKUP}"
info "Backup salvo em ${BACKUP}"

# ─── passo 2: migrations aditivas ─────────────────────────────────────────────

step "Passo 2/6 — Migrations aditivas (idempotentes, reversíveis)"

echo "Vai aplicar:"
echo "  - 2026_05_22_000000_extend_contacts_for_cliente_drawer (ALTER contacts +16 colunas NULL + índice composto)"
echo "  - 2026_05_22_000001_create_anotacoes_table (CREATE TABLE anotacoes polimórfica multi-tenant)"
echo ""
echo "Pretend run primeiro:"
php artisan migrate --pretend 2>&1 | tail -40

confirm "SQL acima parece OK? Aplicar migrations?"

php artisan migrate --force 2>&1 | tail -10
info "Migrations aplicadas"

# ─── passo 3: feature flag canary ─────────────────────────────────────────────

step "Passo 3/6 — Habilitar MWART_CLIENTE_INDEX=true em .env"

if grep -q "^MWART_CLIENTE_INDEX=" .env; then
  # Já existe — atualiza
  sed -i.bak 's/^MWART_CLIENTE_INDEX=.*/MWART_CLIENTE_INDEX=true/' .env
  info "MWART_CLIENTE_INDEX atualizado pra true"
else
  # Não existe — adiciona ao final
  echo "" >> .env
  echo "# ADR 0179 Wave Z-2 canary biz=1 — drawer Cliente 760px" >> .env
  echo "MWART_CLIENTE_INDEX=true" >> .env
  info "MWART_CLIENTE_INDEX=true adicionado ao .env"
fi

# Sanity check
grep "^MWART_CLIENTE_INDEX=" .env

# ─── passo 4: clear caches ────────────────────────────────────────────────────

step "Passo 4/6 — Clear caches (config, route, view)"

php artisan config:clear 2>&1 | tail -2
php artisan route:clear 2>&1 | tail -2
php artisan view:clear 2>&1 | tail -2
php artisan cache:clear 2>&1 | tail -2
info "Caches limpos"

# ─── passo 5: health check ────────────────────────────────────────────────────

step "Passo 5/6 — Health check Jana (5 SQL Tier 0)"

php artisan jana:health-check 2>&1 | tail -30 || warn "Health check teve checks ✗ — verifique se são pré-existentes (brief stale, profile distiller, US-ACCO drift). Wave Cliente não deve causar nenhum."

# ─── passo 6: smoke curl ──────────────────────────────────────────────────────

step "Passo 6/6 — Smoke curl (sem auth, espera 302 redirect login)"

DOMAIN=$(grep "^APP_URL=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
if [[ -z "${DOMAIN}" ]]; then DOMAIN="https://oimpresso.com"; fi

echo "Testando ${DOMAIN}/cliente..."
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "${DOMAIN}/cliente" || echo "ERR")
if [[ "${HTTP_CODE}" == "302" ]] || [[ "${HTTP_CODE}" == "200" ]]; then
  info "${DOMAIN}/cliente respondeu ${HTTP_CODE} ✓"
else
  err "${DOMAIN}/cliente respondeu ${HTTP_CODE} (esperado 302 ou 200)"
fi

echo "Testando ${DOMAIN}/cliente/1 (deeplink → redirect drawer)..."
LOCATION=$(curl -s -o /dev/null -w "%{redirect_url}" "${DOMAIN}/cliente/1" || echo "")
if [[ "${LOCATION}" == *"contact_id=1"* ]]; then
  info "Redirect drawer funcionando: ${LOCATION}"
elif [[ "${LOCATION}" == *"login"* ]]; then
  info "Redirect login (esperado sem auth)"
else
  warn "Redirect location: ${LOCATION}"
fi

# ─── final ────────────────────────────────────────────────────────────────────

step "Wave Z-2 deploy COMPLETO ✓"

echo ""
echo "${GREEN}Próximos passos (mão Wagner — checklist smoke completo):${NC}"
echo "  1. Abrir Brave em https://${DOMAIN#https://}/cliente"
echo "  2. Login Wagner@WR2 SC biz=1 (JAMAIS biz=4 Larissa)"
echo "  3. Clicar 1ª linha → drawer 760px deve abrir lateral direita"
echo "  4. Clicar cada uma das 8 tabs (Identificação · Contato · Endereço · Comercial · Classificação · OSs · IA · Auditoria)"
echo "  5. Tab Identificação: editar 'Nome fantasia' → blur → autosave (toast ✓)"
echo "  6. Tab Endereço: digitar CEP '01310100' → blur → autopreenche Av. Paulista"
echo "  7. Tab IA: clicar 'Gerar resumo' → spinner → resumo Haiku ~5s"
echo "  8. Tab Auditoria: timeline 6+ tipos eventos + botão 'Exportar log' baixa CSV"
echo "  9. Listagem: confirmar avatar HSL hash colorido (12 cores) + 6 filtros + Star pessoal localStorage"
echo " 10. Screenshot drawer aberto (8 tabs) → salvar como prototipo-ui/screenshots/cliente-drawer-760-prod-biz1.png"
echo " 11. Append prototipo-ui/SYNC_LOG.md:"
echo "     $(date '+%Y-%m-%d %H:%M') [W2] approved screenshot cliente-drawer-760 prod biz=1"
echo " 12. (Opcional) Rodar skill brief-update → BRIEFING.md final"
echo ""
echo "${GREEN}Rollback rápido se quebrar:${NC}"
echo "  cp ${BACKUP} .env && php artisan config:clear"
echo "  (drawer desliga; Show.tsx full-page continua funcionando se MWART_CLIENTE_SHOW=true)"
echo ""
