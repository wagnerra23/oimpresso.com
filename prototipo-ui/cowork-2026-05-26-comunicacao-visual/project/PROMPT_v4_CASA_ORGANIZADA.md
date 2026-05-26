# PROMPT_v4_CASA_ORGANIZADA.md
# Sync atômico FINAL · com CSS escopado + ARQUITETURA.md
# 2026-05-18

```bash
#!/usr/bin/env bash
set -euo pipefail

cd ~/oimpresso.com
git checkout main && git pull origin main
git checkout -B feat/kb-9.75-v4-casa-organizada

BACKUP_DIR="prototipo-ui-backup-$(date +%Y%m%d-%H%M%S)"
cp -r prototipo-ui "$BACKUP_DIR"
echo "✓ Backup em $BACKUP_DIR"

BASE="https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo"
TKN="?t=3826b3354a99d3c8870337604ba4cadd0244b8fcc43a1e1c12b26b69578f00f8.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779153655&direct=1"

# Apaga arquivos legacy (fase 1)
LEGACY_HTML=(
  "Auditoria Final.html" "Auditoria UI.html" "Bench KB v2.html" "Bench KB.html"
  "Bench Mecânica.html" "Boleto e Contas Inter.html" "Compras.html" "Estado da Arte.html"
  "Financeiro Unificado.html" "Identidade Visual.html" "Inventario - Migracao Blade React.html"
  "Plano por Tela.html" "Product Cadastro.html" "Product Picker Mecanica.html"
  "Product Picker.html" "Produto Unificado.html" "Produtos Cockpit.html"
  "Produção Oficina - Tela.html" "Progresso de Notas.html" "Telas Faltantes Onda 2.html"
  "Telas Faltantes.html" "Venda Simples F1.html" "Venda por Estagio FSM v1.html"
  "Venda por Estagio FSM.html" "Vendas A+.html"
)
LEGACY_JSX=(
  "chat-v1-legacy.jsx" "prod-page-v1-cv.jsx" "produto-app.jsx" "produto-data.jsx"
  "produto-icons.jsx" "vendas-aplus.jsx" "vendas-create-completo.jsx"
)
for F in "${LEGACY_HTML[@]}" "${LEGACY_JSX[@]}"; do
  rm -f "prototipo-ui/$F" && echo "✗ deletado: $F" || true
done

# Lista final · arquivos a baixar
declare -A FILES=(
  ["vendas-page.jsx"]="vendas-page.jsx"
  ["vendas-extras.jsx"]="vendas-extras.jsx"
  ["vendas-shortcuts.jsx"]="vendas-shortcuts.jsx"
  ["vendas-ai.jsx"]="vendas-ai.jsx"
  ["vendas-curation.jsx"]="vendas-curation.jsx"
  ["vendas-output.jsx"]="vendas-output.jsx"
  ["vendas-tweaks.jsx"]="vendas-tweaks.jsx"
  ["financeiro-app.jsx"]="financeiro-app.jsx"
  ["financeiro-data.jsx"]="financeiro-data.jsx"
  ["financeiro-icons.jsx"]="financeiro-icons.jsx"
  ["financeiro-telas-extras.jsx"]="financeiro-telas-extras.jsx"
  ["financeiro-curation.jsx"]="financeiro-curation.jsx"
  ["financeiro-ai.jsx"]="financeiro-ai.jsx"
  ["financeiro-output.jsx"]="financeiro-output.jsx"
  ["fsm-stepper.jsx"]="fsm-stepper.jsx"
  ["data.jsx"]="data.jsx"
  ["app.jsx"]="app.jsx"
  ["styles.css"]="styles.css"
  ["vendas.css"]="vendas.css"
  ["financeiro.css"]="financeiro.css"
  ["Oimpresso ERP - Chat.html"]="Oimpresso%20ERP%20-%20Chat.html"
  ["ARQUITETURA.md"]="ARQUITETURA.md"
)
for L in "${!FILES[@]}"; do
  R="${FILES[$L]}"
  TMP=$(mktemp)
  curl -sf -L "${BASE}/${R}${TKN}" -o "$TMP"
  SIZE=$(wc -c < "$TMP")
  [ "$SIZE" -lt 100 ] && { echo "✕ $L $SIZE bytes"; rm "$TMP"; exit 1; }
  rm -f "prototipo-ui/$L"
  mv "$TMP" "prototipo-ui/$L"
  echo "✓ $L ($SIZE bytes)"
done

# Sanity checks
cd prototipo-ui
grep -q "useFinEdits" financeiro-curation.jsx
grep -q "FinEditPanel" financeiro-curation.jsx
grep -q "fin-drawer-tabs" financeiro-app.jsx
grep -q "fsm-stepper.jsx" "Oimpresso ERP - Chat.html"
grep -q 'href="vendas.css"' "Oimpresso ERP - Chat.html"
grep -q 'href="financeiro.css"' "Oimpresso ERP - Chat.html"
[ "$(wc -l < styles.css)" -lt 7000 ] || { echo "✕ styles.css ainda muito grande"; exit 1; }
echo "✓ Todos checks passaram"
cd ..

git add prototipo-ui/
git commit -m "feat(prototipo-ui): casa organizada · CSS escopado · KB-9.75 v4

Fase 1: remove 32 arquivos legacy (25 HTMLs experimentais + 7 JSXs duplicados)
Fase 2: quebra styles.css (8000 linhas) em 3:
  - styles.css ~1500l (shell base)
  - vendas.css ~1200l (.vd-* .vendas-aplus FSM venda)
  - financeiro.css ~1500l (.fin-* FSM fin drawer abas)
Fase 3: nomes 1:1 entre Cowork e prototipo-ui/
Fase 4: ARQUITETURA.md (single source of truth)

Score Vendas 9,75 · Financeiro 9,75. Re: ADR-0114."
git push -u origin feat/kb-9.75-v4-casa-organizada
gh pr create --title "feat: casa organizada · KB-9.75 v4 · CSS escopado" --body "Reset estrutural. Ver ARQUITETURA.md." --base main
```

## Conferir após merge

Abra `prototipo-ui/Oimpresso ERP - Chat.html`:
1. Sidebar → Financeiro → click numa linha → drawer com `[Detalhes 💬N]` `[✦ IA]` no topo
2. Aba Detalhes: FSM 4-dots + `[✓ Conferido]` + `[✎ Editar campos]` inline
3. Aba ✦ IA: stats da contraparte + anomalia + botão Perguntar
4. Sidebar → Vendas → mesmo padrão de drawer + 5 tabs no drawer da venda

Se algo estiver fora: o erro está no Code aplicando o sync (não no Cowork).
Backup local em `$BACKUP_DIR` pra rollback rápido.
