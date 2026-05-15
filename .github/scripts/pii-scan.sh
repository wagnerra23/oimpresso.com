#!/usr/bin/env bash
# pii-scan.sh — Detecta CPF/CNPJ literais em arquivos do PR.
#
# Mecanismo #2 ENFORCEMENT — Job 3 do governance-gate.yml.
# Defesa LGPD Art. 7º + Constituição Art. 8 (Policy Gating).
#
# Uso:
#   .github/scripts/pii-scan.sh [-v] [arquivo1 arquivo2 ...]
#   .github/scripts/pii-scan.sh -v                  # lê git diff HEAD~1
#
# Exit codes:
#   0 — sem PII literal
#   1 — PII detectada
#   2 — erro de uso
#
# Falso-positivos esperados (PII de teste/exemplo):
#   Adicionar `# pii-allowlist` no MESMO comentário/linha pra ignorar.
#   Ex: `'000.000.000-00', // pii-allowlist (placeholder Pest factory)`

set -euo pipefail

VERBOSE=0
ARGS=()

while [ $# -gt 0 ]; do
  case "$1" in
    -v|--verbose)
      VERBOSE=1
      shift
      ;;
    -h|--help)
      grep -E '^# ' "$0" | sed 's/^# //'
      exit 0
      ;;
    --)
      shift
      ARGS+=("$@")
      break
      ;;
    -*)
      echo "ERRO: flag desconhecida: $1" >&2
      exit 2
      ;;
    *)
      ARGS+=("$1")
      shift
      ;;
  esac
done

# Sem args: lê git diff HEAD~1 (último commit) como fallback
if [ ${#ARGS[@]} -eq 0 ]; then
  if git rev-parse --git-dir > /dev/null 2>&1; then
    mapfile -t ARGS < <(git diff --name-only --diff-filter=AM HEAD~1 2>/dev/null || true)
  fi
  if [ ${#ARGS[@]} -eq 0 ]; then
    [ "$VERBOSE" -eq 1 ] && echo "[pii-scan] Nenhum arquivo passado e git diff vazio. Nada pra varrer."
    exit 0
  fi
fi

# Regex CPF: XXX.XXX.XXX-XX (com pontos + traço — formato padrão)
# Regex CNPJ: XX.XXX.XXX/XXXX-XX
# Usamos grep -E (POSIX ERE) — funciona no GitHub runner ubuntu-latest.
CPF_REGEX='[0-9]{3}\.[0-9]{3}\.[0-9]{3}-[0-9]{2}'
CNPJ_REGEX='[0-9]{2}\.[0-9]{3}\.[0-9]{3}/[0-9]{4}-[0-9]{2}'

# Diretórios e extensões pulados (binários, vendored)
SKIP_DIR_REGEX='^(vendor|node_modules|public|storage|bootstrap/cache|\.git)/'
SKIP_EXT_REGEX='\.(lock|min\.js|min\.css|map|svg|png|jpg|jpeg|gif|webp|pdf|zip|woff2?|ttf|eot)$'

violations=0
violation_report=""

for file in "${ARGS[@]}"; do
  # Defensivo: arquivo pode ter sido deletado ou ser path vazio
  [ -z "$file" ] && continue
  [ ! -f "$file" ] && continue

  # Pula vendored/binários
  if [[ "$file" =~ $SKIP_DIR_REGEX ]] || [[ "$file" =~ $SKIP_EXT_REGEX ]]; then
    [ "$VERBOSE" -eq 1 ] && echo "[skip] $file (vendored/binary)"
    continue
  fi

  # grep -nE pega CPF OU CNPJ; -H prefixa nome do arquivo
  matches=$(grep -nHE "($CPF_REGEX|$CNPJ_REGEX)" "$file" 2>/dev/null || true)
  [ -z "$matches" ] && continue

  while IFS= read -r line; do
    # Filtra allowlist (caso linha tenha 'pii-allowlist')
    if echo "$line" | grep -q 'pii-allowlist'; then
      [ "$VERBOSE" -eq 1 ] && echo "[allowlist] $line"
      continue
    fi

    # Caso sobreviva ao allowlist → violação
    violations=$((violations + 1))
    # Redact número antes de printar (não vazar PII no log público do GH Action).
    # Usa | como delimitador no sed pra evitar conflito com / do regex CNPJ.
    redacted=$(echo "$line" | sed -E "s|$CPF_REGEX|[REDACTED-CPF]|g; s|$CNPJ_REGEX|[REDACTED-CNPJ]|g")
    violation_report="${violation_report}${redacted}"$'\n'
  done <<< "$matches"
done

if [ "$violations" -gt 0 ]; then
  echo "::error::PII detectada ($violations ocorrência(s) CPF/CNPJ literal)."
  echo ""
  echo "Violações (números REDACTED no log público):"
  echo "$violation_report"
  echo ""
  echo "Como resolver:"
  echo "  - Substituir CPF/CNPJ por placeholder ([REDACTED], faker, factory Pest)"
  echo "  - SE for PII de teste legítima (Pest factory), adicionar '# pii-allowlist' no MESMO comentário"
  echo "  - Em runtime PHP, use App\\Support\\PiiRedactor antes de log/PR/commit"
  echo ""
  echo "Justificativa: LGPD Art. 7º (princípio da finalidade) + Constituição Art. 8."
  echo "PII vazada em git público é incidente de privacidade reportável (Art. 48 LGPD)."
  exit 1
fi

[ "$VERBOSE" -eq 1 ] && echo "[pii-scan] ✅ Nenhuma PII literal detectada em ${#ARGS[@]} arquivo(s)."
exit 0
