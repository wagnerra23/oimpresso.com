#!/usr/bin/env bash
# scripts/deploy-wave-z2-integracao-vendas-oficina.sh
#
# Deploy script Wave Z-2 — Integração Vendas × Oficina (ADR 0192) em prod biz=1 (canary).
#
# Mergeado em main 2026-05-25 — stack completo:
#   Backend:
#     - Onda 0 (542b11ccf · ADR 0192) — JobSheetObserver pattern + schema canon
#     - Onda 1 (f11f10eeb) — Migration `transactions.source` + `os_ref` + `commission_split` + idx
#     - Onda 2 (e98649989) — Observer (CREATE on terminal) + payload endpoints
#     - W2     (2f6f10fc8) — Payload expand items_list + fiscal
#     - W4     (166791e8d) — commission_split editor + validation server-side
#     - W5     (2d86ee55a) — Reverse hook + migration `cancelled_at`
#   Frontend:
#     - Ondas 3+4 (e40289010) — Sells/Index coluna Origem + saved tree + KPI breakdown + listener
#     - Onda 5    (94300b057) — Repair drawer card "Esta OS gerou venda"
#     - W1        (4483ae855) — Sells/Caixa.tsx coexiste rota /vendas/caixa
#     - W3        (129dab230) — Botão Compartilhar Web Share API + clipboard fallback
#
# Wagner roda este script em D:/oimpresso.com/ APÓS SSH no Hostinger.
# Idempotente. Cada passo confirma antes de avançar (Y/N gates).
# Tier 0 IRREVOGÁVEL: canary biz=1 (Wagner WR2 Sistemas) APENAS; biz=4 Larissa só após 7d canary verde.
#
# Uso:
#   ssh oimpresso@hostinger
#   cd /home/oimpresso/oimpresso.com
#   bash scripts/deploy-wave-z2-integracao-vendas-oficina.sh
#
# Rollback rápido se algo der errado:
#   1. Restaurar backup MySQL (Passo 1 cria dump em ~/backups/wave-z2/)
#   2. php artisan migrate:rollback --step=2 (desfaz cancelled_at + source/os_ref)
#   3. git reset --hard <commit-pre-pull>  (revert código)
#   4. php artisan optimize:clear

set -u
set -o pipefail

# ─── helpers ──────────────────────────────────────────────────────────────────

GREEN=$'\033[0;32m'
YELLOW=$'\033[1;33m'
RED=$'\033[0;31m'
BLUE=$'\033[0;34m'
CYAN=$'\033[0;36m'
NC=$'\033[0m'

step() { echo ""; echo "${BLUE}━━━ $* ━━━${NC}"; }
info() { echo "${GREEN}✓${NC} $*"; }
warn() { echo "${YELLOW}⚠${NC}  $*"; }
err()  { echo "${RED}✗${NC} $*" >&2; }
ts()   { date '+%Y-%m-%d %H:%M:%S'; }

confirm() {
  local prompt="$1"
  echo ""
  read -r -p "${YELLOW}[$(ts)] ${prompt} [y/N]: ${NC}" answer
  case "${answer}" in
    [yY]|[yY][eE][sS]) return 0 ;;
    *) err "Abortado pelo usuário no passo: ${prompt}"
       echo ""
       echo "${CYAN}=== Procedimento de rollback ===${NC}"
       echo "Se algum passo ANTERIOR já foi aplicado:"
       echo "  - Restaurar dump SQL: mysql -u<usr> -p <db> < ~/backups/wave-z2/transactions-pre-deploy-*.sql"
       echo "  - Reverter migrations: php artisan migrate:rollback --step=2"
       echo "  - Reverter código: git reset --hard ${PRE_PULL_HEAD:-<commit-anterior>}"
       echo "  - Limpar caches: php artisan optimize:clear"
       exit 1 ;;
  esac
}

# ─── pré-flight ───────────────────────────────────────────────────────────────

step "Pré-flight Wave Z-2 Integração Vendas × Oficina deploy"

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
PRE_PULL_HEAD="${HEAD}"
info "Branch atual: ${BRANCH}"
info "HEAD atual: ${HEAD}"
info "Timestamp: $(ts)"

if [[ "${BRANCH}" != "main" ]]; then
  warn "Branch atual NÃO é main. Cancele se for engano."
  confirm "Continuar mesmo assim?"
fi

# Confirma biz=1 canary
echo ""
warn "Tier 0 IRREVOGÁVEL (ADR 0093): este deploy ativa Integração Vendas × Oficina em prod."
warn "Canary aprovado: biz=1 (Wagner WR2 Sistemas) APENAS."
warn "Larissa biz=4 ROTA LIVRE NÃO recebe agora — espera 7d canary verde."
warn ""
warn "Comportamento ativado:"
warn "  - JobSheetObserver auto-cria Transaction quando OS transiciona pra stage terminal (entregue_completo)"
warn "  - Reverse hook: OS reaberta marca Transaction.cancelled_at"
warn "  - Sells/Index ganha coluna 'Origem' + saved tree 'Por origem' + KPI breakdown"
warn "  - Repair drawer ganha card 'Esta OS gerou venda #V-NNNN' + breakdown peças/serviço + fiscal badge"
warn "  - Sells/Caixa nova rota /vendas/caixa com seção 'Por origem'"
warn "  - commission_split editor em Sells/Edit (2 selects + 2 inputs % · validation total=100)"
confirm "Confirma deploy canary biz=1?"

# ─── passo 1: backup tabelas críticas ─────────────────────────────────────────

step "Passo 1/6 — Backup MySQL tabelas críticas (transactions + repair_job_sheets)"

BACKUP_DIR="${HOME}/backups/wave-z2"
mkdir -p "${BACKUP_DIR}"
BACKUP_FILE="${BACKUP_DIR}/transactions-pre-deploy-$(date +%Y%m%d-%H%M%S).sql"

DB_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_NAME=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")
DB_PASS=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2 | tr -d '"' | tr -d "'")

if [[ -z "${DB_NAME}" ]] || [[ -z "${DB_USER}" ]]; then
  err "Variáveis DB_NAME ou DB_USER vazias no .env. Aborta."
  exit 1
fi

info "Vai dump tabelas: transactions, repair_job_sheets"
info "Destino: ${BACKUP_FILE}"

confirm "Aplicar dump?"

mysqldump -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" \
  --single-transaction --quick \
  "${DB_NAME}" transactions repair_job_sheets \
  > "${BACKUP_FILE}" 2>/dev/null

if [[ ! -s "${BACKUP_FILE}" ]]; then
  err "Dump vazio ou falhou. Verifique credenciais."
  exit 1
fi

BACKUP_SIZE=$(du -h "${BACKUP_FILE}" | cut -f1)
info "Backup ${BACKUP_SIZE} salvo em ${BACKUP_FILE}"

# ─── passo 2: pull main ───────────────────────────────────────────────────────

step "Passo 2/6 — Pull main no Hostinger"

info "Vai rodar: git fetch origin && git pull origin main"

confirm "Aplicar pull?"

git fetch origin 2>&1 | tail -5
git pull origin main 2>&1 | tail -10

NEW_HEAD=$(git rev-parse --short HEAD 2>/dev/null || echo "?")
info "HEAD pós-pull: ${NEW_HEAD}"

if [[ "${NEW_HEAD}" == "${PRE_PULL_HEAD}" ]]; then
  warn "HEAD não mudou. Provavelmente já estava atualizado."
fi

# Confirma que os commits Wave Z-2 estão presentes
EXPECTED_COMMITS=(
  "542b11ccf"  # ADR 0192 Onda 0
  "f11f10eeb"  # Onda 1 migration source + os_ref + commission_split
  "e98649989"  # Onda 2 Observer CREATE + payload endpoints
  "2f6f10fc8"  # W2 payload expand items_list + fiscal
  "166791e8d"  # W4 commission_split editor + validation
  "2d86ee55a"  # W5 reverse hook + migration cancelled_at
  "e40289010"  # Ondas 3+4 Sells/Index coluna Origem + tree + KPI
  "94300b057"  # Onda 5 Repair drawer card
  "4483ae855"  # W1 Sells/Caixa.tsx + rota /vendas/caixa
  "129dab230"  # W3 botão Compartilhar Web Share API
)
MISSING=0
for c in "${EXPECTED_COMMITS[@]}"; do
  if git cat-file -e "${c}" 2>/dev/null; then
    info "Commit ${c} presente"
  else
    err "Commit ${c} AUSENTE pós-pull."
    MISSING=$((MISSING + 1))
  fi
done
if [[ ${MISSING} -gt 0 ]]; then
  err "${MISSING} commit(s) Wave Z-2 ausentes. Abort."
  exit 1
fi

# ─── passo 3: composer install ────────────────────────────────────────────────

step "Passo 3/6 — composer install --no-dev --optimize-autoloader"

info "Vai rodar composer install (sem dev deps, autoloader otimizado)"

confirm "Aplicar composer install?"

composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -10
info "composer install aplicado"

# ─── passo 4: migrations ──────────────────────────────────────────────────────

step "Passo 4/6 — Migrations (2 aditivas idempotentes)"

echo "Vai aplicar:"
echo "  - 2026_05_25_140000_add_source_and_os_ref_to_transactions"
echo "      (ALTER transactions ADD source ENUM + os_ref VARCHAR(20) + commission_split JSON + idx composto)"
echo "  - 2026_05_25_180000_add_cancelled_at_to_transactions"
echo "      (ALTER transactions ADD cancelled_at TIMESTAMP NULL)"
echo ""
echo "Pretend run primeiro:"
php artisan migrate --pretend 2>&1 | tail -30

confirm "SQL acima parece OK? Aplicar migrations?"

php artisan migrate --force 2>&1 | tail -15
info "Migrations aplicadas"

# Verifica migrations efetivas
echo ""
info "Confirmação via migrate:status:"
php artisan migrate:status 2>&1 | grep -E "(add_source_and_os_ref|add_cancelled_at)" || warn "Migrations não apareceram no status — investigar"

# ─── passo 5: optimize:clear + optimize ───────────────────────────────────────

step "Passo 5/6 — optimize:clear + optimize (rebuild caches)"

info "Vai limpar todos os caches e rebuildar (config, route, view, event)"

confirm "Aplicar optimize?"

php artisan optimize:clear 2>&1 | tail -10
info "Caches limpos"

php artisan optimize 2>&1 | tail -5
info "Caches rebuildados"

# ─── passo 6: frontend build ──────────────────────────────────────────────────

step "Passo 6/6 — Frontend build (npm ci + npm run build)"

info "Vai rodar npm ci (clean install) + npm run build (Vite production bundle)"

confirm "Aplicar npm ci + build?"

if ! command -v npm &>/dev/null; then
  err "npm não encontrado. Instale Node.js ou use build local + scp."
  exit 1
fi

npm ci 2>&1 | tail -5
info "npm ci aplicado"

npm run build 2>&1 | tail -10
info "Vite build aplicado"

# ─── final ────────────────────────────────────────────────────────────────────

step "Wave Z-2 Integração Vendas × Oficina deploy COMPLETO ✓"

echo ""
echo "${GREEN}Próximos passos (mão Wagner — checklist smoke completo):${NC}"
echo "  1. Ver checklist completo: memory/sessions/2026-05-25-wave-z2-smoke-checklist.md"
echo "  2. Abrir Brave em https://oimpresso.com/vendas logado biz=1"
echo "  3. Validar 8 blocos A-H (schema · observer · sells/index · repair drawer · caixa · cross-link · reverse hook · commission editor)"
echo "  4. Anexar screenshot/SQL output em cada item marcável"
echo "  5. Append SYNC_LOG.md com '[W2] approved smoke wave-z2 prod biz=1'"
echo "  6. Aguardar 7 dias canary biz=1 verde ANTES de habilitar biz=4 Larissa"
echo ""
echo "${GREEN}Rollback rápido se quebrar:${NC}"
echo "  1. Restaurar DB: mysql -u${DB_USER} -p ${DB_NAME} < ${BACKUP_FILE}"
echo "  2. Reverter migrations: php artisan migrate:rollback --step=2"
echo "  3. Reverter código: git reset --hard ${PRE_PULL_HEAD}"
echo "  4. Limpar caches: php artisan optimize:clear"
echo ""
echo "${CYAN}Stack mergeado validado:${NC}"
echo "  ADR 0192 · 10 commits · 2 migrations · Observer pattern com reverse hook"
echo "  Frontend: coluna Origem + saved tree + KPI breakdown + drawer card + caixa + commission editor"
echo "  Idempotência: skip se Transaction(business_id, os_ref) já existe"
echo "  Multi-tenant Tier 0: Observer herda business_id da OS — jamais cross-tenant"
echo ""
