"""Fix charter frontmatter: last_validated as string + related_adrs as quoted slugs/ints valid."""
import json, re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / '.adr_slug_map.json', encoding='utf-8') as f:
    adr_map = {int(k): v for k, v in json.load(f).items()}

with open(base / '.categorization.json', encoding='utf-8') as f:
    cats = json.load(f)

fixed = 0
skipped = []
for item in cats['G_charter']:
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

    # Fix last_validated: 2026-05-11 -> "2026-05-11"
    new_fm = re.sub(
        r'^(last_validated:\s*)(\d{4}-\d{2}-\d{2})(\s*)$',
        r'\1"\2"\3',
        new_fm,
        flags=re.MULTILINE,
    )

    # Fix related_adrs: [0039, 0058, ...] -> ["0039-slug", "0058-slug", ...]
    def replace_related(m):
        prefix_yaml = m.group(1)
        items_str = m.group(2)
        # parse comma-separated numbers
        nums = []
        for tok in items_str.split(','):
            tok = tok.strip()
            if not tok:
                continue
            # may already be quoted string slug
            if tok.startswith('"') or tok.startswith("'"):
                nums.append(tok)
                continue
            # numeric — possibly zero-padded
            try:
                n = int(tok)
            except ValueError:
                nums.append(tok)
                continue
            slug = adr_map.get(n)
            if slug:
                nums.append(f'"{slug}"')
            else:
                # unknown ADR num — keep as-is but format as 4-digit slug-style if possible
                nums.append(f'"{n:04d}-unknown"')
        return f'{prefix_yaml}[{", ".join(nums)}]'

    new_fm = re.sub(
        r'^(related_adrs:\s*)\[([^\]]*)\]',
        replace_related,
        new_fm,
        flags=re.MULTILINE,
    )

    if new_fm != fm:
        new_content = prefix + new_fm + suffix_sep + body
        fp.write_text(new_content, encoding='utf-8')
        fixed += 1
    else:
        skipped.append((str(fp), 'no-change'))

print(f"Fixed: {fixed}")
print(f"Skipped: {len(skipped)}")
for fp, reason in skipped:
    print(f"  {reason}: {fp}")
