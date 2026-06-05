#!/usr/bin/env python3
"""Integridade do prune: nenhuma classe VIVA pode ter perdido definição."""
import re
from pathlib import Path
ROOT = Path(__file__).resolve().parent.parent
ORIG = ROOT / "resources/css/cowork-canon-financeiro-bundle.css"
PRUNED = ROOT / "resources/css/cowork-canon-financeiro-bundle.css.pruned"
JS = ROOT / "resources/js"; VIEWS = ROOT / "resources/views"

used = set()
def scan(p):
    try: used.update(re.findall(r"[A-Za-z_][A-Za-z0-9_-]*", p.read_text(encoding="utf-8", errors="ignore")))
    except Exception: pass
for ext in ("*.tsx","*.ts","*.jsx"):
    for p in JS.rglob(ext):
        if "_cowork-bundle" in p.parts: continue
        scan(p)
for p in VIEWS.rglob("*.blade.php"): scan(p)

CLS = re.compile(r"\.(-?[A-Za-z_][A-Za-z0-9_-]*)")
def classes_in(path):
    s = path.read_text(encoding="utf-8")
    out = set()
    for blk in re.finditer(r"([^{}]+)\{", s):
        out.update(CLS.findall(blk.group(1)))
    return out

orig_cls = classes_in(ORIG)
pruned_cls = classes_in(PRUNED)

live_defined = {c for c in orig_cls if c == "fin-cowork" or c in used}
lost_live = sorted(live_defined - pruned_cls)
new_in_pruned = sorted(pruned_cls - orig_cls)  # deveria ser vazio

print(f"Classes definidas no original: {len(orig_cls)}")
print(f"Classes definidas no pruned:   {len(pruned_cls)}")
print(f"Classes VIVAS (usadas em prod) definidas no original: {len(live_defined)}")
print(f"VIVAS que PERDERAM definição no pruned: {len(lost_live)}")
if lost_live:
    print("  !! REGRESSÃO — estas classes vivas sumiram:")
    for c in lost_live: print("    ", c)
print(f"Classes novas que apareceram no pruned (deve ser 0): {len(new_in_pruned)}")
print()
print("RESULTADO:", "OK — nenhuma classe viva perdida" if not lost_live else "FALHOU")
