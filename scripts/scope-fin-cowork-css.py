"""
Prefix all top-level CSS selectors in the 5 Financeiro Cowork CSS files with
`.fin-cowork ` so the rules only apply inside Pages/Financeiro/**.

Espelho do scripts/scope-sells-cowork-css.py (mesma lógica, parser CSS robusto)
adaptado pros 5 arquivos do Financeiro.

Plano B canon — ver bloco de comentário em resources/css/inertia.css
linhas 32-71 (versão "DESATIVADO 2026-05-18" antes da reativação).

Preserves:
 - @import (kept at top, no prefix)
 - @keyframes / @-webkit-keyframes / @font-face (global by spec)
 - :root → .fin-cowork (vars scoped to wrapper)
 - @media / @supports / @container / @layer (prefix inner selectors recursivamente)
 - html / body / * / #app → .fin-cowork (wrapper acts as page body)

Usage: python scripts/scope-fin-cowork-css.py
       (idempotente — running twice does not double-prefix, marker SCOPED-OK)
"""
import re
import sys
from pathlib import Path

FILES = [
    # Onda 12.3 (2026-05-19) — bundle canon REAL inteiro (9054 LOC) copiado de
    # public/cowork-preview/styles.css conforme regra Tier 0
    # feedback-cowork-bundle-aplicar-inteiro.md (Wagner autorizou bundle copy
    # após 3 tentativas falhas de cherry-pick de classes Cowork sharedas).
    Path("resources/css/cowork-canon-financeiro-bundle.css"),
    # cowork-financeiro-bundle.css REMOVIDO 2026-06-02 (dedup — só o canon- vive; PACOTE-Q9 PR-2)
    Path("resources/css/fin-curadoria.css"),
    Path("resources/css/fin-ia.css"),
    Path("resources/css/fin-output.css"),
    Path("resources/css/fin-cowork.css"),
]
PREFIX = ".fin-cowork"
MARKER = "/* SCOPED-OK */"


def tokenize_blocks(s, start=0, end=None):
    """Yield (kind, selector, body, abs_start, abs_end) for each top-level block.
    kind in {'rule', 'at_block', 'at_simple', 'comment', 'text'}."""
    if end is None:
        end = len(s)
    i = start
    while i < end:
        while i < end and s[i].isspace():
            i += 1
        if i >= end:
            return
        if s[i : i + 2] == "/*":
            j = s.find("*/", i + 2)
            j = end if j == -1 else j + 2
            yield ("comment", "", s[i:j], i, j)
            i = j
            continue
        if s[i] == "@":
            j = i
            depth_paren = 0
            while j < end:
                c = s[j]
                if c == "(":
                    depth_paren += 1
                elif c == ")":
                    depth_paren -= 1
                elif depth_paren == 0 and c in "{;":
                    break
                j += 1
            if j >= end:
                yield ("text", "", s[i:end], i, end)
                return
            if s[j] == ";":
                yield ("at_simple", s[i : j + 1], "", i, j + 1)
                i = j + 1
                continue
            selector = s[i:j].rstrip()
            body_start = j + 1
            body_end = find_matching_brace(s, j)
            yield ("at_block", selector, s[body_start:body_end], i, body_end + 1)
            i = body_end + 1
            continue
        j = i
        depth_paren = 0
        while j < end:
            c = s[j]
            if c == "(":
                depth_paren += 1
            elif c == ")":
                depth_paren -= 1
            elif depth_paren == 0 and c == "{":
                break
            j += 1
        if j >= end:
            yield ("text", "", s[i:end], i, end)
            return
        selector = s[i:j].rstrip()
        body_start = j + 1
        body_end = find_matching_brace(s, j)
        yield ("rule", selector, s[body_start:body_end], i, body_end + 1)
        i = body_end + 1


def find_matching_brace(s, open_pos):
    depth = 1
    i = open_pos + 1
    n = len(s)
    while i < n:
        c = s[i]
        if c == "/" and i + 1 < n and s[i + 1] == "*":
            j = s.find("*/", i + 2)
            i = n if j == -1 else j + 2
            continue
        if c == '"' or c == "'":
            q = c
            i += 1
            while i < n and s[i] != q:
                if s[i] == "\\":
                    i += 2
                else:
                    i += 1
            i += 1
            continue
        if c == "{":
            depth += 1
        elif c == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return n - 1


def prefix_selector(sel):
    """Prefix each comma-separated selector with `.fin-cowork `.
    :root → .fin-cowork (var scope shifts to wrapper, perfect).
    html, body, *, #app → .fin-cowork (page wrapper acts as body)."""
    parts = []
    depth = 0
    cur = ""
    for ch in sel:
        if ch == "(":
            depth += 1
            cur += ch
        elif ch == ")":
            depth -= 1
            cur += ch
        elif ch == "," and depth == 0:
            parts.append(cur)
            cur = ""
        else:
            cur += ch
    parts.append(cur)
    out = []
    for p in parts:
        p = p.strip()
        if not p:
            continue
        if p == ":root":
            out.append(PREFIX)
            continue
        if p in ("body", "html", "*", "#app", "html, body", "html body"):
            out.append(PREFIX)
            continue
        if p.startswith(PREFIX):
            out.append(p)
            continue
        out.append(f"{PREFIX} {p}")
    return ", ".join(out)


def process(s):
    out_parts = []
    for kind, sel, body, _a, _b in tokenize_blocks(s):
        if kind == "comment":
            out_parts.append(body)
        elif kind == "at_simple":
            out_parts.append(sel)
        elif kind == "at_block":
            head = sel.split(None, 1)[0]
            if head in ("@keyframes", "@-webkit-keyframes", "@font-face"):
                out_parts.append(f"{sel} {{{body}}}")
            elif head in ("@media", "@supports", "@container", "@layer"):
                inner = process(body)
                out_parts.append(f"{sel} {{\n{inner}\n}}")
            else:
                out_parts.append(f"{sel} {{{body}}}")
        elif kind == "rule":
            new_sel = prefix_selector(sel)
            out_parts.append(f"{new_sel} {{{body}}}")
        else:
            out_parts.append(body)
    return "\n".join(out_parts)


def scope_file(path: Path) -> str:
    if not path.exists():
        return f"SKIP — não encontrado: {path}"
    src = path.read_text(encoding="utf-8")
    first_lines = src.splitlines()[0:5]
    if any(MARKER in line for line in first_lines):
        return f"SKIP — já escopado: {path}"
    banner = MARKER + f" - escopo .fin-cowork aplicado por scripts/scope-fin-cowork-css.py\n"
    output = banner + process(src)
    path.write_text(output, encoding="utf-8")
    return f"OK — {path} reescrito com {len(output.splitlines())} linhas."


if __name__ == "__main__":
    for f in FILES:
        print(scope_file(f))
