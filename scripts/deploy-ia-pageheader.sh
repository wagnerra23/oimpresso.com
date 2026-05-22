#!/usr/bin/env bash
# scripts/deploy-ia-pageheader.sh
#
# Deploy do PR #1385 (IA pageheader canon Jana — ADR 0182 + GUIA-SIDEBAR-V3).
# Idempotente. Wagner roda APÓS SSH no Hostinger.
#
# Uso:
#   ssh oimpresso@hostinger
#   cd /home/oimpresso/oimpresso.com
#   bash scripts/deploy-ia-pageheader.sh
#
# Tier 0 IRREVOGÁVEL: Multi-tenant — script roda no servidor que serve TODOS
# os tenants simultaneamente. Sem `--biz N` (não há canary aqui — mudança é
# visual no header das telas Jana, baixo risco).
#
# Rollback rápido:
#   git revert a7b6ca5bd  # commit do PR #1385
#   git push origin main
#   bash scripts/deploy-ia-pageheader.sh  # re-deploy versão anterior

set -e

REPO_DIR="${REPO_DIR:-/home/oimpresso/oimpresso.com}"
EXPECTED_COMMIT="a7b6ca5bd"  # PR #1385 merge commit
EXPECTED_FILES=(
    "resources/js/Pages/Jana/_shared/JanaSubNav.tsx"
    "resources/js/Pages/Jana/_shared/JanaPrimaryButton.tsx"
)

cd "$REPO_DIR" || { echo "ERRO: REPO_DIR não encontrado: $REPO_DIR"; exit 1; }

echo "=================================================="
echo "Deploy IA PageHeader canon — PR #1385"
echo "=================================================="
echo ""

# 1. Verifica estado git limpo
echo "[1/5] Verificando estado git..."
if [[ -n "$(git status --porcelain)" ]]; then
    echo "⚠️  Working tree dirty. Rode 'git stash' ou commita antes."
    exit 1
fi
echo "✅ Working tree limpo"
echo ""

# 2. Git pull
echo "[2/5] git pull origin main..."
git pull origin main
echo ""

# 3. Confirma que o commit esperado está presente
echo "[3/5] Verificando commit a7b6ca5bd no histórico..."
if git log --oneline -50 | grep -q "$EXPECTED_COMMIT"; then
    echo "✅ Commit $EXPECTED_COMMIT presente"
else
    echo "⚠️  Commit $EXPECTED_COMMIT NÃO encontrado nos últimos 50. Continuando mesmo assim..."
fi

# Verifica arquivos novos
for f in "${EXPECTED_FILES[@]}"; do
    if [[ -f "$f" ]]; then
        echo "✅ $f"
    else
        echo "❌ $f não encontrado — git pull falhou?"
        exit 1
    fi
done
echo ""

# 4. NPM install (caso package.json tenha mudado) + build
echo "[4/5] npm run build (Vite assets)..."
echo "Isto pode demorar 1-3min..."
npm run build 2>&1 | tail -20

if [[ ${PIPESTATUS[0]} -ne 0 ]]; then
    echo ""
    echo "❌ npm run build FALHOU"
    echo "   Investigue stderr acima. Pode ser disco cheio, node_modules corrompido, ou"
    echo "   erro de TypeScript na branch. Re-rode 'npm install' se necessário."
    exit 1
fi
echo "✅ Vite build OK"
echo ""

# 5. Clear caches Laravel
echo "[5/5] Limpando caches Laravel..."
php artisan view:clear
php artisan cache:clear
php artisan config:clear
echo "✅ Caches limpos"
echo ""

echo "=================================================="
echo "Deploy COMPLETO"
echo "=================================================="
echo ""
echo "Próximos passos pra validar:"
echo "  1. Abra https://oimpresso.com/jana em browser"
echo "  2. Header sticky deve mostrar:"
echo "     [JANA] [Copiloto*] Brief Memórias KB Regras ⋯ Mais 3  [+ Conversar]"
echo "  3. Ghost ativo 'Copiloto' tem border-bottom azul hue 220"
echo "  4. Click em '+ Conversar' = botão azul (não magenta)"
echo ""
echo "Rotas Brief/KB/Regras ainda dão 404 (pendente Onda 2 do GUIA-SIDEBAR-V3)."
echo "Isso é esperado — só 'Copiloto/Dashboard/Memórias/Metas/Custos' funcionam hoje."
echo ""
