"""Migrate legacy ADR schema (adr/deciders/date/references) to canonical (slug/number/decided_by/decided_at/related).

Only acts on ADRs that have status: proposto/proposed/rascunho/draft.
"""
import re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

TARGETS = [
    'memory/decisions/0122-admin-center-ct100.md',
    'memory/decisions/0123-modules-arquivos-backbone.md',
    'memory/decisions/0124-curador-conhecimento-pipeline.md',
    'memory/decisions/0125-modules-autopecas-feature-wish.md',
    'memory/decisions/0126-mcp-jira-projects-modulos-verticais.md',
]

DECIDER_MAP = {
    'wagner': 'W', 'felipe': 'F', 'maiara': 'M', 'luiz': 'L', 'eliana': 'E',
}

for t in TARGETS:
    fp = base / t
    if not fp.exists():
        print(f"MISSING: {t}")
        continue
    content = fp.read_text(encoding='utf-8')

    fm_match = re.match(r'^(---\n)(.*?)(\n---\n)(.*)$', content, re.DOTALL)
    if not fm_match:
        print(f"NO FM: {t}")
        continue
    prefix, fm, suffix_sep, body = fm_match.groups()

    # Extract canonical filename info
    fname = fp.stem  # ex 0122-admin-center-ct100
    m = re.match(r'^(\d{4})-(.+)$', fname)
    if not m:
        print(f"BAD FILENAME: {t}")
        continue
    num = int(m.group(1))
    slug = fname

    # Parse simple fields from old format
    title = ''
    title_m = re.search(r'^title:\s*(.+)$', fm, re.MULTILINE)
    if title_m:
        title = title_m.group(1).strip().strip('"').strip("'")

    status = 'proposto'
    sm = re.search(r'^status:\s*(\S+)', fm, re.MULTILINE)
    if sm:
        status = sm.group(1).strip()
        if status == 'proposed':
            status = 'proposto'

    date = '2026-05-13'
    dm = re.search(r'^date:\s*(\S+)', fm, re.MULTILINE)
    if dm:
        date = dm.group(1).strip().strip('"').strip("'")
        # If integer timestamp leave as is fallback
        if not re.match(r'^\d{4}-\d{2}-\d{2}$', date):
            date = '2026-05-13'

    # deciders: [Wagner] -> decided_by: [W]
    deciders = []
    dm2 = re.search(r'^deciders:\s*\[([^\]]*)\]', fm, re.MULTILINE)
    if dm2:
        for tok in dm2.group(1).split(','):
            tok = tok.strip().strip('"').strip("'")
            if not tok: continue
            mapped = DECIDER_MAP.get(tok.lower())
            if mapped:
                deciders.append(mapped)
            elif tok in ('W','F','M','L','E'):
                deciders.append(tok)
    if not deciders:
        deciders = ['W']

    # Parse references list (formatted as YAML list `references:`)
    references = []
    ref_m = re.search(r'^references:\n((?:\s*-[^\n]*\n)+)', fm, re.MULTILINE)
    if ref_m:
        for line in ref_m.group(1).split('\n'):
            line = line.strip()
            if line.startswith('- '):
                ref = line[2:].strip().strip('"').strip("'")
                # Strip .md suffix
                ref = re.sub(r'\.md$', '', ref)
                if re.match(r'^\d{4}-[a-z0-9-]+$', ref):
                    references.append(ref)

    # Build new frontmatter — preserve existing custom fields, override schema-required
    # Strip schema-required fields from old fm first
    SCHEMA_FIELDS = ('adr', 'title', 'status', 'date', 'deciders', 'references', 'slug', 'number', 'type', 'authority', 'lifecycle', 'decided_by', 'decided_at', 'related')

    custom_lines = []
    in_list = False
    list_field = None
    for line in fm.split('\n'):
        # Skip lines for schema fields we'll rewrite
        m_field = re.match(r'^([a-zA-Z_]+):', line)
        if m_field and m_field.group(1) in SCHEMA_FIELDS:
            in_list = m_field.group(1) in ('deciders', 'references') and ('[' not in line or not line.rstrip().endswith(']'))
            list_field = m_field.group(1) if in_list else None
            continue
        # If continuation of list
        if in_list and (line.startswith('  ') or line.startswith('-')):
            continue
        else:
            in_list = False
        custom_lines.append(line)

    # Compose new frontmatter
    new_fm_lines = [
        f'slug: {slug}',
        f'number: {num}',
        f'title: "{title}"',
        'type: adr',
        f'status: {status}',
        'authority: canonical',
        'lifecycle: ativo',
        'decided_by:',
    ]
    for d in deciders:
        new_fm_lines.append(f'  - {d}')
    new_fm_lines.append(f'decided_at: "{date}"')
    if references:
        new_fm_lines.append('related:')
        for r in references:
            new_fm_lines.append(f'  - "{r}"')
    # Append custom fields preserved
    custom_text = '\n'.join(l for l in custom_lines if l.strip()).strip()
    if custom_text:
        new_fm_lines.append(custom_text)

    new_fm = '\n'.join(new_fm_lines)
    new_content = prefix + new_fm + '\n' + suffix_sep + body
    fp.write_text(new_content, encoding='utf-8')
    print(f"FIXED: {t}")
