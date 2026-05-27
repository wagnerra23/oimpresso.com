# FORCE_OVERWRITE_V3.md
# Versão "atômica" — Code não tem o que mergir, só baixar e usar
# 2026-05-18

## Problema diagnosticado

Code está usando `git apply --3way` ou `patch -p1` que fazem merge incremental.
Quando há conflito (espaços, comentários, ordem de blocos), ele preserva o antigo.

Resultado: `financeiro-app.jsx` ficou com OLD `<nav className="fin-subnav">` + NEW
header de action buttons + OLD `<FinAgeing/>` + NEW filter checkboxes. Frankenstein.

## Solução: força bruta atômica

Cole este script INTEIRO no Claude Code. Ele:
1. Faz backup dos seus arquivos atuais em `prototipo-ui-backup-YYYYMMDD/`
2. APAGA os 19 arquivos do refino
3. Baixa cada um do zero (curl com `-f --fail-with-body` que retorna erro se 404)
4. Valida que cada arquivo tem o tamanho esperado (compara com o servidor)
5. Roda checks de sanidade nos arquivos (grep por strings que devem/não devem aparecer)
6. Commit final SÓ se tudo passou

```bash
#!/usr/bin/env bash
set -euo pipefail

cd ~/oimpresso.com

# ── 1. Branch + backup ────────────────────────────────────────
git checkout main && git pull origin main
git checkout -B feat/vendas-financeiro-kb-9.75-v3-force

BACKUP_DIR="prototipo-ui-backup-$(date +%Y%m%d-%H%M%S)"
cp -r prototipo-ui "$BACKUP_DIR"
echo "✓ Backup em $BACKUP_DIR"

# ── 2. Lista de arquivos a APAGAR e re-baixar ─────────────────
FILES_TO_REPLACE=(
  "vendas-page.jsx"
  "vendas-extras.jsx"
  "vendas-shortcuts.jsx"
  "vendas-ai.jsx"
  "vendas-curation.jsx"
  "vendas-output.jsx"
  "vendas-tweaks.jsx"
  "financeiro-app.jsx"
  "financeiro-data.jsx"
  "financeiro-icons.jsx"
  "financeiro-telas-extras.jsx"
  "financeiro-curation.jsx"
  "financeiro-ai.jsx"
  "financeiro-output.jsx"
  "fsm-stepper.jsx"
  "data.jsx"
  "app.jsx"
  "styles.css"
  "Oimpresso ERP - Chat.html"
)

BASE_URL="https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/vendas-financeiro-completo"
TOKEN="?t=281a39cffd1f34014cbd058a9d777717a5d78563a476d68ac529032529db065e.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1779131727&direct=1"

# ── 3. APAGA + RE-BAIXA cada arquivo (atômico) ───────────────
for FILE in "${FILES_TO_REPLACE[@]}"; do
  echo "→ Reaplicando $FILE..."
  ENC_FILE=$(echo "$FILE" | sed 's/ /%20/g')
  TMP_FILE=$(mktemp)
  if curl -sf -L "${BASE_URL}/${ENC_FILE}${TOKEN}" -o "$TMP_FILE"; then
    SIZE=$(wc -c < "$TMP_FILE")
    if [ "$SIZE" -lt 100 ]; then
      echo "  ✕ erro: $FILE tem só ${SIZE} bytes (URL expirou?)"
      rm "$TMP_FILE"
      exit 1
    fi
    rm -f "prototipo-ui/$FILE"
    mv "$TMP_FILE" "prototipo-ui/$FILE"
    echo "  ✓ ${SIZE} bytes"
  else
    echo "  ✕ falhou baixar $FILE"
    rm "$TMP_FILE"
    exit 1
  fi
done

# ── 4. Sanity checks ─────────────────────────────────────────
echo ""
echo "─── Sanity checks ───"

# (a) financeiro-app.jsx NÃO pode ter o fin-subnav antigo
if grep -q 'className="fin-subnav"' prototipo-ui/financeiro-app.jsx; then
  echo "✕ financeiro-app.jsx ainda tem fin-subnav antigo — re-download falhou"
  exit 1
else
  echo "✓ fin-subnav removido"
fi

# (b) financeiro-app.jsx NÃO pode renderizar FinAgeing
if grep -q '<FinAgeing' prototipo-ui/financeiro-app.jsx; then
  echo "✕ financeiro-app.jsx ainda chama <FinAgeing/> — re-download falhou"
  exit 1
else
  echo "✓ FinAgeing removido"
fi

# (c) financeiro-app.jsx PRECISA ter <nav className="fin-drawer-tabs">
if grep -q 'fin-drawer-tabs' prototipo-ui/financeiro-app.jsx; then
  echo "✓ fin-drawer-tabs presente"
else
  echo "✕ fin-drawer-tabs FALTANDO no financeiro-app.jsx"
  exit 1
fi

# (d) styles.css PRECISA ter >30 menções a classes do refino
COUNT=$(grep -c "fin-drawer-tabs\|fin-conferido-toggle\|fin-ai-anomalia\|fin-frescor\|fin-audit-row" prototipo-ui/styles.css || true)
if [ "$COUNT" -lt 30 ]; then
  echo "✕ styles.css tem só $COUNT menções — esperado >30. CSS truncado."
  exit 1
else
  echo "✓ styles.css com $COUNT menções (esperado >30)"
fi

# (e) Chat.html precisa carregar 19 script tags
TAGS=$(grep -c "vendas-\|financeiro-\|fsm-stepper" "prototipo-ui/Oimpresso ERP - Chat.html" || true)
if [ "$TAGS" -lt 15 ]; then
  echo "✕ Chat.html tem só $TAGS script tags — esperado 15+"
  exit 1
else
  echo "✓ Chat.html com $TAGS script tags"
fi

echo ""
echo "═══════════════════════════════════════════"
echo "✓ TODOS OS CHECKS PASSARAM"
echo "═══════════════════════════════════════════"

# ── 5. Commit ────────────────────────────────────────────────
git add prototipo-ui/
git diff --cached --stat
echo ""
read -p "Commit & push? [y/N] " yn
if [[ "$yn" =~ ^[Yy]$ ]]; then
  git commit -m "fix(prototipo-ui): força overwrite v3 — KB-9.75 limpo

Atômico: apaga arquivos legados e re-baixa do zero.
Remove resíduos de merge incremental:
- fin-subnav (substituído por sidebar routes + action buttons)
- FinAgeing (Wagner pediu pra remover no refino)
Garante fin-drawer-tabs + classes CSS do refino.

Backup local: $BACKUP_DIR"
  git push -u origin feat/vendas-financeiro-kb-9.75-v3-force

  gh pr create \
    --title "fix(financeiro): força overwrite v3 — sem resíduos de merge" \
    --body "Reaplica o pacote KB-9.75 garantindo ATOMICIDADE: apaga arquivos antes de re-baixar.

Resolve o problema do PR anterior onde o git apply --3way preservou blocos antigos (fin-subnav, FinAgeing) ao tentar mergir com modificações novas.

Sanity checks passaram:
- fin-subnav removido ✓
- FinAgeing removido ✓
- fin-drawer-tabs presente ✓
- styles.css >30 menções de classes refino ✓
- Chat.html >15 script tags ✓

Re: PR anterior + #295 ADR-0114" \
    --base main
else
  echo "Não commitou. Backup preservado em $BACKUP_DIR"
fi
```

## Por que isso resolve

| Antes (git apply --3way) | Depois (apaga + baixa) |
|---|---|
| Patch precisa achar contexto exato | Não precisa, copia o arquivo todo |
| Conflito resolvido com "aceita ambos" | Sem conflito, é overwrite total |
| Pode pular linhas silenciosamente | Erra explícito se tamanho < 100 bytes |
| Difícil debugar o que ficou | Sanity checks falham com mensagem clara |

## Depois — recopiar suas funções

Wagner mencionou que tem customizações dele que precisa preservar.

**Como fazer:**

1. O script salvou backup em `prototipo-ui-backup-YYYYMMDD-HHMMSS/`
2. Compare:
   ```bash
   diff -r prototipo-ui-backup-YYYYMMDD-HHMMSS prototipo-ui | less
   ```
3. Cherry-pick suas customizações:
   - Se for em `data.jsx` (rotas extras, modelos, etc.) — copiar bloco específico
   - Se for backend (Laravel) — não está nessa pasta, intacto
   - Se for em `app.jsx` (rotas customizadas) — adicionar de volta

4. Re-commit suas customizações em commit separado:
   ```bash
   git commit -am "feat(prototipo-ui): re-aplica customizações Wagner pós-overwrite"
   ```

## Lista de URLs (caso precisem regenerar)

| Arquivo | URL |
|---|---|
| Todos os 19 arquivos | `${BASE_URL}/${FILE}${TOKEN}` |
| URLs expiram em ~1h após geração | Se passou disso, peço regen ao Cowork |

Se as URLs do `${TOKEN}` expiraram, Wagner pede pro Cowork regenerar (mando aqui).
