"""Fix session frontmatter: date string + topic added + related_adrs slugs + authors enum."""
import json, re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / '.adr_slug_map.json', encoding='utf-8') as f:
    adr_map = {int(k): v for k, v in json.load(f).items()}

with open(base / '.categorization.json', encoding='utf-8') as f:
    cats = json.load(f)

# Allowed authors
ALLOWED_AUTHORS = {'W', 'F', 'M', 'L', 'E', 'C'}

# Map known invalid authors to valid
AUTHOR_FIXES = {
    'Wagner': 'W',
    'Felipe': 'F',
    'Maiara': 'M',
    'Luiz': 'L',
    'Eliana': 'E',
    'Claude': 'C',
}

fixed = 0
skipped = []
notes = []

for item in cats['E_session']:
    fp = base / item['file']
    if not fp.exists():
        skipped.append((str(fp), 'missing'))
        continue
    content = fp.read_text(encoding='utf-8')

    fm_match = re.match(r'^(---\n)(.*?)(\n---\n)(.*)$', content, re.DOTALL)
    if not fm_match:
        # No frontmatter — need to add one
        # Extract from filename: YYYY-MM-DD-<slug>
        fname = fp.stem
        m = re.match(r'^(\d{4}-\d{2}-\d{2})-(.+)$', fname)
        if not m:
            skipped.append((str(fp), 'no-date-in-filename'))
            continue
        date_str = m.group(1)
        topic = m.group(2).replace('-', ' ').strip()
        # Get title from H1 if present
        h1_match = re.search(r'^# (.+)$', content, re.MULTILINE)
        if h1_match:
            topic = h1_match.group(1).strip()
        topic = topic[:200]
        new_fm = f'date: "{date_str}"\ntopic: "{topic}"\n'
        new_content = f'---\n{new_fm}---\n\n{content}'
        fp.write_text(new_content, encoding='utf-8')
        fixed += 1
        notes.append((str(fp), 'added new frontmatter'))
        continue

    prefix, fm, suffix_sep, body = fm_match.groups()
    new_fm = fm

    # Fix date: 2026-05-04 -> "2026-05-04" OR "2026-05-12 17:00 BRT" -> "2026-05-12" + time field
    def fix_date(m):
        prefix_y = m.group(1)
        val = m.group(2).strip()
        # If date includes time: "2026-05-12 17:00 BRT"
        date_with_time = re.match(r'^(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}:\d{2}(?:\s*BRT|\s*UTC)?))?', val)
        if date_with_time:
            d = date_with_time.group(1)
            t = date_with_time.group(2)
            if t:
                return f'{prefix_y}"{d}"\ntime: "{t}"'
            return f'{prefix_y}"{d}"'
        return m.group(0)

    new_fm = re.sub(
        r'^(date:\s*)([^\n]+?)\s*$',
        fix_date,
        new_fm,
        flags=re.MULTILINE,
    )

    # Add topic if missing — derive from title or filename
    if not re.search(r'^topic:\s*', new_fm, re.MULTILINE):
        # Get topic from title field or H1
        topic = None
        title_match = re.search(r'^title:\s*["\']?(.+?)["\']?\s*$', new_fm, re.MULTILINE)
        if title_match:
            topic = title_match.group(1).strip().strip('"').strip("'")
        if not topic:
            # H1
            h1_match = re.search(r'^# (.+)$', body, re.MULTILINE)
            if h1_match:
                topic = h1_match.group(1).strip()
        if not topic:
            fname = fp.stem
            m = re.match(r'^\d{4}-\d{2}-\d{2}-(.+)$', fname)
            if m:
                topic = m.group(1).replace('-', ' ').strip()
        if topic:
            topic = topic[:200]
            # Escape quotes
            topic_safe = topic.replace('"', "'")
            new_fm = new_fm.rstrip() + f'\ntopic: "{topic_safe}"\n'

    # Fix authors enum
    def fix_authors(m):
        prefix_y = m.group(1)
        items_str = m.group(2)
        out = []
        for tok in items_str.split(','):
            tok = tok.strip().strip('"').strip("'")
            if not tok:
                continue
            if tok in ALLOWED_AUTHORS:
                out.append(tok)
            elif tok in AUTHOR_FIXES:
                out.append(AUTHOR_FIXES[tok])
            else:
                # Try first letter
                if tok[0].upper() in ALLOWED_AUTHORS:
                    out.append(tok[0].upper())
                else:
                    out.append(tok)  # leave as-is
        return f'{prefix_y}[{", ".join(out)}]'

    new_fm = re.sub(
        r'^(authors:\s*)\[([^\]]*)\]',
        fix_authors,
        new_fm,
        flags=re.MULTILINE,
    )

    # Fix related_adrs: integer -> slug
    def fix_related_adrs(m):
        prefix_y = m.group(1)
        items_str = m.group(2)
        out = []
        for tok in items_str.split(','):
            tok = tok.strip()
            if not tok:
                continue
            if tok.startswith('"') or tok.startswith("'"):
                # Already a string — check if matches pattern
                inner = tok.strip('"').strip("'")
                if re.match(r'^\d{4}-[a-z0-9-]+$', inner):
                    out.append(f'"{inner}"')
                else:
                    # try number
                    try:
                        n = int(inner)
                        slug = adr_map.get(n)
                        if slug:
                            out.append(f'"{slug}"')
                        else:
                            out.append(f'"{n:04d}-unknown"')
                    except ValueError:
                        out.append(tok)
                continue
            try:
                n = int(tok)
                slug = adr_map.get(n)
                if slug:
                    out.append(f'"{slug}"')
                else:
                    out.append(f'"{n:04d}-unknown"')
            except ValueError:
                out.append(tok)
        return f'{prefix_y}[{", ".join(out)}]'

    new_fm = re.sub(
        r'^(related_adrs:\s*)\[([^\]]*)\]',
        fix_related_adrs,
        new_fm,
        flags=re.MULTILINE,
    )

    # Fix duration: invalid pattern → leave alone? Just remove if invalid
    # ex: 2026-05-10-consolidacao-massiva-auto-mem.md
    def fix_duration(m):
        prefix_y = m.group(1)
        val = m.group(2).strip().strip('"').strip("'")
        if re.match(r'^\d+(\.\d+)?h$', val):
            return m.group(0)
        # try to extract hours
        hm = re.match(r'^(\d+(?:\.\d+)?)\s*h', val)
        if hm:
            return f'{prefix_y}"{hm.group(1)}h"'
        # else strip the line entirely
        return ''

    new_fm = re.sub(
        r'^(duration:\s*)([^\n]+)\s*$',
        fix_duration,
        new_fm,
        flags=re.MULTILINE,
    )
    # cleanup double newlines
    new_fm = re.sub(r'\n\n+', '\n', new_fm)

    if new_fm.strip() != fm.strip():
        new_content = prefix + new_fm.rstrip() + '\n' + suffix_sep + body
        fp.write_text(new_content, encoding='utf-8')
        fixed += 1
    else:
        skipped.append((str(fp), 'no-change-needed'))

print(f"Fixed: {fixed}")
print(f"Skipped: {len(skipped)}")
for fp, reason in skipped:
    print(f"  {reason}: {fp}")
for fp, n in notes:
    print(f"  NOTE: {n}: {fp}")
