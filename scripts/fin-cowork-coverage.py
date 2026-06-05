#!/usr/bin/env python3
"""
fin-cowork-coverage.py — coverage READ-ONLY do bundle cowork-canon-financeiro-bundle.css.

Mede quais classes definidas no bundle ainda são referenciadas em algum lugar de
resources/js/ (.tsx). Conservador: uma classe é considerada VIVA se aparecer como
token (\b<classe>\b) em QUALQUER .tsx — cobre className literal, template string,
clsx/cva, props. Só marca MORTA o que nunca aparece em lugar nenhum.

NÃO deleta nada. Só relata.
"""
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
def _arg(flag, default):
    return sys.argv[sys.argv.index(flag) + 1] if flag in sys.argv else default
BUNDLE = ROOT / _arg("--bundle", "resources/css/cowork-canon-financeiro-bundle.css")
JS_DIR = ROOT / "resources/js"

css = BUNDLE.read_text(encoding="utf-8")

# Extrai todos os nomes de classe que aparecem em SELETORES (não em valores).
# Pega `.classe` em qualquer seletor. Ignora pseudo (::before) e .fin-cowork wrapper.
# Regex de classe CSS: .[A-Za-z_-][A-Za-z0-9_-]*
selector_classes = set()
# Processa só as porções de seletor (antes de cada `{`).
for block in re.finditer(r"([^{}]+)\{", css):
    sel = block.group(1)
    for m in re.finditer(r"\.(-?[A-Za-z_][A-Za-z0-9_-]*)", sel):
        selector_classes.add(m.group(1))

selector_classes.discard("fin-cowork")  # wrapper, sempre vivo

# Coleta tokens usados em todo o .tsx (conservador).
tsx_text = []
for p in JS_DIR.rglob("*.tsx"):
    tsx_text.append(p.read_text(encoding="utf-8", errors="ignore"))
blob = "\n".join(tsx_text)
# Set de tokens word-like presentes (rápido).
used_tokens = set(re.findall(r"[A-Za-z_][A-Za-z0-9_-]*", blob))

dead, live = [], []
for c in sorted(selector_classes):
    # token exato (classes têm hífen, então o regex de token acima já as captura inteiras)
    if c in used_tokens:
        live.append(c)
    else:
        dead.append(c)

print(f"Bundle: {BUNDLE.relative_to(ROOT)}")
print(f"Linhas totais: {len(css.splitlines())}")
print(f"Classes distintas em seletores: {len(selector_classes)}")
print(f"  VIVAS (referenciadas em algum .tsx): {len(live)}")
print(f"  MORTAS (zero referência): {len(dead)}")
print()
print("=== amostra de classes MORTAS (até 60) ===")
for c in dead[:60]:
    print(" ", c)
if len(dead) > 60:
    print(f"  ... +{len(dead)-60} mais")
