"""Fix RUNBOOK frontmatter: add owner, last_validated, normalize status, fix related_adrs, add title."""
import json, re, subprocess
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / '.adr_slug_map.json', encoding='utf-8') as f:
    adr_map = {int(k): v for k, v in json.load(f).items()}

with open(base / '.categorization.json', encoding='utf-8') as f:
    cats = json.load(f)

STATUS_FIX = {
    'active': 'ativo',
    'live': 'ativo',
    'draft': 'rascunho',
    'archived': 'arquivado',
    'em-construcao': 'rascunho',
    'em construcao': 'rascunho',
    'planned': 'rascunho',
    'planning': 'rascunho',
}

def get_git_first_author(fp):
    try:
        cwd = Path('D:/oimpresso.com')
        rel = fp.resolve().relative_to(cwd) if str(fp.resolve()).startswith(str(cwd.resolve())) else fp
        result = subprocess.run(
            ['git', 'log', '--format=%an', '--', str(rel).replace('\\', '/')],
            cwd=str(cwd), capture_output=True, text=True, timeout=10
        )
        names = result.stdout.strip().split('\n')
        # Most frequent
        from collections import Counter
        if names and names[0]:
            most = Counter(names).most_common(1)[0][0]
            # Map to W/F/M/L/E
            if 'Wagner' in most or 'wagner' in most.lower() or 'oimpresso' in most.lower() or 'Office Impresso' in most:
                return 'W'
            if 'felipe' in most.lower(): return 'F'
            if 'maiara' in most.lower(): return 'M'
            if 'luiz' in most.lower(): return 'L'
            if 'eliana' in most.lower(): return 'E'
    except Exception:
        pass
    return 'W'  # default Wagner

fixed = 0
skipped = []

for item in cats['D_runbook']:
    fp = base / item['file']
    if not fp.exists():
        skipped.append((str(fp), 'missing'))
        continue
    content = fp.read_text(encoding='utf-8')

    fm_match = re.match(r'^(---\n)(.*?)(\n---\n)(.*)$', content, re.DOTALL)
    if not fm_match:
        skipped.append((str(fp), 'no-frontmatter'))
        continue

    prefix, fm, suffix_sep, body = fm_match.groups()
    new_fm = fm
    needs_change = False

    # Add title if missing — infer from H1
    if not re.search(r'^title:\s*\S+', new_fm, re.MULTILINE):
        h1_match = re.search(r'^# (.+)$', body, re.MULTILINE)
        if h1_match:
            title = h1_match.group(1).strip()
            title_safe = title.replace('"', "'")
            new_fm = f'title: "{title_safe}"\n' + new_fm
            needs_change = True

    # Add owner if missing
    if not re.search(r'^owner:\s*\S+', new_fm, re.MULTILINE):
        owner = get_git_first_author(fp)
        new_fm = new_fm.rstrip() + f'\nowner: {owner}\n'
        needs_change = True

    # Add last_validated if missing
    if not re.search(r'^last_validated:\s*\S+', new_fm, re.MULTILINE):
        new_fm = new_fm.rstrip() + f'\nlast_validated: "2026-05-13"\n'
        needs_change = True

    # Fix status
    def fix_status(m):
        prefix_y = m.group(1)
        val = m.group(2).strip().strip('"').strip("'")
        if val in ('rascunho', 'ativo', 'arquivado', 'historical'):
            return m.group(0)
        new_val = STATUS_FIX.get(val.lower(), 'rascunho')
        return f'{prefix_y}{new_val}'

    new_fm2 = re.sub(
        r'^(status:\s*)([^\n]+?)\s*$',
        fix_status,
        new_fm,
        flags=re.MULTILINE,
    )
    if new_fm2 != new_fm:
        new_fm = new_fm2
        needs_change = True

    # Fix related_adrs
    def fix_related_adrs(m):
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
                else:
                    try:
                        n = int(inner)
                        slug = adr_map.get(n)
                        out.append(f'"{slug}"' if slug else f'"{n:04d}-unknown"')
                    except ValueError:
                        out.append(tok)
                continue
            try:
                n = int(tok)
                slug = adr_map.get(n)
                out.append(f'"{slug}"' if slug else f'"{n:04d}-unknown"')
            except ValueError:
                out.append(tok)
        return f'{prefix_y}[{", ".join(out)}]'

    new_fm2 = re.sub(
        r'^(related_adrs:\s*)\[([^\]]*)\]',
        fix_related_adrs,
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
        skipped.append((str(fp), 'no-change'))

print(f"Fixed: {fixed}")
print(f"Skipped: {len(skipped)}")
for fp, reason in skipped:
    print(f"  {reason}: {fp}")
