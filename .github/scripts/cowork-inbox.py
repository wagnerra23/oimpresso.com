#!/usr/bin/env python3
"""Process files in cowork-inbox/ — move/append to whitelisted paths, then delete from inbox.

Triggered by .github/workflows/cowork-inbox.yml on push to main touching cowork-inbox/**.

Header syntax (HTML/markdown comment, anywhere in file):
    <!-- cowork: target: <path> -->        # write/overwrite at <path>
    <!-- cowork: append-to: <path> -->     # append (after newline) to <path>
    <!-- cowork: commit: <message> -->     # reserved; not yet wired (workflow hardcodes the message)

Whitelist enforced — paths must start with an allowed prefix and must not contain a denied substring.

Two tiers (Onda D):
    ALLOWED_PREFIXES        -> "auto":   doc/memory/prototype, fast-path (--auto merge once CI is green).
    ALLOWED_PREFIXES_REVIEW -> "review": code (resources/js/**), PR opened for human review — NEVER auto-merged.
Anything else, or any denied substring, is SKIPPED.

If any processed file is "review" tier, this script writes `review_required=true` to $GITHUB_OUTPUT
so the workflow opens the PR for review instead of auto-merging.
"""
import os
import re
import sys
from pathlib import Path

INBOX = Path("cowork-inbox")
ALLOWED_PREFIXES = ("prototipo-ui/", "memory/", "docs/")       # auto-merge once green
ALLOWED_PREFIXES_REVIEW = ("resources/js/",)                   # code -> human review, never auto-merge
DENY_SUBSTRINGS = ("..", ".github/", ".claude/")               # never reachable, even via review tier
MAX_SIZE_BYTES = 1_000_000
SKIP_FILES = {"README.md", ".gitkeep"}

HEADER_RE = re.compile(r"<!--\s*cowork:\s*([\w-]+):\s*(.+?)\s*-->")


def parse_headers(content: str) -> dict[str, str]:
    return {m.group(1): m.group(2) for m in HEADER_RE.finditer(content)}


def strip_headers(content: str) -> str:
    return HEADER_RE.sub("", content).lstrip("\n")


def classify_path(path: str) -> tuple[str | None, str | None]:
    """Return (tier, error). tier is 'auto', 'review', or None when blocked."""
    if any(s in path for s in DENY_SUBSTRINGS):
        return None, f"denied substring in {path!r}"
    if any(path.startswith(p) for p in ALLOWED_PREFIXES):
        return "auto", None
    if any(path.startswith(p) for p in ALLOWED_PREFIXES_REVIEW):
        return "review", None
    return None, f"path {path!r} not in whitelist {ALLOWED_PREFIXES + ALLOWED_PREFIXES_REVIEW}"


def process_file(filepath: Path) -> tuple[str, str | None]:
    """Return (log_message, tier). tier is the written file's tier, or None when skipped."""
    size = filepath.stat().st_size
    if size > MAX_SIZE_BYTES:
        return f"SKIP {filepath} (size {size} > {MAX_SIZE_BYTES})", None

    content = filepath.read_text(encoding="utf-8")
    headers = parse_headers(content)
    body = strip_headers(content)

    target = headers.get("target")
    append_to = headers.get("append-to")

    if target and append_to:
        return f"SKIP {filepath} (both target and append-to set)", None
    if not target and not append_to:
        return f"SKIP {filepath} (no target/append-to header)", None

    dest = target or append_to
    tier, err = classify_path(dest)
    if tier is None:
        return f"SKIP {filepath} ({err})", None

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
    return f"{action} [{tier}] {filepath} -> {dest}", tier


def emit_review_flag(review_required: bool) -> None:
    value = "true" if review_required else "false"
    gh_output = os.environ.get("GITHUB_OUTPUT")
    if gh_output:
        with open(gh_output, "a", encoding="utf-8") as fh:
            fh.write(f"review_required={value}\n")
    print(f"review_required={value}")


def main() -> int:
    if not INBOX.exists():
        print("cowork-inbox/ does not exist; nothing to do")
        emit_review_flag(False)
        return 0

    files = sorted(
        f for f in INBOX.iterdir() if f.is_file() and f.name not in SKIP_FILES
    )
    if not files:
        print("inbox empty; nothing to do")
        emit_review_flag(False)
        return 0

    print(f"Found {len(files)} file(s) in inbox")
    tiers: list[str] = []
    for f in files:
        try:
            result, tier = process_file(f)
            print(f"  {result}")
            if tier is not None:
                tiers.append(tier)
        except Exception as e:
            print(f"  ERROR processing {f}: {e}", file=sys.stderr)
            return 1

    emit_review_flag(any(t == "review" for t in tiers))
    return 0


if __name__ == "__main__":
    sys.exit(main())
