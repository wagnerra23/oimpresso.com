#!/usr/bin/env bash
# scripts/gh/safe-merge.sh — merge de PR à prova de desync do GitHub (headRefOid stale).
#
# POR QUE EXISTE
#   2026-07-03: o handoff da régua OficinaAuto (commit a1782b7d7c) foi pushado mas o
#   squash-merge do #3763 usou um headRefOid STALE (só o 1º commit) → o commit do handoff
#   NUNCA landou em main. Mesmo padrão do #3732 (onda Cliente). É silencioso: o merge diz
#   "success" e ENGOLE commits. Detecção pós-merge é cega quando a causa é o próprio GitHub
#   estar com estado velho (a API de files responde o mesmo head velho). O ÚNICO ponto
#   confiável é ANTES do merge: pinar o SHA. A API PUT /pulls/{n}/merge aceita `sha=` e
#   RETORNA 409 se o head tiver mexido — garantia do servidor, não esperança.
#   Ref: memory/reference/feedback-merge-desync-headrefoid.md
#
# USO
#   scripts/gh/safe-merge.sh <PR> [merge_method]     # method: squash (default) | merge | rebase
#   Ex: scripts/gh/safe-merge.sh 3767
#       scripts/gh/safe-merge.sh 3767 squash
#
# GARANTIA (camada 1): merge só acontece se o head que o GitHub tem == o que você empurrou.
# REDE (camada 2): pós-merge, confere que os arquivos add/mod do PR existem em origin/main.
set -euo pipefail

PR="${1:?uso: safe-merge.sh <PR> [squash|merge|rebase]}"
METHOD="${2:-squash}"

command -v gh >/dev/null || { echo "✗ gh CLI não encontrado no PATH"; exit 2; }

REPO="$(gh repo view --json nameWithOwner -q .nameWithOwner)"
LOCAL="$(git rev-parse HEAD)"
BRANCH="$(git rev-parse --abbrev-ref HEAD)"

echo "── safe-merge · PR #$PR · $REPO · method=$METHOD ──"
echo "  branch local : $BRANCH"
echo "  HEAD local   : $LOCAL"

# 1) local == remoto (nada pendente de push que o merge fosse engolir)
git fetch -q origin "$BRANCH" 2>/dev/null || true
UPSTREAM="$(git rev-parse "@{u}" 2>/dev/null || echo '')"
if [ -n "$UPSTREAM" ] && [ "$LOCAL" != "$UPSTREAM" ]; then
  echo "✗ branch local ($LOCAL) ≠ remoto ($UPSTREAM). Faça 'git push' antes de mergear."
  exit 1
fi

# 2) o head que o GitHub ACHA que é o do PR == o que empurrei (pré-check legível)
REMOTE_HEAD="$(gh pr view "$PR" --json headRefOid -q .headRefOid)"
echo "  headRefOid GH: $REMOTE_HEAD"
if [ "$REMOTE_HEAD" != "$LOCAL" ]; then
  echo "✗ DESYNC: GitHub tem head $REMOTE_HEAD, você tem $LOCAL."
  echo "  Causas: push ainda propagando, ou alguém empurrou. Espere ~15s e rode de novo."
  exit 1
fi

# 3) MERGE PINADO — o servidor 409s se o head mexeu entre o check e o merge (atômico)
echo "  → merge pinado no SHA (server rejeita se head mudar)…"
if ! gh api -X PUT "repos/$REPO/pulls/$PR/merge" \
      -f sha="$LOCAL" -f merge_method="$METHOD" >/dev/null 2>/tmp/safe-merge-err; then
  echo "✗ merge rejeitado:"; sed 's/^/    /' /tmp/safe-merge-err
  echo "  Se disser 'Head branch was modified' = o desync foi pego ANTES de estragar. ✓ guard funcionou."
  exit 1
fi
echo "  ✓ merge aceito (sha pinado)."

# 4) REDE pós-merge — confere que os arquivos add/mod do PR estão em origin/main.
#    IMPORTANTE (Windows/Git-Bash): NÃO usar `git cat-file -e origin/main:$path` — o MSYS
#    mangleia o revspec `<ref>:<path>` (`:`→`;`, `/`→`\`), dá falso "AUSENTE" (cry-wolf).
#    `git ls-tree <ref> -- <path>` separa ref de path com `--` e é imune ao mangling.
#    (bug pego pelo próprio dogfood do #3768 — a rede acusou drop que não existiu.)
echo "  → verificando arquivos do PR em origin/main…"
git fetch -q origin
MISSING=0
while IFS=$'\t' read -r status path; do
  case "$status" in
    added|modified)
      if [ -z "$(git ls-tree origin/main -- "$path" 2>/dev/null)" ]; then
        echo "  ⚠ AUSENTE em origin/main: $path (status PR=$status)"; MISSING=$((MISSING+1))
      fi ;;
  esac
done < <(gh api "repos/$REPO/pulls/$PR/files" --paginate -q '.[] | "\(.status)\t\(.filename)"')

if [ "$MISSING" -gt 0 ]; then
  echo "✗ $MISSING arquivo(s) do PR NÃO estão em origin/main — possível commit engolido. INVESTIGAR."
  exit 3
fi
echo "  ✓ todos os arquivos add/mod do PR presentes em origin/main."
echo "✓ safe-merge OK · PR #$PR mergeado sem perda."
