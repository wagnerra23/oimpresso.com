#!/usr/bin/env python3
"""
Backfill conservador de frontmatter YAML em memory/decisions/*.md +
memory/handoffs/*.md pra ratificar com schemas canon.

Fixes aplicados (apenas LINHA-A-LINHA, sem re-serializar YAML):
  1. `date: YYYY-MM-DD` → `date: "YYYY-MM-DD"` (aspas — schema quer string)
  2. `decided_at: YYYY-MM-DD` → `decided_at: "YYYY-MM-DD"`
  3. `accepted_at: YYYY-MM-DD` → idem
  4. `number: 0NNN` → `number: NNN` (integer sem leading zero)
  5. `related: [0061, ...]` → `related: ["0061-...", ...]` via mapping decisions/
  6. `supersedes: [0061, ...]` → idem
  7. `superseded_by: [0061, ...]` → idem
  8. `related_adrs: [0061, ...]` → idem (handoffs)

NÃO TOCA:
  - Conteúdo markdown abaixo de `---`
  - Strings que já estão aspeadas
  - Campos required faltantes (decisão manual case-a-case)
  - tldr > 500 chars (truncar é destrutivo — deixa pro humano)

Modo `--check` apenas reporta o que mudaria sem aplicar.
"""

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent.parent
DECISIONS = ROOT / "memory" / "decisions"
HANDOFFS = ROOT / "memory" / "handoffs"

# Mapping NNNN → "NNNN-slug" via listing real de decisions/
SLUG_MAP: dict[str, str] = {}
for p in DECISIONS.glob("*.md"):
    name = p.stem  # ex: "0191-microsoft-clarity-session-replay-lgpd"
    m = re.match(r"^(\d{4})-(.+)$", name)
    if m:
        num = m.group(1)
        # Se já houver mapping (caso 0170 tenha 2 ADRs), pegar o primeiro encontrado
        SLUG_MAP.setdefault(num, name)


def expand_related_items(line: str) -> str:
    """
    Converte arrays inline tipo `related: [0061, 0062]` → `related: ["0061-slug", "0062-slug"]`.
    Preserva comentários e indentação.
    """
    m = re.match(r"^(\s*)(related|supersedes|superseded_by|related_adrs|relacionada_a|errata_de|supersedes_partially)(\s*:\s*)\[([^\]]+)\](.*)$", line)
    if not m:
        return line

    indent, key, sep, inner, tail = m.groups()
    items = [x.strip() for x in inner.split(",") if x.strip()]
    if not items:
        return line

    new_items: list[str] = []
    changed = False
    for it in items:
        # Remove aspas existentes pra normalizar
        clean = it.strip().strip('"').strip("'")
        # Caso 1: já está no formato "NNNN-slug" (com ou sem aspas)
        if re.match(r"^\d{4}-[a-z0-9-]+$", clean):
            new_items.append(f'"{clean}"')
        # Caso 2: só NNNN (integer ou string curta)
        elif re.match(r"^\d{1,4}$", clean):
            num = clean.zfill(4)
            slug = SLUG_MAP.get(num)
            if slug:
                new_items.append(f'"{slug}"')
                changed = True
            else:
                # Sem mapping — usa placeholder explícito
                new_items.append(f'"{num}-unknown"')
                changed = True
        else:
            # Outros padrões (path absoluto, slug livre) — mantém aspeado
            new_items.append(f'"{clean}"')
            if not (it.startswith('"') or it.startswith("'")):
                changed = True

    if not changed and inner.count('"') >= len(items):
        return line  # nada mudou + tudo já aspeado

    # Preserva line ending da linha original (CRLF/LF/nenhum)
    if line.endswith("\r\n"):
        eol = "\r\n"
    elif line.endswith("\n"):
        eol = "\n"
    else:
        eol = ""
    tail_clean = tail.rstrip("\r\n")
    return f"{indent}{key}{sep}[{', '.join(new_items)}]{tail_clean}{eol}"


# Canonização status legacy → ADR enum válido
STATUS_MAP = {
    "accepted": "aceito",
    "Accepted": "aceito",
    "ACCEPTED": "aceito",
    "proposed": "proposto",
    "Proposed": "proposto",
    "PROPOSED": "proposto",
    "draft": "rascunho",
    "Draft": "rascunho",
    "DRAFT": "rascunho",
    "deprecated": "deprecated",
    "Deprecated": "deprecated",
    "superseded": "superseded",
    "Superseded": "superseded",
}

# Canonização decided_by string → array [W/F/M/L/E]
USER_MAP = {
    "wagner": "W",
    "Wagner": "W",
    "felipe": "F",
    "Felipe": "F",
    "maiara": "M",
    "Maiara": "M",
    "luiz": "L",
    "Luiz": "L",
    "eliana": "E",
    "Eliana": "E",
}


def fix_line(line: str) -> tuple[str, bool]:
    """Retorna (linha_corrigida, mudou). Preserva line ending original (CRLF/LF)."""
    original = line

    # Detecta line ending pra reaplicar fielmente
    if line.endswith("\r\n"):
        eol = "\r\n"
        body = line[:-2]
    elif line.endswith("\n"):
        eol = "\n"
        body = line[:-1]
    else:
        eol = ""
        body = line

    # 1-3. Quote ISO date em campos date/decided_at/accepted_at (sem aspas)
    m = re.match(r'^(\s*)(date|decided_at|accepted_at)(\s*:\s*)(\d{4}-\d{2}-\d{2})(\s*)$', body)
    if m:
        indent, key, sep, dt, _ = m.groups()
        body = f'{indent}{key}{sep}"{dt}"'

    # 4. number: 0NNN → number: NNN
    m = re.match(r'^(\s*)number(\s*:\s*)0+(\d{1,4})(\s*)$', body)
    if m:
        indent, sep, n, _ = m.groups()
        body = f'{indent}number{sep}{int(n)}'

    # 4b. status: legacy → enum válido pra ADR
    m = re.match(r'^(\s*)status(\s*:\s*)(["\']?)([A-Za-z]+)(["\']?)(\s*)$', body)
    if m:
        indent, sep, q1, val, q2, _ = m.groups()
        if val in STATUS_MAP and val != STATUS_MAP[val]:
            body = f'{indent}status{sep}{STATUS_MAP[val]}'

    # 4c. decided_by: wagner (string singleton) → [W]
    m = re.match(r'^(\s*)decided_by(\s*:\s*)(["\']?)([A-Za-z]+)(["\']?)(\s*)$', body)
    if m:
        indent, sep, q1, val, q2, _ = m.groups()
        if val in USER_MAP:
            body = f'{indent}decided_by{sep}[{USER_MAP[val]}]'

    # 4d. accepted_by: wagner → decided_by: [W] alias (preserva ambos por compat)
    m = re.match(r'^(\s*)accepted_by(\s*:\s*)(["\']?)([A-Za-z]+)(["\']?)(\s*)$', body)
    if m:
        indent, sep, q1, val, q2, _ = m.groups()
        if val in USER_MAP:
            # Substitui accepted_by por decided_by canon (mais correto)
            body = f'{indent}decided_by{sep}[{USER_MAP[val]}]'

    # 4e. authors: [wagner, claude] → ignora; ADR não usa esse campo
    # (campo authors é legacy livre, schema additionalProperties: true aceita)

    # 4f. adr: 0NNN (legacy field não padronizado) → number: NNN
    m = re.match(r'^(\s*)adr(\s*:\s*)0*(\d{1,4})(\s*)$', body)
    if m:
        indent, sep, n, _ = m.groups()
        body = f'{indent}number{sep}{int(n)}'

    # 5-8. Expand related/supersedes/superseded_by/related_adrs com NNNN puro
    expanded = expand_related_items(body + eol)
    # expand_related_items pode adicionar \n se entrada não tem; normaliza pro eol original
    if expanded.endswith("\n") and not expanded.endswith("\r\n") and eol == "\r\n":
        expanded = expanded[:-1] + eol
    elif expanded.endswith("\n") and eol == "":
        expanded = expanded[:-1]

    return expanded, expanded != original


def process_file(path: Path, dry_run: bool) -> int:
    """Retorna número de linhas alteradas no frontmatter."""
    text = path.read_text(encoding="utf-8")
    lines = text.splitlines(keepends=True)

    if not lines or not lines[0].startswith("---"):
        return 0

    # Localiza fim do frontmatter
    end_idx = None
    for i, l in enumerate(lines[1:], start=1):
        if l.strip() == "---":
            end_idx = i
            break
    if end_idx is None:
        return 0

    changed = 0
    new_fm = []
    for l in lines[:end_idx + 1]:
        new_l, did_change = fix_line(l)
        if did_change:
            changed += 1
        new_fm.append(new_l)

    if changed == 0:
        return 0

    new_content = "".join(new_fm) + "".join(lines[end_idx + 1:])
    if not dry_run:
        path.write_text(new_content, encoding="utf-8", newline="")

    return changed


def main() -> int:
    dry_run = "--check" in sys.argv

    total_files = 0
    total_lines = 0

    for folder in [DECISIONS, HANDOFFS]:
        if not folder.exists():
            continue
        for p in sorted(folder.glob("*.md")):
            if p.stem.startswith("_") or p.stem == "README":
                continue
            n = process_file(p, dry_run)
            if n > 0:
                total_files += 1
                total_lines += n
                print(f"  [{'DRY' if dry_run else 'FIX'}] {p.relative_to(ROOT)} ({n} lines)")

    print()
    print(f"{'[DRY-RUN] would fix' if dry_run else 'Fixed'}: {total_files} arquivos, {total_lines} linhas")
    print(f"SLUG_MAP cached: {len(SLUG_MAP)} ADRs")
    return 0


if __name__ == "__main__":
    sys.exit(main())
