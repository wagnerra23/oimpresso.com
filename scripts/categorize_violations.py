"""Categorize frontmatter violations by editability."""
import json, re
from pathlib import Path

base = Path('D:/oimpresso.com/.claude/worktrees/nervous-mayer-3ff0da')

with open(base / 'violations.json', encoding='utf-8-sig') as f:
    data = json.load(f)

categories = {
    'A_adr_accepted': [],
    'B_adr_other': [],
    'C_spec': [],
    'D_runbook': [],
    'E_session': [],
    'F_handoff': [],
    'G_charter': [],
}

for v in data['buckets']['adr']['violations']:
    if v['level'] != 'error':
        continue
    fp_str = v['file'].replace('\\', '/')
    fp = base / fp_str
    try:
        content = fp.read_text(encoding='utf-8')
    except Exception:
        categories['B_adr_other'].append({'file': fp_str, 'status': 'unreadable', 'errors': v['errors']})
        continue
    fm_match = re.search(r'^---\n(.*?)\n---', content, re.DOTALL)
    if not fm_match:
        categories['B_adr_other'].append({'file': fp_str, 'status': 'no-frontmatter', 'errors': v['errors']})
        continue
    fm = fm_match.group(1)
    status_match = re.search(r'^status:\s*(\S+)', fm, re.MULTILINE)
    status = status_match.group(1).strip() if status_match else 'unknown'
    item = {'file': fp_str, 'status': status, 'errors': v['errors']}
    if status in ('aceito', 'accepted'):
        categories['A_adr_accepted'].append(item)
    else:
        categories['B_adr_other'].append(item)

for v in data['buckets']['spec']['violations']:
    if v['level'] == 'error':
        categories['C_spec'].append({'file': v['file'].replace('\\', '/'), 'errors': v['errors']})
for v in data['buckets']['runbook']['violations']:
    if v['level'] == 'error':
        categories['D_runbook'].append({'file': v['file'].replace('\\', '/'), 'errors': v['errors']})
for v in data['buckets']['session']['violations']:
    if v['level'] == 'error':
        categories['E_session'].append({'file': v['file'].replace('\\', '/'), 'errors': v['errors']})
for v in data['buckets']['handoff']['violations']:
    if v['level'] == 'error':
        categories['F_handoff'].append({'file': v['file'].replace('\\', '/'), 'errors': v['errors']})
for v in data['buckets']['charter']['violations']:
    if v['level'] == 'error':
        categories['G_charter'].append({'file': v['file'].replace('\\', '/'), 'errors': v['errors']})

print("Breakdown by category:")
print(f"A (ADR accepted - INTOCÁVEL): {len(categories['A_adr_accepted'])}")
print(f"B (ADR proposed/draft/superseded): {len(categories['B_adr_other'])}")
print(f"C (SPEC): {len(categories['C_spec'])}")
print(f"D (RUNBOOK): {len(categories['D_runbook'])}")
print(f"E (Session): {len(categories['E_session'])}")
print(f"F (Handoff): {len(categories['F_handoff'])}")
print(f"G (Charter): {len(categories['G_charter'])}")
total = sum(len(v) for v in categories.values())
print(f"TOTAL: {total}")

with open(base / '.categorization.json', 'w', encoding='utf-8') as f:
    json.dump(categories, f, indent=2, ensure_ascii=False)
print("Saved to .categorization.json")

# Show breakdown by status for ADRs
from collections import Counter
adr_status_count = Counter(item['status'] for item in categories['B_adr_other'])
print(f"\nB sub-breakdown by status: {dict(adr_status_count)}")
