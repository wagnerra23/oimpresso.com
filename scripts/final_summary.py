"""Generate final report of remaining violations."""
import json, re
from pathlib import Path
from collections import Counter

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / 'violations_final.json', encoding='utf-8-sig') as f:
    data = json.load(f)

statuses = Counter()
accepted_list = []
other_list = []

for v in data['buckets']['adr']['violations']:
    if v['level'] != 'error':
        continue
    fp_str = v['file'].replace('\\', '/')
    fp = base / fp_str
    try:
        content = fp.read_text(encoding='utf-8')
    except Exception:
        continue
    m = re.search(r'^---\n(.*?)\n---', content, re.DOTALL)
    if not m:
        statuses['no-frontmatter'] += 1
        continue
    fm = m.group(1)
    sm = re.search(r'^status:\s*(\S+)', fm, re.MULTILINE)
    status = sm.group(1).strip() if sm else 'unknown'
    statuses[status] += 1
    if status.lower() in ('aceito', 'aceita', 'accepted'):
        accepted_list.append(fp_str)
    else:
        other_list.append((fp_str, status))

print('Status breakdown of remaining ADR errors:')
for s, c in statuses.most_common():
    print(f'  {c}x  {s}')
print()
print(f'Accepted (Tier 0 INTOC): {len(accepted_list)}')
print(f'Other (potentially missed): {len(other_list)}')
for fp, s in other_list:
    print(f'  [{s}] {fp}')

# Save lists for report
with open(base / '.adr_accepted_remaining.json', 'w', encoding='utf-8') as f:
    json.dump({'accepted': accepted_list, 'other': other_list, 'statuses': dict(statuses)}, f, indent=2, ensure_ascii=False)
