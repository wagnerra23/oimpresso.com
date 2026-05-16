"""Fix SPEC frontmatter: add version, last_updated, normalize status, fix related_adrs."""
import json, re, subprocess
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / '.adr_slug_map.json', encoding='utf-8') as f:
    adr_map = {int(k): v for k, v in json.load(f).items()}

with open(base / '.categorization.json', encoding='utf-8') as f:
    cats = json.load(f)

# Map non-standard statuses to valid enum
STATUS_FIX = {
    'feature-wish': 'rascunho',
    'em-construcao': 'rascunho',
    'em construcao': 'rascunho',
    'em produção': 'ativo',
    'em-producao': 'ativo',
    'producao': 'ativo',
    'production': 'ativo',
    'live': 'ativo',
    'draft': 'rascunho',
    'archived': 'arquivado',
    'planned': 'rascunho',
    'planning': 'rascunho',
}

def get_git_last_date(fp):
    """Get last commit date of file in YYYY-MM-DD."""
    try:
        # Run from D:/oimpresso.com main (canonical history)
        cwd = Path('D:/oimpresso.com')
        rel = fp.resolve().relative_to(cwd) if str(fp.resolve()).startswith(str(cwd.resolve())) else fp
        result = subprocess.run(
            ['git', 'log', '-1', '--format=%cs', '--', str(rel).replace('\\', '/')],
            cwd=str(cwd), capture_output=True, text=True, timeout=10
        )
        out = result.stdout.strip()
        if re.match(r'^\d{4}-\d{2}-\d{2}$', out):
            return out
    except Exception as e:
        pass
    return '2026-05-13'  # fallback today

fixed = 0
skipped = []

for item in cats['C_spec']:
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

    # Get module from path: memory/requisitos/<Mod>/SPEC.md
    rel_path = item['file']
    m = re.search(r'memory/requisitos/([^/]+)/SPEC\.md', rel_path)
    module_name = m.group(1) if m else 'Unknown'

    # Add module if missing
    if not re.search(r'^module:\s*\S+', new_fm, re.MULTILINE):
        new_fm = f'module: {module_name}\n' + new_fm
        needs_change = True

    # Add version if missing (default 1.0)
    if not re.search(r'^version:\s*\S+', new_fm, re.MULTILINE):
        new_fm = new_fm.rstrip() + '\nversion: "1.0"\n'
        needs_change = True

    # Add last_updated if missing
    if not re.search(r'^last_updated:\s*\S+', new_fm, re.MULTILINE):
        last_date = get_git_last_date(fp)
        new_fm = new_fm.rstrip() + f'\nlast_updated: "{last_date}"\n'
        needs_change = True

    # Fix status if not in enum
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
