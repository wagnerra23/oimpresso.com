#!/usr/bin/env python3
"""Process files in cowork-inbox/ — move/append to whitelisted paths, then delete from inbox.

Triggered by .github/workflows/cowork-inbox.yml on push to main touching cowork-inbox/**.

Header syntax (HTML/markdown comment, anywhere in file):
    <!-- cowork: target: <path> -->        # write/overwrite at <path>
    <!-- cowork: append-to: <path> -->     # append (after newline) to <path>
    <!-- cowork: commit: <message> -->     # optional commit message override

Whitelist enforced — paths must start with one of ALLOWED_PREFIXES and must not contain '..'.
"""
import re
import subprocess
import sys
from pathlib import Path

INBOX = Path("cowork-inbox")
ALLOWED_PREFIXES = ("prototipo-ui/", "memory/", "docs/")
DENY_SUBSTRINGS = ("..", ".github/", ".claude/")
MAX_SIZE_BYTES = 1_000_000
SKIP_FILES = {"README.md", ".gitkeep"}

HEADER_RE = re.compile(r"<!--\s*cowork:\s*([\w-]+):\s*(.+?)\s*-->")


def parse_headers(content: str) -> dict[str, str]:
    return {m.group(1): m.group(2) for m in HEADER_RE.finditer(content)}


def strip_headers(content: str) -> str:
    return HEADER_RE.sub("", content).lstrip("\n")


def validate_path(path: str) -> tuple[bool, str | None]:
    if any(s in path for s in DENY_SUBSTRINGS):
        return False, f"denied substring in {path!r}"
    if not any(path.startswith(p) for p in ALLOWED_PREFIXES):
        return False, f"path {path!r} not in whitelist {ALLOWED_PREFIXES}"
    return True, None


def process_file(filepath: Path) -> str:
    size = filepath.stat().st_size
    if size > MAX_SIZE_BYTES:
        return f"SKIP {filepath} (size {size} > {MAX_SIZE_BYTES})"

    content = filepath.read_text(encoding="utf-8")
    headers = parse_headers(content)
    body = strip_headers(content)

    target = headers.get("target")
    append_to = headers.get("append-to")

    if target and append_to:
        return f"SKIP {filepath} (both target and append-to set)"
    if not target and not append_to:
        return f"SKIP {filepath} (no target/append-to header)"

    dest = target or append_to
    ok, err = validate_path(dest)
    if not ok:
        return f"SKIP {filepath} ({err})"

    dest_path = Path(dest)
    dest_path.parent.mkdir(parents=True, exist_ok=True)

    if target:
        dest_path.write_text(body, encoding="utf-8")
        action = "WRITE"
    else:
        existing = dest_path.read_text(encoding="utf-8") if dest_path.exists() else ""
        sep = "\n" if existing and not existing.endswith("\n") else ""
        with dest_path.open("a", encoding="utf-8") as f:
            f.write(sep + body)
        action = "APPEND"

    filepath.unlink()
    return f"{action} {filepath} -> {dest}"


def main() -> int:
    if not INBOX.exists():
        print("cowork-inbox/ does not exist; nothing to do")
        return 0

    files = sorted(
        f for f in INBOX.iterdir() if f.is_file() and f.name not in SKIP_FILES
    )
    if not files:
        print("inbox empty; nothing to do")
        return 0

    print(f"Found {len(files)} file(s) in inbox")
    for f in files:
        try:
            result = process_file(f)
            print(f"  {result}")
        except Exception as e:
            print(f"  ERROR processing {f}: {e}", file=sys.stderr)
            return 1

    return 0


if __name__ == "__main__":
    sys.exit(main())
