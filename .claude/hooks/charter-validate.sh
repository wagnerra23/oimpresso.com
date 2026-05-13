#!/usr/bin/env bash
# Hook PreToolUse — WARN/BLOCK Edit/Write em Pages/<Mod>/<Tela>.tsx sem charter-fetch prévia.
# Equivalente POSIX do charter-validate.ps1 (Unix/Hostinger).
#
# GAP-ANALYSIS-91-100-2026-05-13 (C1 P0 Onda 4) — ativa Page Charters S4.
# Princípio Constituição V2 #3 (Charter > Spec — ADR 0094 + ADR 0101).
#
# Modo default WARNING. Strict mode: export CHARTER_VALIDATE_STRICT=1.

set -euo pipefail

# Lê stdin (payload JSON Claude Code)
INPUT=$(cat || true)
if [ -z "$INPUT" ]; then
    exit 0
fi

# Extrai tool_name e file_path via jq se disponível; senão grep simples
if command -v jq >/dev/null 2>&1; then
    TOOL=$(printf '%s' "$INPUT" | jq -r '.tool_name // empty' 2>/dev/null || echo "")
    FILE_PATH=$(printf '%s' "$INPUT" | jq -r '.tool_input.file_path // empty' 2>/dev/null || echo "")
else
    TOOL=$(printf '%s' "$INPUT" | grep -oE '"tool_name"\s*:\s*"[^"]*"' | sed 's/.*:\s*"\([^"]*\)".*/\1/')
    FILE_PATH=$(printf '%s' "$INPUT" | grep -oE '"file_path"\s*:\s*"[^"]*"' | sed 's/.*:\s*"\([^"]*\)".*/\1/')
fi

case "$TOOL" in
    Write|Edit|MultiEdit) ;;
    *) exit 0 ;;
esac

[ -z "$FILE_PATH" ] && exit 0

# Normaliza forward slashes
PATH_FWD=$(printf '%s' "$FILE_PATH" | sed 's|\\|/|g')

# Match Pages/<Mod>/<Tela>.tsx
if ! printf '%s' "$PATH_FWD" | grep -qE 'resources/js/Pages/[^/_][^/]*/([^/]+/)?[A-Za-z][A-Za-z0-9]*\.tsx$'; then
    exit 0
fi

# Charter esperado ao lado
CHARTER_PATH=$(printf '%s' "$FILE_PATH" | sed 's/\.tsx$/.charter.md/')
[ ! -f "$CHARTER_PATH" ] && exit 0

# Extrai status do charter (primeiras 30 linhas — frontmatter)
CHARTER_STATUS=$(head -n 30 "$CHARTER_PATH" 2>/dev/null | grep -m1 -E '^status:' | sed 's/^status:\s*//; s/["'"'"']//g; s/\s*$//' || echo "unknown")
[ -z "$CHARTER_STATUS" ] && CHARTER_STATUS="unknown"

CHARTER_REL=$(printf '%s' "$CHARTER_PATH" | sed 's|\\|/|g')
MSG="[charter-first] $TOOL em '$PATH_FWD' detectado — esta tela TEM contrato vivo em '$CHARTER_REL' (status: $CHARTER_STATUS). "
MSG="${MSG}Princípio Constituição V2 #3 (Charter > Spec — ADR 0094 + ADR 0101): chame tool MCP \`charter-fetch page_id:'$PATH_FWD'\` ANTES de editar pra carregar Mission/Goals/Non-Goals/UX targets/Anti-hooks. "
MSG="${MSG}Skill \`charter-first\` Tier A. "

if [ "${CHARTER_VALIDATE_STRICT:-0}" = "1" ]; then
    MSG="${MSG}Modo STRICT (env CHARTER_VALIDATE_STRICT=1) — Edit BLOQUEADO."
    printf '{"decision":"deny","reason":"charter-first Tier A — strict mode","systemMessage":"%s"}\n' "$(printf '%s' "$MSG" | sed 's/"/\\"/g')"
    exit 0
fi

# Modo warning default
MSG="${MSG}Modo warning-mode (P1 — vira bloqueante quando ROI provado em ≥5 sessões)."
printf '{"decision":"allow","systemMessage":"%s"}\n' "$(printf '%s' "$MSG" | sed 's/"/\\"/g')"

exit 0
