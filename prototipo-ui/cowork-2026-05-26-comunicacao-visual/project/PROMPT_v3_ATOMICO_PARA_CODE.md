# PROMPT v3 FINAL — Sync atômico Vendas + Financeiro KB-9.75
# Cole TUDO de uma vez no Claude Code · 2026-05-18

```bash
#!/usr/bin/env bash
set -euo pipefail

cd ~/oimpresso.com

# ── 1. Branch + backup ───────────────────────────────────────
git checkout main && git pull origin main
git checkout -B feat/kb-9.75-v3-atomic

BACKUP_DIR="prototipo-ui-backup-$(date +%Y%m%d-%H%M%S)"
cp -r prototipo-ui "$BACKUP_DIR"
echo "✓ Backup em $BACKUP_DIR"

# ── 2. Baixa todos os arquivos finais ────────────────────────
BASE="https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo"
TKN="?t=2662d7619e4ac3525e6f2ff0c372c8e40172ba083fe57de7e9eb16f30fa10b3f.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779150355&direct=1"

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
  ["Oimpresso ERP - Chat.html"]="Oimpresso%20ERP%20-%20Chat.html"
)

for LOCAL in "${!FILES[@]}"; do
  REMOTE="${FILES[$LOCAL]}"
  TMP=$(mktemp)
  if curl -sf -L "${BASE}/${REMOTE}${TKN}" -o "$TMP"; then
    SIZE=$(wc -c < "$TMP")
    if [ "$SIZE" -lt 100 ]; then
      echo "✕ $LOCAL ficou com $SIZE bytes — URL expirou?"
      rm "$TMP"; exit 1
    fi
    rm -f "prototipo-ui/$LOCAL"
    mv "$TMP" "prototipo-ui/$LOCAL"
    echo "✓ $LOCAL ($SIZE bytes)"
  else
    echo "✕ Falhou baixar $LOCAL"
    rm -f "$TMP"; exit 1
  fi
done

# ── 3. Sanity checks ─────────────────────────────────────────
echo ""
echo "─── Sanity checks ───"

cd prototipo-ui

# (a) financeiro-curation.jsx PRECISA ter useFinEdits + FinEditPanel
grep -q "useFinEdits"   financeiro-curation.jsx || { echo "✕ useFinEdits faltando"; exit 1; }
grep -q "FinEditPanel"  financeiro-curation.jsx || { echo "✕ FinEditPanel faltando"; exit 1; }
echo "✓ useFinEdits + FinEditPanel"

# (b) financeiro-app.jsx PRECISA ter as tabs
grep -q "fin-drawer-tabs"     financeiro-app.jsx || { echo "✕ fin-drawer-tabs faltando"; exit 1; }
grep -q "fin-drawer-tab-ai"   financeiro-app.jsx || { echo "✕ tab IA faltando"; exit 1; }
echo "✓ Drawer tabs (Detalhes + ✦ IA)"

# (c) financeiro-app.jsx NÃO pode mais ter fin-subnav (foi removido)
grep -q 'className="fin-subnav"' financeiro-app.jsx && { echo "✕ fin-subnav residual"; exit 1; }
grep -q '<FinAgeing'             financeiro-app.jsx && { echo "✕ FinAgeing residual"; exit 1; }
echo "✓ Sem resíduos antigos (fin-subnav + FinAgeing)"

# (d) Chat.html PRECISA carregar fsm-stepper.jsx
grep -q "fsm-stepper.jsx" "Oimpresso ERP - Chat.html" || { echo "✕ fsm-stepper.jsx não está no Chat.html"; exit 1; }
echo "✓ fsm-stepper.jsx registrado"

# (e) styles.css PRECISA ter > 30 menções das classes do refino
COUNT=$(grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-frescor\|fin-audit-row\|fin-edit-panel\|fsm-stepper" styles.css || true)
if [ "$COUNT" -lt 30 ]; then echo "✕ styles.css só $COUNT menções (esperado >30)"; exit 1; fi
echo "✓ styles.css com $COUNT menções"

cd ..

# ── 4. Commit + PR ───────────────────────────────────────────
echo ""
echo "═══ TODOS OS CHECKS PASSARAM ═══"
git add prototipo-ui/
git diff --cached --stat
echo ""

git commit -m "fix(prototipo-ui): KB-9.75 v3 atômico — drawer com abas + editar inline + FSM

ATÔMICO: apaga e re-baixa cada arquivo (sem merge incremental).

Resolve resíduos do PR anterior:
- Drawer ganha abas [Detalhes][✦ IA]
- useFinEdits + FinEditPanel adicionados (estavam faltando)
- fsm-stepper.jsx registrado no Chat.html
- Remove fin-subnav e FinAgeing legacy
- styles.css >30 classes do refino confirmadas

Backup local: $BACKUP_DIR
Re: ADR-0114"
git push -u origin feat/kb-9.75-v3-atomic

gh pr create \
  --title "fix(kb-9.75): v3 atômico — drawer com abas + edit inline + FSM" \
  --body "Reaplicação atômica. Score Vendas + Financeiro = 9,75 ambos.

Sanity checks passaram:
- useFinEdits + FinEditPanel ✓
- fin-drawer-tabs + tab IA ✓
- Sem resíduos antigos ✓
- fsm-stepper.jsx no Chat.html ✓
- styles.css >30 classes ✓

Re: ADR-0114" \
  --base main
```

## Conferir que ficou igual

Depois do merge, abra `prototipo-ui/Oimpresso ERP - Chat.html` local e:

1. Sidebar → Financeiro
2. Click numa linha qualquer (ex: R-2641)
3. Drawer abre com **2 abas no topo**: `[Detalhes 💬N]` `[✦ IA]`
4. Aba Detalhes:
   - Valor + Atrasado pill + Frescor pill
   - **FSM stepper 4-dots**: Emitido → Conferido → Conciliado → Liquidado
   - Botões lado a lado: `[✓ Conferido]` `[✎ Editar campos]`
   - Click em Editar → painel amber expande com Valor / Vencimento / Categoria / Forma pgto / Cliente
   - Botão `[✓ Salvar]` no canto superior do painel
5. Aba ✦ IA:
   - Banner roxo "IA copiloto"
   - Stats da contraparte (4 cards)
   - Botão "✦ Perguntar"

Se tudo aparecer, está 100% igual ao Cowork.
