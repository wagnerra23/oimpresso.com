"""Dedupe superseded_by/related/supersedes YAML lists in ADRs (non-accepted)."""
import re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

# Edit-eligible non-accepted ADRs known to have duplicates
TARGETS = [
    'memory/decisions/0010-sistema-memoria-projeto.md',
    'memory/decisions/0031-memoriacontrato-mem0-default.md',
    'memory/decisions/0032-vizra-adk-prism-php-orquestracao.md',
    'memory/decisions/0033-vector-store-meilisearch-pgvector-mem0.md',
    'memory/decisions/0042-reverb-substitui-pusher-cloud.md',
    'memory/decisions/0079-constituicao-oimpresso-7-camadas-governanca.md',
]

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
    new_fm = fm

    # Dedupe YAML lists for known fields
    def dedupe_yaml_list(m):
        field = m.group(1)
        block = m.group(2)
        items = re.findall(r'^\s*-\s*(.+?)$', block, re.MULTILINE)
        seen = set()
        out = []
        for raw in items:
            raw = raw.strip()
            key = raw.strip('"').strip("'")
            if key in seen:
                continue
            seen.add(key)
            out.append(f'  - {raw}')
        return f'{field}:\n' + '\n'.join(out) + '\n'

    for field in ('superseded_by', 'related', 'supersedes'):
        pattern = r'^(' + field + r'):\n((?:\s*-[^\n]*\n)+)'
        new_fm2 = re.sub(pattern, dedupe_yaml_list, new_fm, flags=re.MULTILINE)
        if new_fm2 != new_fm:
            new_fm = new_fm2

    # Also dedupe inline arrays [a, b, b]
    def dedupe_inline(m):
        field = m.group(1)
        items_str = m.group(2)
        items = [s.strip() for s in items_str.split(',') if s.strip()]
        seen = set()
        out = []
        for x in items:
            key = x.strip('"').strip("'")
            if key in seen: continue
            seen.add(key)
            out.append(x)
        return f'{field}[{", ".join(out)}]'

    for field in ('superseded_by', 'related', 'supersedes'):
        pattern = r'^(' + field + r':\s*)\[([^\]]*)\]'
        new_fm2 = re.sub(pattern, dedupe_inline, new_fm, flags=re.MULTILINE)
        if new_fm2 != new_fm:
            new_fm = new_fm2

    if new_fm != fm:
        new_content = prefix + new_fm.rstrip() + '\n' + suffix_sep + body
        fp.write_text(new_content, encoding='utf-8')
        print(f"FIXED: {t}")
    else:
        print(f"NOCHANGE: {t}")
