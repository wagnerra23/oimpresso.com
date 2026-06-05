#!/usr/bin/env python3
"""
fin-cowork-prune.py — remove rule-blocks PROVADAMENTE MORTOS do
cowork-canon-financeiro-bundle.css.

Regra de segurança (dead-code elimination, não refactor visual):
  - "vivo" = classe aparece como token em QUALQUER arquivo de produção
    (.tsx, .ts, .jsx exceto _cowork-bundle, .blade.php).
  - um rule-block é removível SE e SOMENTE SE todo branch (separado por
    vírgula top-level) do seu seletor contém ≥1 classe MORTA. Branch sem
    classe ou com todas as classes vivas => mantém o block inteiro.
  - @keyframes: mantidos integralmente (animation-name é difícil de rastrear).
  - .fin-cowork wrapper é sempre vivo (className="fin-cowork").

Como um elemento com classe morta NUNCA existe no DOM renderizado, remover
sua regra é visualmente inerte por construção.

Uso:
  python scripts/fin-cowork-prune.py            # gera .pruned + relatório (dry)
  python scripts/fin-cowork-prune.py --write    # sobrescreve o bundle
"""
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
BUNDLE = ROOT / "resources/css/cowork-canon-financeiro-bundle.css"
JS_DIR = ROOT / "resources/js"
VIEWS_DIR = ROOT / "resources/views"

WRITE = "--write" in sys.argv

# ── 1. set de tokens VIVOS (produção) ───────────────────────────────
used = set()
def scan(p):
    try:
        t = p.read_text(encoding="utf-8", errors="ignore")
    except Exception:
        return
    used.update(re.findall(r"[A-Za-z_][A-Za-z0-9_-]*", t))

for ext in ("*.tsx", "*.ts", "*.jsx"):
    for p in JS_DIR.rglob(ext):
        if "_cowork-bundle" in p.parts:
            continue
        scan(p)
if VIEWS_DIR.exists():
    for p in VIEWS_DIR.rglob("*.blade.php"):
        scan(p)

css = BUNDLE.read_text(encoding="utf-8")

# ── 2. scanner top-level: lista de items (raw, kind, header) ────────
def split_top_level(s):
    """Divide CSS em items top-level preservando texto bruto."""
    items, i, n = [], 0, len(s)
    while i < n:
        # consome whitespace/comentário como item "trivia"
        m = re.match(r"\s+|/\*.*?\*/", s[i:], re.S)
        if m:
            items.append(("trivia", s[i:i+m.end()], ""))
            i += m.end()
            continue
        # acha o próximo '{' ou ';' top-level
        j = i
        depth = 0
        header_end = None
        while j < n:
            c = s[j]
            if c == "/" and s[j:j+2] == "/*":
                k = s.find("*/", j+2)
                j = (k+2) if k != -1 else n
                continue
            if c == "{":
                header_end = j
                break
            if c == ";" and depth == 0:
                # at-rule sem bloco (ex: @import) — item até ;
                items.append(("stmt", s[i:j+1], s[i:j].strip()))
                header_end = None
                i = j+1
                break
            j += 1
        else:
            # resto sem bloco
            items.append(("trivia", s[i:], ""))
            break
        if header_end is None:
            continue
        # achar '}' que fecha este bloco (com balanceamento)
        k = header_end + 1
        d = 1
        while k < n and d > 0:
            c = s[k]
            if c == "/" and s[k:k+2] == "/*":
                e = s.find("*/", k+2); k = (e+2) if e != -1 else n; continue
            if c == "{": d += 1
            elif c == "}": d -= 1
            k += 1
        raw = s[i:k]
        header = s[i:header_end].strip()
        kind = "atrule" if header.lstrip().startswith("@") else "rule"
        items.append((kind, raw, header))
        i = k
    return items

CLASS_RE = re.compile(r"\.(-?[A-Za-z_][A-Za-z0-9_-]*)")

def rule_deletable(header):
    """SEGURO: removível só se TODA classe do seletor (todos os branches) for
    morta. Assim nenhuma classe viva pode perder sua última definição —
    mesmo que apareça num seletor composto junto de uma classe morta."""
    classes = [c for c in CLASS_RE.findall(header) if c != "fin-cowork"]
    if not classes:
        return False  # sem classe própria => mantém (ex: .fin-cowork, [data-*])
    return all(c not in used for c in classes)

def inner_body(raw, header):
    a = raw.find("{"); b = raw.rfind("}")
    return raw[a+1:b]

# ── 3. processa ────────────────────────────────────────────────────
out = []
removed_rules = 0
removed_lines = 0

def process_rule_list(s):
    """retorna (novo_css, n_removidas, linhas_removidas)"""
    global_removed = 0
    global_lines = 0
    pieces = []
    for kind, raw, header in split_top_level(s):
        if kind == "rule" and rule_deletable(header):
            global_removed += 1
            global_lines += raw.count("\n")
            continue
        pieces.append(raw)
    return "".join(pieces), global_removed, global_lines

for kind, raw, header in split_top_level(css):
    if kind == "atrule":
        h = header.lstrip()
        if h.startswith("@keyframes") or h.startswith("@font-face") or h.startswith("@import") or h.startswith("@charset"):
            out.append(raw); continue
        if h.startswith("@media") or h.startswith("@supports") or h.startswith("@container"):
            body = inner_body(raw, header)
            new_body, r, l = process_rule_list(body)
            removed_rules += r; removed_lines += l
            if new_body.strip() == "":
                # @media ficou vazio => remove
                removed_lines += raw.count("\n")
                continue
            out.append(f"{header} {{{new_body}}}")
            continue
        out.append(raw); continue
    if kind == "rule" and rule_deletable(header):
        removed_rules += 1
        removed_lines += raw.count("\n")
        continue
    out.append(raw)

new_css = "".join(out)
# colapsa 3+ linhas em branco
new_css = re.sub(r"\n{3,}", "\n\n", new_css)

orig_lines = len(css.splitlines())
new_lines = len(new_css.splitlines())

print(f"Regras removidas: {removed_rules}")
print(f"Linhas: {orig_lines} -> {new_lines}  (−{orig_lines - new_lines}, {100*(orig_lines-new_lines)//orig_lines}%)")

if WRITE:
    BUNDLE.write_text(new_css, encoding="utf-8")
    print(f"ESCRITO em {BUNDLE.relative_to(ROOT)}")
else:
    tmp = BUNDLE.with_suffix(".css.pruned")
    tmp.write_text(new_css, encoding="utf-8")
    print(f"DRY-RUN -> {tmp.relative_to(ROOT)} (rode com --write pra aplicar)")
