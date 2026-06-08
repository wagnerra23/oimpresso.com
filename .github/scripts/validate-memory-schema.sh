#!/usr/bin/env bash
# validate-memory-schema.sh — gate D6 #4 (memoria-senior audit 2026-05-15)
#
# Valida schema RÍGIDO de conteúdo (não só frontmatter YAML):
#   - filename regex
#   - frontmatter mínimo (campos requeridos)
#   - seções obrigatórias (regex no corpo markdown)
#   - lista US no formato US-<MOD>-<NNN>
#
# Complementa o workflow existente memory-schema-gate.yml (que cobre só ADRs +
# AJV/JSON Schema do frontmatter). Aqui mira SPEC/Session/Handoff e SEÇÕES do
# corpo.
#
# Uso:
#   validate-memory-schema.sh <type> <file...>
#   type: spec | session | handoff
#
# Exit:
#   0 = sem violations
#   1 = violations encontradas (lista em stderr, JSON em $VIOLATIONS_JSON)
#   2 = erro de uso (type/args inválidos)
#
# Override: arquivo pode conter linha `<!-- schema-allowlist: <razão> -->` pra
# pular validação (registra warning em violations.json).
#
# PT-BR mensagens user-visible.
set -euo pipefail

TYPE="${1:-}"
shift || true

if [[ -z "${TYPE}" ]]; then
  echo "ERRO uso: $0 <spec|session|handoff> <file...>" >&2
  exit 2
fi

if [[ "$#" -eq 0 ]]; then
  echo "[OK] nenhum arquivo passado (type=${TYPE}) — nada a validar." >&2
  exit 0
fi

VIOLATIONS_JSON="${VIOLATIONS_JSON:-violations.json}"
VIOLATIONS=()
FAILED=0
SKIPPED=0

# Detecta binário Python (GHA Linux runner tem python3; Windows local tem python).
PYTHON_BIN=""
for candidate in python3 python py; do
  if command -v "$candidate" >/dev/null 2>&1; then
    # Confere se executa (Windows tem stub python3 que abre Microsoft Store).
    if "$candidate" -c "import sys; sys.exit(0)" >/dev/null 2>&1; then
      PYTHON_BIN="$candidate"
      break
    fi
  fi
done

if [[ -z "$PYTHON_BIN" ]]; then
  echo "ERRO python não disponível (testado: python3, python, py) — script requer Python 3.x" >&2
  exit 2
fi

add_violation() {
  local file="$1" level="$2" msg="$3"
  # Escape pra JSON simples (sem dependência de jq).
  local esc_file esc_msg
  esc_file="$(printf '%s' "$file" | "$PYTHON_BIN" -c 'import json,sys; print(json.dumps(sys.stdin.read()))')"
  esc_msg="$(printf '%s' "$msg" | "$PYTHON_BIN" -c 'import json,sys; print(json.dumps(sys.stdin.read()))')"
  VIOLATIONS+=("{\"file\":${esc_file},\"level\":\"${level}\",\"error\":${esc_msg}}")
  if [[ "$level" == "error" ]]; then
    echo "::error file=${file}::${msg}" >&2
    FAILED=$((FAILED + 1))
  else
    echo "::warning file=${file}::${msg}" >&2
  fi
}

has_allowlist() {
  local file="$1"
  grep -qE '<!--\s*schema-allowlist:' "$file" 2>/dev/null
}

# Extrai frontmatter YAML (entre --- e ---) usando python3 + PyYAML se houver,
# senão fallback grep simples.
extract_frontmatter_field() {
  local file="$1" field="$2"
  "$PYTHON_BIN" - "$file" "$field" <<'PY' 2>/dev/null || true
import sys, re
path, field = sys.argv[1], sys.argv[2]
try:
    raw = open(path, encoding='utf-8').read()
except Exception:
    sys.exit(0)
m = re.match(r'^---\s*\n(.*?)\n---\s*\n', raw, re.DOTALL)
if not m:
    sys.exit(0)
fm = m.group(1)
try:
    import yaml
    data = yaml.safe_load(fm) or {}
    val = data.get(field)
    if val is None:
        sys.exit(0)
    if isinstance(val, (list, dict)):
        import json
        print(json.dumps(val))
    else:
        print(val)
except ImportError:
    # Fallback: regex linha-simples key: value (sem suporte a list/dict aninhados).
    for line in fm.splitlines():
        m2 = re.match(rf'^{re.escape(field)}\s*:\s*(.*)$', line)
        if m2:
            print(m2.group(1).strip().strip('"').strip("'"))
            break
PY
}

has_frontmatter() {
  local file="$1"
  head -n 1 "$file" 2>/dev/null | grep -qE '^---\s*$'
}

# Procura seção markdown via regex (case-insensitive, captura `## Title`).
# Wagner 2026-05-25: aceita prefixo numerado opcional `## 2. Title` que é
# convenção dos SPECs do projeto (## 1. Glossário · ## 2. User stories · ## 3. ...).
has_section() {
  local file="$1" pattern="$2"
  grep -qiE "^##\s+([0-9]+\.\s+)?${pattern}" "$file" 2>/dev/null
}

# Valida que pelo menos uma das seções está presente.
has_any_section() {
  local file="$1"
  shift
  local found=0
  for pat in "$@"; do
    if has_section "$file" "$pat"; then
      found=1
      break
    fi
  done
  return $((1 - found))
}

# Valida lista US no formato US-<MOD>-<NNN>. Se aparece QUALQUER coisa que
# pareça US-*-* mas não bate o regex canônico, falha. Se nenhum US- aparece,
# ok (SPEC introdutório).
validate_us_format() {
  local file="$1"
  # Procura todas as ocorrências US-<X>-<Y>.
  local all_us
  all_us="$(grep -oE 'US-[A-Za-z0-9]+-[0-9]+' "$file" 2>/dev/null || true)"
  if [[ -z "$all_us" ]]; then
    return 0
  fi
  # Procura referências malformadas (não batem ^US-[A-Z]{2,8}-[0-9]{3,4}$).
  local malformed
  malformed="$(printf '%s\n' "$all_us" | grep -vE '^US-[A-Z]{2,8}-[0-9]{3,4}$' || true)"
  if [[ -n "$malformed" ]]; then
    # Anexa exemplos malformados via stderr pra debug (até 3).
    local sample
    sample="$(printf '%s' "$malformed" | head -n 3 | tr '\n' ' ')"
    echo "::debug::US malformadas detectadas: $sample" >&2
    return 1
  fi
  return 0
}

# Validação por tipo.
validate_spec() {
  local file="$1"

  # 1. Frontmatter presente.
  if ! has_frontmatter "$file"; then
    add_violation "$file" "error" "SPEC sem frontmatter YAML (esperado bloco --- ... --- no topo)"
    return
  fi

  # 2. Campos obrigatórios.
  for field in module last_updated version owner; do
    local val
    val="$(extract_frontmatter_field "$file" "$field")"
    if [[ -z "$val" ]]; then
      # Tenta variante 'owners' (lista, schema legacy ONDA 5 S1).
      if [[ "$field" == "owner" ]]; then
        val="$(extract_frontmatter_field "$file" "owners")"
        if [[ -n "$val" ]]; then
          continue
        fi
      fi
      add_violation "$file" "error" "SPEC frontmatter campo obrigatório ausente: '${field}'"
    fi
  done

  # 3. module deve ser PascalCase (ou _PascalCase pra pseudo-módulos cross-cutting).
  # Wagner 2026-05-25: aceita prefixo `_` opcional pra módulos pseudo-cross-cutting
  # do tipo _DesignSystem (não é nWidart Modules/ — é cross-cutting design system).
  local module
  module="$(extract_frontmatter_field "$file" "module")"
  if [[ -n "$module" ]] && ! [[ "$module" =~ ^_?[A-Z][a-zA-Z0-9]+$ ]]; then
    add_violation "$file" "error" "SPEC module '${module}' não é PascalCase válido (ex: Jana, NfeBrasil, RecurringBilling, _DesignSystem)"
  fi

  # 4. last_updated YYYY-MM-DD.
  local lu
  lu="$(extract_frontmatter_field "$file" "last_updated")"
  if [[ -n "$lu" ]] && ! [[ "$lu" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
    add_violation "$file" "error" "SPEC last_updated '${lu}' fora do formato YYYY-MM-DD"
  fi

  # 5. version pattern (vN.N.N ou N.N.N — schema ONDA 5 S1 aceita ambos).
  local v
  v="$(extract_frontmatter_field "$file" "version")"
  if [[ -n "$v" ]] && ! [[ "$v" =~ ^v?[0-9]+\.[0-9]+(\.[0-9]+)?$ ]]; then
    add_violation "$file" "error" "SPEC version '${v}' fora do formato vN.N.N ou N.N.N"
  fi

  # 6. Seções obrigatórias.
  if ! has_any_section "$file" "Backlog ativo" "US ativas" "User stories" "Personas"; then
    add_violation "$file" "error" "SPEC sem seção '## Backlog ativo' ou '## US ativas' ou '## User stories'"
  fi
  if ! has_section "$file" "Histórico"; then
    if ! has_section "$file" "Historico"; then
      add_violation "$file" "warn" "SPEC sem seção '## Histórico' (recomendado pra auditoria)"
    fi
  fi
  if ! has_section "$file" "Referências"; then
    if ! has_section "$file" "Referencias"; then
      add_violation "$file" "warn" "SPEC sem seção '## Referências' (recomendado — link ADRs/RUNBOOKs)"
    fi
  fi

  # 7. US format.
  if ! validate_us_format "$file"; then
    add_violation "$file" "error" "SPEC contém US malformadas (esperado US-<MOD>-<NNN>, ex: US-COPI-001, US-NFE-042)"
  fi
}

validate_session() {
  local file="$1"
  local base
  base="$(basename "$file")"

  # 1. Filename regex YYYY-MM-DD-<slug>.md.
  if ! [[ "$base" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-[a-z0-9-]+\.md$ ]]; then
    add_violation "$file" "error" "Session filename '${base}' fora do regex ^YYYY-MM-DD-<slug-kebab>.md$"
    return
  fi

  # 2. Frontmatter (opcional pra legacy, mas se presente valida).
  if has_frontmatter "$file"; then
    local date_v topic
    date_v="$(extract_frontmatter_field "$file" "date")"
    topic="$(extract_frontmatter_field "$file" "topic")"
    if [[ -z "$date_v" ]]; then
      add_violation "$file" "error" "Session frontmatter campo 'date' obrigatório ausente"
    elif ! [[ "$date_v" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}$ ]]; then
      add_violation "$file" "error" "Session date '${date_v}' fora do formato YYYY-MM-DD"
    fi
    if [[ -z "$topic" ]]; then
      add_violation "$file" "error" "Session frontmatter campo 'topic' obrigatório ausente"
    fi
  else
    add_violation "$file" "warn" "Session sem frontmatter YAML (legacy aceito; recomendado adicionar)"
  fi

  # 3. Pelo menos uma seção TL;DR / Resumo executivo / heading qualquer.
  if ! has_any_section "$file" "TL;DR" "Resumo executivo" "Resumo" "Contexto"; then
    add_violation "$file" "error" "Session sem '## TL;DR' nem '## Resumo executivo' nem '## Contexto'"
  fi
}

validate_handoff() {
  local file="$1"
  local base
  base="$(basename "$file")"

  # Templates / README aceitos.
  if [[ "$base" =~ ^_TEMPLATE ]] || [[ "$base" == "README.md" ]]; then
    return
  fi

  # 1. Filename regex YYYY-MM-DD-HHMM-<slug>.md (ADR 0130).
  if ! [[ "$base" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}-[0-9]{4}-[a-z0-9-]+\.md$ ]]; then
    add_violation "$file" "error" "Handoff filename '${base}' fora do regex ^YYYY-MM-DD-HHMM-<slug-kebab>.md$ (ADR 0130)"
    return
  fi

  # 2. Frontmatter (opcional pra legacy mas se presente valida date/slug/tldr).
  if has_frontmatter "$file"; then
    for field in date slug tldr; do
      local val
      val="$(extract_frontmatter_field "$file" "$field")"
      if [[ -z "$val" ]]; then
        add_violation "$file" "error" "Handoff frontmatter campo '${field}' obrigatório ausente"
      fi
    done
  else
    add_violation "$file" "warn" "Handoff sem frontmatter YAML (legacy aceito; recomendado adicionar — ver _TEMPLATE.md)"
  fi

  # 3. Seção MCP-first obrigatória (ADR 0130 §6).
  if ! has_section "$file" "Estado MCP no momento do fechamento"; then
    # Aceita variantes.
    if ! has_section "$file" "Estado MCP" && ! has_section "$file" "MCP no momento"; then
      add_violation "$file" "error" "Handoff sem '## Estado MCP no momento do fechamento' (ADR 0130 §6 — prova MCP-first, não promessa)"
    fi
  fi

  # 4. TL;DR pelo menos.
  if ! has_any_section "$file" "TL;DR" "Resumo" "Resumo executivo"; then
    add_violation "$file" "warn" "Handoff sem '## TL;DR' — primeiro item recomendado pelo ADR 0130 pra leitura rápida"
  fi
}

# Loop arquivos.
for FILE in "$@"; do
  if [[ ! -f "$FILE" ]]; then
    echo "[SKIP] arquivo não existe: $FILE" >&2
    continue
  fi

  # Ignora templates explicitamente.
  case "$(basename "$FILE")" in
    _TEMPLATE*|README.md|INDEX.md|_INDEX*.md)
      SKIPPED=$((SKIPPED + 1))
      continue
      ;;
  esac

  if has_allowlist "$FILE"; then
    add_violation "$FILE" "warn" "schema-allowlist marker presente — validação pulada"
    SKIPPED=$((SKIPPED + 1))
    continue
  fi

  case "$TYPE" in
    spec)    validate_spec "$FILE" ;;
    session) validate_session "$FILE" ;;
    handoff) validate_handoff "$FILE" ;;
    *)
      echo "ERRO type inválido: '$TYPE' (esperado spec|session|handoff)" >&2
      exit 2
      ;;
  esac
done

# Persiste violations JSON.
{
  printf '['
  first=1
  for v in "${VIOLATIONS[@]:-}"; do
    if [[ -z "${v:-}" ]]; then continue; fi
    if [[ $first -eq 1 ]]; then
      printf '%s' "$v"
      first=0
    else
      printf ',%s' "$v"
    fi
  done
  printf ']\n'
} > "$VIOLATIONS_JSON"

TOTAL=$#
echo "Arquivos validados: $((TOTAL - SKIPPED)) (skipados: ${SKIPPED}) — erros: ${FAILED}" >&2

if [[ "$FAILED" -gt 0 ]]; then
  exit 1
fi
exit 0
