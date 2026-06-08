#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
import_claude_export.py
=======================

Importa conversas do export oficial de claude.ai (Settings → Privacy →
"Export data" → email com ZIP) e gera arquivos de evidência markdown
em `_Ideias/{Modulo}/evidencias/` com frontmatter padrão.

Vantagens vs scrape Playwright
------------------------------
- Zero browser, zero login, zero token IA.
- Conteúdo oficial direto do Anthropic — não pode ficar incompleto.
- Inclui `created_at` por mensagem (timestamps preservados).
- Roda offline depois do download.

Limitações conhecidas
---------------------
- **Artifacts não vêm**: o JSON tem só o `text` do chat, não o conteúdo
  de artifacts gerados em runtime. Pra esses, abrir conversa manual.
- **Imagens/anexos não vêm**: só metadados em `attachments`/`files`.

Uso
---
    python import_claude_export.py \\
        D:/path/to/data-XXX-batch-0000.zip \\
        conversas-pendentes.tsv

O TSV usa o mesmo formato do scraper Playwright:
    https://claude.ai/chat/<UUID>\\t<output_path>

Linhas em branco e iniciadas com # são ignoradas. URLs com UUID que não
estiver no ZIP geram warning e seguem.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import zipfile
from dataclasses import dataclass
from datetime import date, datetime
from pathlib import Path


UUID_FROM_URL_RE = re.compile(r"chat/([0-9a-f-]{36})", re.IGNORECASE)


@dataclass
class Job:
    url: str
    uuid: str
    output: Path


def parse_jobs(tsv_path: Path) -> list[Job]:
    jobs: list[Job] = []
    for raw in tsv_path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        parts = re.split(r"\t+| {2,}", line)
        if len(parts) < 2:
            print(f"[WARN] linha sem path: {line!r}")
            continue
        url = parts[0].strip()
        out = Path(parts[1].strip())
        m = UUID_FROM_URL_RE.search(url)
        if not m:
            print(f"[WARN] UUID não extraído de: {url!r}")
            continue
        jobs.append(Job(url=url, uuid=m.group(1).lower(), output=out))
    return jobs


def fmt_dt(iso: str) -> str:
    """ISO 8601 -> 'YYYY-MM-DD HH:MM' (sem timezone)."""
    try:
        # Python 3.11+ aceita 'Z'
        dt = datetime.fromisoformat(iso.replace("Z", "+00:00"))
        return dt.strftime("%Y-%m-%d %H:%M")
    except Exception:
        return iso[:16].replace("T", " ")


def conversation_to_markdown(conv: dict, url: str) -> str:
    """Converte uma conversa do export pro markdown de evidência."""
    name = (conv.get("name") or "").strip() or "(sem título)"
    safe_title = name.replace('"', '\\"')
    today = date.today().isoformat()
    created = conv.get("created_at", "")[:19]
    updated = conv.get("updated_at", "")[:19]
    msgs = conv.get("chat_messages") or []

    front = (
        "---\n"
        "type: evidencia\n"
        f"origin_url: {url}\n"
        f'origin_title: "{safe_title}"\n'
        f"extracted_at: {today}\n"
        "extraction_method: claude.ai data export (conversations.json)\n"
        f"created_at: {created}\n"
        f"updated_at: {updated}\n"
        f"message_count: {len(msgs)}\n"
        "---\n\n"
    )

    body_parts = [f"# {name}\n", f"**URL:** {url}\n"]
    for i, m in enumerate(msgs, 1):
        sender = (m.get("sender") or "").lower()
        label = "Wagner" if sender == "human" else "Claude"
        when = fmt_dt(m.get("created_at", ""))
        text = (m.get("text") or "").rstrip()
        body_parts.append(f"\n## {i}. {label} — {when}\n\n{text}\n")

        # Anexos (metadados)
        atts = m.get("attachments") or []
        files = m.get("files") or []
        if atts or files:
            body_parts.append("\n_Anexos:_\n")
            for a in atts:
                fname = a.get("file_name") or a.get("filename") or "(arquivo)"
                size = a.get("file_size") or a.get("size") or "?"
                body_parts.append(f"- {fname} ({size} bytes)\n")
            for f in files:
                fname = f.get("file_name") or f.get("filename") or "(arquivo)"
                body_parts.append(f"- {fname}\n")

    return front + "\n".join(body_parts) + "\n"


def load_export(zip_path: Path) -> dict[str, dict]:
    """Lê conversations.json do ZIP e retorna dict uuid->conversa."""
    with zipfile.ZipFile(zip_path) as z:
        with z.open("conversations.json") as f:
            data = json.load(f)
    if not isinstance(data, list):
        raise SystemExit(f"[ERRO] conversations.json não é lista (tipo={type(data).__name__})")
    return {c["uuid"]: c for c in data if "uuid" in c}


def main() -> int:
    ap = argparse.ArgumentParser(description="Importa export claude.ai -> markdown evidence")
    ap.add_argument("zip", help="Caminho do ZIP do export (data-*.zip)")
    ap.add_argument("batch", help="TSV com 'url<TAB>output_path'")
    ap.add_argument("--overwrite", action="store_true", help="Sobrescreve evidências existentes")
    args = ap.parse_args()

    zip_path = Path(args.zip)
    if not zip_path.exists():
        print(f"[ERRO] ZIP não encontrado: {zip_path}")
        return 2

    jobs = parse_jobs(Path(args.batch))
    if not jobs:
        print("[ERRO] batch vazio")
        return 2

    print(f"[*] Lendo export: {zip_path.name}")
    by_uuid = load_export(zip_path)
    print(f"    {len(by_uuid)} conversas no ZIP")

    rc = 0
    for job in jobs:
        conv = by_uuid.get(job.uuid)
        if not conv:
            print(f"[MISS] UUID {job.uuid} não está no ZIP -> {job.output}")
            rc = 1
            continue
        if job.output.exists() and not args.overwrite:
            print(f"[SKIP] já existe: {job.output} (use --overwrite pra refazer)")
            continue

        md = conversation_to_markdown(conv, job.url)
        job.output.parent.mkdir(parents=True, exist_ok=True)
        job.output.write_text(md, encoding="utf-8")
        msgs = len(conv.get("chat_messages") or [])
        print(f"[OK] {job.uuid}  msgs={msgs:>3}  -> {job.output}")

    return rc


if __name__ == "__main__":
    sys.exit(main())
