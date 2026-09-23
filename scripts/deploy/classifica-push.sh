#!/usr/bin/env bash
# Classifica um run do deploy.yml: runtime_changed (deploy completo × sync leve) e
# frontend_changed (liga o gate de hash de bundle no smoke).
#
# Chamado pelo job `build` de .github/workflows/deploy.yml. Grava as chaves em
# $GITHUB_OUTPUT (se definido) e sempre em stdout.
#
# ── Por que a base é o ÚLTIMO DEPLOY BEM-SUCEDIDO e não `github.event.before` ──
# A concurrency `deploy-production` mantém 1 run pendente e cada push novo CANCELA
# o pendente anterior. Com a base em `event.before`, o diff via só o ÚLTIMO push:
# em 2026-09-23 o push do #7789 (120340ba2 — migration dre_linha + DreService lendo
# a coluna) teve o deploy completo cancelado; o push seguinte (49c420e25) não tocava
# runtime e foi pelo sync leve (git reset, SEM migrate). O código foi pro ar sem a
# coluna e /financeiro/dre deu 500 até a migração ser rodada à mão.
# A pergunta certa é "o que mudou desde o que o servidor TEM aplicado?", e isso é o
# headSha do último run `push` com conclusion=success.
#
# ── Por que runtime usa `git log --name-only` e não `git diff base..sha` ──
# Monotonicidade. Se a base encontrada for MAIS ANTIGA que a real (API atrasada),
# `git diff` pode ERRAR PRA MENOS: arquivo alterado e depois revertido dentro do
# intervalo some das pontas. "Arquivos tocados por algum commit do intervalo" é
# superconjunto do diff de qualquer sub-intervalo — base velha só pode empurrar
# pra deploy completo, nunca pra sync leve.
#
# --no-renames: com detecção de rename, mover app/X.php → docs/ mostraria só o
# destino (não-runtime) e esconderia a remoção do arquivo servido.
#
# ── Fail-closed ──
# Qualquer dúvida (evento que não é push, gh indisponível/erro, nenhuma base
# ancestral, git falhou, grep com erro real) ⇒ runtime_changed=true.
#
# Entradas (env): SHA (obrigatório), EVENT_NAME, RUN_ID, GH_BIN (default: gh),
#                 DEPLOY_WORKFLOW (default: deploy.yml), BASE_BRANCH (default: main).
set -uo pipefail

SHA="${SHA:?SHA obrigatório}"
EVENT_NAME="${EVENT_NAME:-}"
RUN_ID="${RUN_ID:-}"
GH_BIN="${GH_BIN:-gh}"
DEPLOY_WORKFLOW="${DEPLOY_WORKFLOW:-deploy.yml}"
BASE_BRANCH="${BASE_BRANCH:-main}"

# Por que cada path NÃO é runtime (e mesmo assim CHEGA ao servidor, via o
# `git reset --hard` do sync-light — só não exige maintenance/composer/cache):
#   .github/     → roda no runner do GitHub, nunca no Hostinger
#   .claude/     → config de agente (lida sob demanda por artisan)
#   scripts/     → invocado por Process/CLI a cada uso, sem restart
#   governance/  → JSON de baseline lido por request pelo Modules/Governance
#   tests/ docs/ memory/ prototipo-ui/ *.md + dotfiles de raiz
NAO_RUNTIME='^(\.github/|\.claude/|scripts/|governance/|tests/|docs/|memory/|prototipo-ui/|\.gitattributes$|\.gitignore$|\.editorconfig$|CODEOWNERS$)|\.md$'

emit() {
  echo "$1"
  if [ -n "${GITHUB_OUTPUT:-}" ]; then echo "$1" >> "$GITHUB_OUTPUT"; fi
}

# ── 1. Base = último run push bem-sucedido do deploy, ancestral de SHA ──────────
# NÃO usar `gh run list --status success`: medido 2026-09-23, o filtro no servidor
# devolveu runs de 2026-08-28 enquanto havia sucessos do próprio dia (índice
# atrasado — §5 2026-08-13). Lista sem filtro de status e filtra conclusion aqui.
# workflow_dispatch fica FORA: pode ter rodado com skip_migrate=true, e aí o SHA
# dele não tem o schema aplicado.
BASE=""
if [ "$EVENT_NAME" = "push" ]; then
  if CANDIDATOS=$("$GH_BIN" run list --workflow "$DEPLOY_WORKFLOW" --branch "$BASE_BRANCH" \
        --event push --limit 100 --json databaseId,headSha,conclusion \
        --jq '.[] | select(.conclusion=="success") | "\(.databaseId) \(.headSha)"'); then
    while read -r ID CAND; do
      [ -z "${CAND:-}" ] && continue
      [ -n "$RUN_ID" ] && [ "$ID" = "$RUN_ID" ] && continue
      git merge-base --is-ancestor "$CAND" "$SHA" 2>/dev/null
      case $? in
        0) BASE="$CAND"; echo "base = último deploy OK: ${CAND} (run ${ID})"; break ;;
        1) continue ;;   # não-ancestral (history reescrita): tenta o próximo
        *) echo "::warning::merge-base falhou pra ${CAND} — ignorando candidato" ;;
      esac
    done <<< "$CANDIDATOS"
    [ -z "$BASE" ] && echo "::warning::nenhum deploy bem-sucedido ancestral de ${SHA} — assumindo runtime_changed=true"
  else
    echo "::warning::gh run list falhou — sem base medida, assumindo runtime_changed=true"
  fi
else
  echo "evento '${EVENT_NAME}' não é push — deploy completo"
fi

# ── 2. frontend_changed (gate de hash do smoke) ────────────────────────────────
# Exclui *.md (charter.md/casos.md ficam sob resources/js/ por convenção, ADR 0264).
FRONTEND_CHANGED=unknown
if [ -n "$BASE" ]; then
  if FE=$(git diff --name-only "$BASE" "$SHA" -- resources/js resources/css ':(exclude)*.md'); then
    if [ -n "$FE" ]; then FRONTEND_CHANGED=true; else FRONTEND_CHANGED=false; fi
  fi
fi
emit "frontend_changed=$FRONTEND_CHANGED"

# ── 3. runtime_changed ─────────────────────────────────────────────────────────
RUNTIME_CHANGED=true
SOBRA=""
if [ -n "$BASE" ]; then
  if MUDADOS=$(git log --no-renames --format= --name-only -m "${BASE}..${SHA}"); then
    # UM grep só: com dois em pipeline, erro real do primeiro (rc=2) daria entrada
    # vazia pro segundo → "nada sobrou" → caminho leve por FALHA (§5 2026-08-11).
    # `^$` na mesma regex: linhas vazias do `--format=` não contam como runtime.
    SOBRA=$(printf '%s\n' "$MUDADOS" | grep -vE "^\$|$NAO_RUNTIME")
    GREP_RC=$?
    if [ "$GREP_RC" -gt 1 ]; then
      echo "::warning::grep falhou (rc=$GREP_RC) — assumindo runtime_changed=true"
    elif [ -z "$SOBRA" ]; then
      RUNTIME_CHANGED=false
    fi
  else
    echo "::warning::git log ${BASE}..${SHA} falhou — assumindo runtime_changed=true"
  fi
fi
emit "runtime_changed=$RUNTIME_CHANGED"

if [ "$RUNTIME_CHANGED" = "false" ]; then
  echo "→ nada de runtime desde ${BASE}: SYNC LEVE (site NÃO sai do ar)"
else
  echo "→ DEPLOY COMPLETO (maintenance + migrate). Arquivos que decidiram:"
  printf '%s\n' "${SOBRA:-<sem base medida>}" | sort -u | head -20
fi
