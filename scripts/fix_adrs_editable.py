"""Fix ADR frontmatter for editable (non-accepted) ADRs.

- Convert related[] integer → slug string
- Convert superseded_by[] integer → slug string
- Convert decided_at integer (YAML date timestamp) → string YYYY-MM-DD
- Convert number string → integer
- Translate status proposed → proposto

CRITICAL: skip ADRs with status accepted/aceito/aceita (Tier 0 append-only).
"""
import json, re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / '.adr_slug_map.json', encoding='utf-8') as f:
    adr_map = {int(k): v for k, v in json.load(f).items()}

with open(base / '.categorization.json', encoding='utf-8') as f:
    cats = json.load(f)

# Strict skip list — append-only Tier 0
SKIP_STATUSES = {'accepted', 'aceito', 'aceita'}

STATUS_TRANSLATE = {
    'proposed': 'proposto',
    'draft': 'rascunho',
    'archived': 'arquivado',
}

fixed = 0
skipped_accepted = []
skipped_other = []

for item in cats['B_adr_other']:
    status = item.get('status', 'unknown')
    if status.lower() in SKIP_STATUSES:
        skipped_accepted.append(item['file'])
        continue
    fp = base / item['file']
    if not fp.exists():
        skipped_other.append((str(fp), 'missing'))
        continue
    content = fp.read_text(encoding='utf-8')

    fm_match = re.match(r'^(---\n)(.*?)(\n---\n)(.*)$', content, re.DOTALL)
    if not fm_match:
        skipped_other.append((str(fp), 'no-frontmatter'))
        continue

    prefix, fm, suffix_sep, body = fm_match.groups()
    new_fm = fm
    needs_change = False

    # Translate status proposed → proposto
    def fix_status(m):
        prefix_y = m.group(1)
        val = m.group(2).strip()
        if val in STATUS_TRANSLATE:
            return f'{prefix_y}{STATUS_TRANSLATE[val]}'
        return m.group(0)

    new_fm2 = re.sub(
        r'^(status:\s*)([^\s\n]+)\s*$',
        fix_status,
        new_fm,
        flags=re.MULTILINE,
    )
    if new_fm2 != new_fm:
        new_fm = new_fm2
        needs_change = True

    # Fix related[], superseded_by[], supersedes[] — convert integers to slugs
    def fix_adr_list(field_name):
        def repl(m):
            prefix_y = m.group(1)
            items_str = m.group(2)
            out = []
            for tok in items_str.split(','):
                tok = tok.strip()
                if not tok:
                    continue
                if tok.startswith('"') or tok.startswith("'"):
                    inner = tok.strip('"').strip("'")
                    if re.match(r'^\d{4}-[a-z0-9-]+$', inner):
                        out.append(f'"{inner}"')
                        continue
                    # quoted but doesn't match pattern
                    # try parse as number
                    try:
                        n = int(inner)
                        slug = adr_map.get(n)
                        out.append(f'"{slug}"' if slug else f'"{n:04d}-unknown"')
                    except ValueError:
                        out.append(tok)
                    continue
                # unquoted — likely integer
                try:
                    n = int(tok)
                    slug = adr_map.get(n)
                    out.append(f'"{slug}"' if slug else f'"{n:04d}-unknown"')
                except ValueError:
                    out.append(tok)
            return f'{prefix_y}[{", ".join(out)}]'
        return repl

    # Handle bracket format [a, b, c]
    for field in ('related', 'superseded_by', 'supersedes'):
        pattern = r'^(' + field + r':\s*)\[([^\]]*)\]'
        new_fm2 = re.sub(pattern, fix_adr_list(field), new_fm, flags=re.MULTILINE)
        if new_fm2 != new_fm:
            new_fm = new_fm2
            needs_change = True

    # Handle YAML list format - field:\n  - item\n  - item
    def fix_yaml_list(m):
        field_name = m.group(1)
        block = m.group(2)
        items = re.findall(r'^\s*-\s*(.+?)$', block, re.MULTILINE)
        out = []
        for raw in items:
            raw = raw.strip().strip('"').strip("'")
            if re.match(r'^\d{4}-[a-z0-9-]+$', raw):
                out.append(f'  - "{raw}"')
                continue
            try:
                n = int(raw)
                slug = adr_map.get(n)
                out.append(f'  - "{slug}"' if slug else f'  - "{n:04d}-unknown"')
            except ValueError:
                out.append(f'  - "{raw}"')
        return f'{field_name}:\n' + '\n'.join(out) + '\n'

    for field in ('related', 'superseded_by', 'supersedes'):
        pattern = r'^(' + field + r'):\n((?:\s*-[^\n]*\n)+)'
        new_fm2 = re.sub(pattern, fix_yaml_list, new_fm, flags=re.MULTILINE)
        if new_fm2 != new_fm:
            new_fm = new_fm2
            needs_change = True

    # Fix decided_at integer → string
    def fix_decided_at(m):
        prefix_y = m.group(1)
        val = m.group(2).strip()
        # If unquoted YYYY-MM-DD or integer, normalize
        if re.match(r'^\d{4}-\d{2}-\d{2}$', val):
            return f'{prefix_y}"{val}"'
        return m.group(0)

    new_fm2 = re.sub(
        r'^(decided_at:\s*)([^"\'\n][^\n]*?)\s*$',
        fix_decided_at,
        new_fm,
        flags=re.MULTILINE,
    )
    if new_fm2 != new_fm:
        new_fm = new_fm2
        needs_change = True

    # Fix number: '5' → 5 (integer)
    def fix_number(m):
        prefix_y = m.group(1)
        val = m.group(2).strip().strip('"').strip("'")
        try:
            n = int(val)
            return f'{prefix_y}{n}'
        except ValueError:
            return m.group(0)

    new_fm2 = re.sub(
        r'^(number:\s*)([\'"]?\d+[\'"]?)\s*$',
        fix_number,
        new_fm,
        flags=re.MULTILINE,
    )
    if new_fm2 != new_fm:
        new_fm = new_fm2
        needs_change = True

    if needs_change:
        new_content = prefix + new_fm.rstrip() + '\n' + suffix_sep + body
        fp.write_text(new_content, encoding='utf-8')
        fixed += 1
    else:
        skipped_other.append((str(fp), 'no-change'))

print(f"Fixed ADRs editable: {fixed}")
print(f"Skipped as ACCEPTED (Tier 0 append-only): {len(skipped_accepted)}")
for f in skipped_accepted:
    print(f"  TIER0: {f}")
print(f"Other skips: {len(skipped_other)}")
for fp, reason in skipped_other:
    print(f"  {reason}: {fp}")
