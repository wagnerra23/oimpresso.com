"""
Prefix all top-level CSS selectors in resources/css/sells-cowork.css with
`.sells-cowork ` so the rules only apply inside the Sells/Index page.

Preserves:
 - @import (kept at top, no prefix)
 - @keyframes (keyframes are global by spec; no prefix needed)
 - @font-face (global)
 - :root (CSS vars — only valid at top-level; we wrap inside .sells-cowork to scope vars)
 - @media (prefix inner selectors)
 - @supports (prefix inner selectors)

Usage: python scripts/scope-sells-cowork-css.py
       (idempotent — running twice does not double-prefix)
"""
import re
import sys
from pathlib import Path

PATH = Path("resources/css/sells-cowork.css")
PREFIX = ".sells-cowork"
MARKER = "/* SCOPED-OK */"

src = PATH.read_text(encoding="utf-8")
if MARKER in src.splitlines()[0:5]:
    print("Already scoped; skipping.")
    sys.exit(0)


def tokenize_blocks(s, start=0, end=None):
    """Yield (kind, selector, body, abs_start, abs_end) for each top-level block.
    kind in {'rule', 'at_block', 'at_simple'}."""
    if end is None:
        end = len(s)
    i = start
    while i < end:
        # skip whitespace + comments
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
        # at-rules
        if s[i] == "@":
            # find first { or ;
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
            # block @rule
            selector = s[i:j].rstrip()
            body_start = j + 1
            body_end = find_matching_brace(s, j)
            yield ("at_block", selector, s[body_start:body_end], i, body_end + 1)
            i = body_end + 1
            continue
        # regular rule
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
    """Prefix each comma-separated selector with `.sells-cowork `.
    :root → .sells-cowork (var scope shifts to wrapper, perfect).
    html, body, * → .sells-cowork (page wrapper acts as body)."""
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
        # :root mapping → just .sells-cowork (vars scoped to wrapper)
        if p == ":root":
            out.append(PREFIX)
            continue
        # body / html / * → .sells-cowork (wrapper acts as page body)
        if p in ("body", "html", "*", "html, body", "html body"):
            out.append(PREFIX)
            continue
        # already prefixed (idempotency)
        if p.startswith(PREFIX):
            out.append(p)
            continue
        # descendant prefix
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
                # global by spec — keep as-is
                out_parts.append(f"{sel} {{{body}}}")
            elif head in ("@media", "@supports", "@container", "@layer"):
                # recurse into inner rules
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


banner = MARKER + " - escopo .sells-cowork aplicado por scripts/scope-sells-cowork-css.py\n"
output = banner + process(src)
PATH.write_text(output, encoding="utf-8")
print(f"OK — escrito {PATH} com {len(output.splitlines())} linhas.")
