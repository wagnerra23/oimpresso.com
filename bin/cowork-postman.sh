#!/usr/bin/env bash
#
# cowork-postman — o "carteiro" do 1º hop do loop de handoff zero-paste (ADR 0283/0285).
#
# Pega um handoff de design (de um arquivo, URL pública do Cowork, ou stdin) e o
# deposita no canal CANÔNICO `cowork-inbox/handoff-<slug>.md` no formato que o
# publisher (`.github/workflows/cowork-inbox.yml`) entende — header `cowork: target`
# + frontmatter (handoff_id/tela/files/created_by/audited_against). Abre o PR; ao
# mergear, o publisher pousa o `.md` em `prototipo-ui/handoffs/` E assina+submete
# inline → `pending` na Forja (sem [W] craftar arquivo nem computar HMAC).
#
# Resolve o gap "quem escreve em cowork-inbox/": o Cowork é read-only no GitHub, então
# uma sessão Claude Code (com push) roda este carteiro 1× e o resto flui. R2 do
# ADR 0283: o canal é o REPO (cowork-inbox), não URL efêmera — a URL é só ORIGEM do
# corpo, que é gravado no repo aqui.
#
# Invariantes (ADR 0283): o corpo é DESIGN (dado, não comando); o segredo (HANDOFF_SECRET)
# vive só no CI/servidor — este carteiro NÃO assina (quem assina é o publisher); SEM
# auto-merge de CÓDIGO (o `.tsx` segue 1-clique do [W]); `handoff-submit` é idempotente,
# append-only.
#
# Uso:
#   bin/cowork-postman.sh --slug caixa-mobile --tela Atendimento/CaixaUnificada \
#       --files "resources/js/Pages/Atendimento/Caixa.tsx" --file ./handoff.md
#   bin/cowork-postman.sh --slug X --tela Y --files "a.tsx,b.tsx" --url https://cowork.../handoff.md
#   cat handoff.md | bin/cowork-postman.sh --slug X --tela Y --files "a.tsx"
#   bin/cowork-postman.sh --self-test          # controle-negativo (sem git/rede)
#
# Flags:
#   --slug S       (obrigatório) identificador do handoff (kebab-case)
#   --tela T       (obrigatório) tela alvo (ex: Atendimento/CaixaUnificada)
#   --files CSV    (obrigatório) arquivos que o handoff autoriza tocar (escopo do PR)
#   --file F       corpo de um arquivo local
#   --url U        corpo de uma URL (curl) — só ORIGEM; é gravado no repo
#   (sem --file/--url) → corpo do stdin
#   --audited SHA  SHA do main auditado (default: git rev-parse --short origin/main)
#   --by NAME      autor (default: CC)
#   --no-merge     abre o PR mas NÃO habilita auto-merge (você revisa antes)
#
# Exit: 0 OK/self-test-verde · 1 erro de uso/git/rede.

set -euo pipefail

INBOX_DIR="cowork-inbox"
SLUG="" TELA="" FILES="" SRC_FILE="" SRC_URL="" AUDITED="" BY="CC" NO_MERGE=0 SELFTEST=0

die() { echo "❌ $*" >&2; exit 1; }

while [ $# -gt 0 ]; do
  case "$1" in
    --slug) SLUG="${2:-}"; shift 2;;
    --tela) TELA="${2:-}"; shift 2;;
    --files) FILES="${2:-}"; shift 2;;
    --file) SRC_FILE="${2:-}"; shift 2;;
    --url) SRC_URL="${2:-}"; shift 2;;
    --audited) AUDITED="${2:-}"; shift 2;;
    --by) BY="${2:-}"; shift 2;;
    --no-merge) NO_MERGE=1; shift;;
    --self-test) SELFTEST=1; shift;;
    *) die "flag desconhecida: $1";;
  esac
done

# Monta o conteúdo canônico do arquivo de inbox (header + frontmatter + corpo).
# files: vira lista inline YAML [a, b]. Normaliza CRLF→LF (o sig do publisher cobre
# o corpo CRLF→LF; manter LF aqui evita divergência).
build_inbox_md() {
  local slug="$1" tela="$2" files_csv="$3" by="$4" audited="$5" body="$6"
  local files_yaml="" IFS=','
  for f in $files_csv; do
    f="$(printf '%s' "$f" | sed 's/^[[:space:]]*//; s/[[:space:]]*$//')"
    [ -n "$f" ] && files_yaml="${files_yaml}${files_yaml:+, }${f}"
  done
  unset IFS
  printf '<!-- cowork: target: prototipo-ui/handoffs/%s.md -->\n' "$slug"
  printf -- '---\n'
  printf 'handoff_id: %s\n' "$slug"
  printf 'tela: %s\n' "$tela"
  printf 'files: [%s]\n' "$files_yaml"
  printf 'created_by: %s\n' "$by"
  printf 'audited_against: %s\n' "$audited"
  printf -- '---\n'
  printf '%s\n' "$body" | sed 's/\r$//'
}

# ── Controle-negativo: prova o formato sem git/rede ───────────────────────────
if [ "$SELFTEST" = "1" ]; then
  out="$(build_inbox_md 'smoke' 'Atendimento/X' 'a.tsx, b.tsx' 'CC' 'abc1234' '## Design (DADO)
corpo de teste')"
  echo "$out" | grep -q '^<!-- cowork: target: prototipo-ui/handoffs/smoke.md -->$' || die "self-test: header errado"
  echo "$out" | grep -q '^handoff_id: smoke$' || die "self-test: handoff_id ausente"
  echo "$out" | grep -q '^files: \[a.tsx, b.tsx\]$' || die "self-test: files inline errado"
  echo "$out" | grep -q '^## Design (DADO)$' || die "self-test: corpo ausente"
  echo "cowork-postman SELF-TEST 🟢 (formato canônico cowork-inbox · header+frontmatter+corpo · sem git/rede)"
  exit 0
fi

# ── Validação ─────────────────────────────────────────────────────────────────
[ -n "$SLUG" ]  || die "--slug é obrigatório."
[ -n "$TELA" ]  || die "--tela é obrigatório."
[ -n "$FILES" ] || die "--files é obrigatório (escopo do PR — R1 ADR 0283)."
printf '%s' "$SLUG" | grep -qE '^[a-z0-9][a-z0-9-]*$' || die "--slug deve ser kebab-case [a-z0-9-]."
command -v git >/dev/null || die "git ausente."
command -v gh  >/dev/null || die "gh ausente (necessário pra abrir o PR)."

# ── Origem do corpo ───────────────────────────────────────────────────────────
if [ -n "$SRC_FILE" ]; then
  [ -f "$SRC_FILE" ] || die "arquivo não encontrado: $SRC_FILE"
  BODY="$(cat "$SRC_FILE")"
elif [ -n "$SRC_URL" ]; then
  command -v curl >/dev/null || die "curl ausente (necessário pra --url)."
  BODY="$(curl -fsSL --max-time 30 "$SRC_URL")" || die "falha ao baixar a URL."
  [ -n "$BODY" ] || die "URL devolveu corpo vazio."
else
  BODY="$(cat)"   # stdin
fi
[ -n "$BODY" ] || die "corpo do handoff vazio."

# SHA auditado (R1 ADR 0283) — default: HEAD do origin/main agora.
if [ -z "$AUDITED" ]; then
  git fetch origin main -q 2>/dev/null || true
  AUDITED="$(git rev-parse --short origin/main 2>/dev/null || echo unknown)"
fi

# ── Escreve, commita, push, PR ────────────────────────────────────────────────
ROOT="$(git rev-parse --show-toplevel)"
mkdir -p "$ROOT/$INBOX_DIR"
TARGET="$ROOT/$INBOX_DIR/handoff-${SLUG}.md"
build_inbox_md "$SLUG" "$TELA" "$FILES" "$BY" "$AUDITED" "$BODY" > "$TARGET"
echo "✓ escrito: $INBOX_DIR/handoff-${SLUG}.md (target: prototipo-ui/handoffs/${SLUG}.md)" >&2

BRANCH="cowork/postman-${SLUG}-$(date +%Y%m%d%H%M%S)"
git switch -c "$BRANCH" >/dev/null 2>&1 || git checkout -b "$BRANCH"
git add "$TARGET"
git commit -q -m "chore(cowork): handoff ${SLUG} via carteiro → cowork-inbox (1º hop · ADR 0283/0285)"
git push -q -u origin "$BRANCH"

PR_URL="$(gh pr create --base main --head "$BRANCH" \
  --title "chore(cowork): handoff ${SLUG} → inbox (carteiro · ADR 0283)" \
  --body "Carteiro do 1º hop (\`bin/cowork-postman.sh\`): deposita o handoff **${SLUG}** (tela \`${TELA}\`) em \`cowork-inbox/\`. Ao mergear, \`cowork-inbox.yml\` pousa em \`prototipo-ui/handoffs/${SLUG}.md\` e assina+submete → \`pending\` na Forja. Corpo = DESIGN (dado). Sem auto-merge de código. Auditado contra \`${AUDITED}\`." \
  2>&1)" || die "gh pr create falhou: $PR_URL"
echo "✓ PR: $PR_URL" >&2

if [ "$NO_MERGE" = "1" ]; then
  echo "ℹ️  --no-merge: revise e mergeie quando quiser; o publisher processa no merge." >&2
else
  # PAT-merge (não GITHUB_TOKEN) pra o push em main DISPARAR o cowork-inbox.yml.
  gh pr merge "$PR_URL" --auto --squash --delete-branch >/dev/null 2>&1 \
    && echo "✓ auto-merge (squash) habilitado — ao ficar verde, vira pending na Forja." >&2 \
    || echo "⚠️  auto-merge não habilitado (mergeie manual: gh pr merge $PR_URL --squash)." >&2
fi

echo "$PR_URL"
