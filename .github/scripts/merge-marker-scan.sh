#!/usr/bin/env bash
# merge-marker-scan.sh — Detecta marcadores de conflito de merge COMMITADOS.
#
# Mecanismo #2 ENFORCEMENT — Job do governance-gate.yml.
# Complementa o hook runtime .claude/hooks/block-merge-markers.ps1 (PreToolUse):
# o hook barra na hora do Write/Edit local; este gate barra no CI, pegando o que
# entrou por caminho que não passou pelo hook (merge manual, cherry-pick, edição
# fora do agente). Origem: 2026-07-02 — 11 CHANGELOG/README de módulos entraram em
# origin/main com marcadores `<<<<<<< / ======= / >>>>>>>` commitados (canon
# malformado), corrigidos no PR #3660. Nenhum gate os pegava.
#
# Uso:
#   .github/scripts/merge-marker-scan.sh [-v] [arquivo1 arquivo2 ...]
#   .github/scripts/merge-marker-scan.sh -v                 # lê git diff HEAD~1
#
# Exit codes:
#   0 — sem marcadores
#   1 — marcador de conflito detectado
#   2 — erro de uso
#
# Detecção: linhas que COMEÇAM com `<<<<<<< ` ou `>>>>>>> ` (7 chars + espaço).
#   São inequívocas — um conflito real sempre tem início E fim. O separador nu
#   `=======` NÃO é usado como sinal (colide com underline de heading RST/Markdown
#   setext → falso-positivo). Todo conflito de verdade tem os marcadores início/fim,
#   então isso basta.
#
# Falso-positivos legítimos (fixtures que PRECISAM conter os marcadores):
#   Listados em SKIP_FILE_REGEX abaixo. Manter mínimo e explícito.

set -euo pipefail

VERBOSE=0
ARGS=()

while [ $# -gt 0 ]; do
  case "$1" in
    -v|--verbose) VERBOSE=1; shift ;;
    -h|--help) grep -E '^# ' "$0" | sed 's/^# //'; exit 0 ;;
    --) shift; ARGS+=("$@"); break ;;
    -*) echo "ERRO: flag desconhecida: $1" >&2; exit 2 ;;
    *) ARGS+=("$1"); shift ;;
  esac
done

# Sem args: lê git diff HEAD~1 (último commit) como fallback
if [ ${#ARGS[@]} -eq 0 ]; then
  if git rev-parse --git-dir > /dev/null 2>&1; then
    mapfile -t ARGS < <(git diff --name-only --diff-filter=AM HEAD~1 2>/dev/null || true)
  fi
  if [ ${#ARGS[@]} -eq 0 ]; then
    [ "$VERBOSE" -eq 1 ] && echo "[merge-marker-scan] Nada pra varrer."
    exit 0
  fi
fi

# Marcadores início/fim de conflito (ERE POSIX — grep -E no runner ubuntu).
MARKER_REGEX='^(<<<<<<< |>>>>>>> )'

# Diretórios/extensões pulados (binários, vendored).
SKIP_DIR_REGEX='^(vendor|node_modules|public|storage|bootstrap/cache|\.git)/'
SKIP_EXT_REGEX='\.(lock|min\.js|min\.css|map|svg|png|jpg|jpeg|gif|webp|pdf|zip|woff2?|ttf|eot)$'

# Fixtures que legitimamente contêm marcadores (self-test do próprio mecanismo).
# Manter EXPLÍCITO e mínimo — cada entrada é uma exceção auditável.
SKIP_FILE_REGEX='(\.claude/hooks/block-merge-markers\.test\.ps1|\.github/scripts/merge-marker-scan\.sh)$'

violations=0
violation_report=""

for file in "${ARGS[@]}"; do
  [ -z "$file" ] && continue
  [ ! -f "$file" ] && continue

  if [[ "$file" =~ $SKIP_DIR_REGEX ]] || [[ "$file" =~ $SKIP_EXT_REGEX ]]; then
    [ "$VERBOSE" -eq 1 ] && echo "[skip] $file (vendored/binary)"
    continue
  fi
  if [[ "$file" =~ $SKIP_FILE_REGEX ]]; then
    [ "$VERBOSE" -eq 1 ] && echo "[skip] $file (fixture allowlisted)"
    continue
  fi

  matches=$(grep -nHE "$MARKER_REGEX" "$file" 2>/dev/null || true)
  [ -z "$matches" ] && continue

  while IFS= read -r line; do
    violations=$((violations + 1))
    violation_report="${violation_report}${line}"$'\n'
  done <<< "$matches"
done

if [ "$violations" -gt 0 ]; then
  echo "::error::Marcador de conflito de merge commitado ($violations linha(s))."
  echo ""
  echo "Linhas:"
  echo "$violation_report"
  echo "Como resolver:"
  echo "  - Resolver o conflito de verdade: mesclar os dois lados no conteúdo correto"
  echo "    e REMOVER as linhas <<<<<<< / ======= / >>>>>>>."
  echo "  - Em CHANGELOG (append-only): preservar ambos os lados, mais novo no topo."
  echo "  - Se for fixture legítima (self-test), adicionar à SKIP_FILE_REGEX do scan."
  echo ""
  echo "Contexto: canon malformado = defeito. Complementa o hook runtime"
  echo ".claude/hooks/block-merge-markers.ps1 (PreToolUse). Origem: PR #3660 (2026-07-02)."
  exit 1
fi

[ "$VERBOSE" -eq 1 ] && echo "[merge-marker-scan] ✅ Nenhum marcador de conflito em ${#ARGS[@]} arquivo(s)."
exit 0
