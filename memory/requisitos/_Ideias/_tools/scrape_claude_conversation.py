#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
scrape_claude_conversation.py
=============================

Extrai conteúdo de uma conversa em claude.ai e salva como markdown com
frontmatter de evidência (origin_url, origin_title, extracted_at).

Estratégia
----------
- Usa Playwright (chromium) com `user_data_dir` dedicado em
  `~/.claude-scraper-profile`. Na PRIMEIRA execução o usuário loga
  manualmente em claude.ai (SSO Google). Depois disso o cookie fica
  persistido nesse profile e o scraper reusa em todas as execuções
  seguintes — sem precisar logar de novo.
- Extrai os turnos da conversa via seletores de DOM, agrupando como
  "Wagner / Claude" pra preservar contexto.
- Saída em markdown com frontmatter padrão de evidência usado em
  `memory/requisitos/_Ideias/{Modulo}/evidencias/`.

Uso
---
1) Instale dependências (uma vez):
       pip install playwright
       playwright install chromium

2) Single:
       python scrape_claude_conversation.py \
           https://claude.ai/chat/<UUID> \
           D:/oimpresso.com/memory/requisitos/_Ideias/<Modulo>/evidencias/conversa-claude-2026-04-mobile.md

3) Batch (arquivo .tsv com colunas: url<TAB>output_path):
       python scrape_claude_conversation.py --batch lista.tsv

   Exemplo de lista.tsv (linha em branco e # são ignorados):
       https://claude.ai/chat/<UUID-1>\tD:/oimpresso.com/memory/.../evidencias/A.md
       https://claude.ai/chat/<UUID-2>\tD:/oimpresso.com/memory/.../evidencias/B.md

Notas
-----
- Headless desativado por padrão pra você ver o que está acontecendo.
  Adicione `--headless` pra rodar invisível depois de validar.
- Se claude.ai pedir captcha/checkpoint, ele aparece na janela e você
  resolve manualmente — depois aperte Enter no console pra continuar.
"""

from __future__ import annotations

import argparse
import re
import sys
from dataclasses import dataclass
from datetime import date
from pathlib import Path
from typing import Iterable

try:
    from playwright.sync_api import sync_playwright, Page, TimeoutError as PWTimeout
except ImportError:
    print("[ERRO] Playwright não instalado. Rode:\n  pip install playwright && playwright install chromium")
    sys.exit(2)


PROFILE_DIR = Path.home() / ".claude-scraper-profile"
DEFAULT_TIMEOUT_MS = 60_000
RENDER_WAIT_MS = 2_500  # tempo extra após networkidle pra React montar tudo


@dataclass
class Turn:
    author: str  # "human" | "assistant"
    text: str


@dataclass
class Conversation:
    url: str
    title: str
    turns: list[Turn]

    def to_markdown(self) -> str:
        today = date.today().isoformat()
        # escapa aspas no título
        safe_title = self.title.replace('"', '\\"')
        front = (
            "---\n"
            "type: evidencia\n"
            f"origin_url: {self.url}\n"
            f'origin_title: "{safe_title}"\n'
            f"extracted_at: {today}\n"
            "extraction_method: playwright + chromium profile autenticado\n"
            "---\n\n"
        )
        body = [f"# {self.title}\n", f"**URL:** {self.url}\n"]
        for i, turn in enumerate(self.turns, 1):
            label = "Wagner" if turn.author == "human" else "Claude"
            body.append(f"\n## {i}. {label}\n\n{turn.text.strip()}\n")
        return front + "\n".join(body) + "\n"


# --- DOM extraction --------------------------------------------------------

# Seletores tentados em ordem (claude.ai muda de tempos em tempos).
# Cada par é (seletor, função pra deduzir autor a partir do nó).
TURN_SELECTORS = [
    # Layout atual (~2026-04): mensagens com data-testid
    'div[data-testid^="message-"]',
    # Fallback: classes conhecidas
    'div.font-claude-message, div.font-user-message',
    # Fallback genérico: blocos de chat
    'div[class*="conversation-turn"]',
]


def _detect_author(handle) -> str:
    """Heurística pra autor: 'human' (Wagner) ou 'assistant' (Claude)."""
    # 1) data-author
    attr = handle.get_attribute("data-author") or ""
    if "human" in attr.lower() or "user" in attr.lower():
        return "human"
    if "assistant" in attr.lower() or "claude" in attr.lower():
        return "assistant"

    # 2) classe
    cls = handle.get_attribute("class") or ""
    if "user-message" in cls:
        return "human"
    if "claude-message" in cls or "assistant-message" in cls:
        return "assistant"

    # 3) data-testid
    tid = handle.get_attribute("data-testid") or ""
    if "user" in tid.lower():
        return "human"
    if "assistant" in tid.lower():
        return "assistant"

    # 4) presença de avatar/nome no subtree
    try:
        if handle.locator("text=/Wagner|Você|You/").count() > 0:
            return "human"
    except Exception:
        pass

    return "assistant"  # default conservador


def extract_turns(page: Page) -> list[Turn]:
    for sel in TURN_SELECTORS:
        nodes = page.locator(sel)
        count = nodes.count()
        if count > 0:
            turns: list[Turn] = []
            for i in range(count):
                h = nodes.nth(i)
                text = h.inner_text().strip()
                if not text:
                    continue
                turns.append(Turn(author=_detect_author(h), text=text))
            if turns:
                return turns

    # Fallback bruto: pega texto do <main>
    main_text = page.locator("main").first.inner_text()
    return [Turn(author="assistant", text=main_text)]


def scroll_to_bottom(page: Page, max_scrolls: int = 30) -> None:
    """Rola o scroller principal até o fim pra forçar render lazy."""
    for _ in range(max_scrolls):
        prev = page.evaluate("document.documentElement.scrollHeight")
        page.evaluate("window.scrollTo(0, document.documentElement.scrollHeight)")
        page.wait_for_timeout(400)
        cur = page.evaluate("document.documentElement.scrollHeight")
        if cur == prev:
            break


def scrape_one(page: Page, url: str) -> Conversation:
    print(f"[*] Navegando: {url}", flush=True)
    # claude.ai nunca atinge networkidle (polling constante). Usar domcontentloaded.
    page.goto(url, wait_until="domcontentloaded", timeout=DEFAULT_TIMEOUT_MS)
    # Espera mensagens renderizarem (ou timeout maior se for primeira carga)
    try:
        page.wait_for_selector(
            'div[data-testid^="message-"], div.font-claude-message, div.font-user-message',
            timeout=30_000,
        )
    except PWTimeout:
        print("    [warn] selector de mensagem não apareceu em 30s, continuando", flush=True)
    page.wait_for_timeout(RENDER_WAIT_MS)

    # Garante render completo (incluindo conteúdo lazy)
    scroll_to_bottom(page)

    title = page.title().replace(" - Claude", "").replace("Claude", "").strip(" -·")
    turns = extract_turns(page)
    print(f"    -> {len(turns)} turno(s) extraído(s)")
    return Conversation(url=url, title=title or "(sem título)", turns=turns)


# --- Batch driver ----------------------------------------------------------

def parse_batch(path: Path) -> list[tuple[str, Path]]:
    pairs: list[tuple[str, Path]] = []
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        # aceita TAB ou vários espaços
        parts = re.split(r"\t+| {2,}", line)
        if len(parts) < 2:
            print(f"[WARN] Linha ignorada (sem path): {line!r}")
            continue
        pairs.append((parts[0].strip(), Path(parts[1].strip())))
    return pairs


def run(jobs: Iterable[tuple[str, Path]], headless: bool = False) -> int:
    PROFILE_DIR.mkdir(parents=True, exist_ok=True)
    rc = 0
    with sync_playwright() as p:
        ctx = p.chromium.launch_persistent_context(
            user_data_dir=str(PROFILE_DIR),
            headless=headless,
            viewport={"width": 1280, "height": 900},
            locale="pt-BR",
        )
        # Reusa página existente se houver
        page = ctx.pages[0] if ctx.pages else ctx.new_page()

        # Primeira navegação: garante login.
        # claude.ai redireciona /login -> /login?... pra usuário não autenticado,
        # ou /new pra autenticado. Polling até sair de qualquer URL de /login/*.
        page.goto("https://claude.ai/", wait_until="domcontentloaded")
        page.wait_for_timeout(2_000)

        def is_logged_in() -> bool:
            url = page.url
            if "/login" in url:
                return False
            if "claude.ai" not in url:
                return False
            # Verifica presença de elemento da app (sidebar/avatar/textarea)
            try:
                return page.locator(
                    'nav, [data-testid="chat-input"], textarea[placeholder*="Reply"], textarea'
                ).first.is_visible(timeout=1_000)
            except Exception:
                return False

        if not is_logged_in():
            print("[!] Faça login em claude.ai na janela aberta (timeout 5min). Detecto automaticamente.", flush=True)
            deadline_s = 300
            for elapsed in range(0, deadline_s, 3):
                page.wait_for_timeout(3_000)
                if is_logged_in():
                    print(f"    -> login detectado após {elapsed+3}s", flush=True)
                    break
            else:
                print("[ERRO] timeout no login. Abortando.", flush=True)
                ctx.close()
                return 2

        for url, out_path in jobs:
            try:
                conv = scrape_one(page, url)
                out_path.parent.mkdir(parents=True, exist_ok=True)
                out_path.write_text(conv.to_markdown(), encoding="utf-8")
                print(f"[OK] -> {out_path}")
            except PWTimeout as e:
                print(f"[ERRO] timeout em {url}: {e}")
                rc = 1
            except Exception as e:
                print(f"[ERRO] {url}: {e}")
                rc = 1

        ctx.close()
    return rc


def main() -> int:
    ap = argparse.ArgumentParser(description="Scrape claude.ai conversation -> markdown evidence")
    ap.add_argument("url", nargs="?", help="URL da conversa claude.ai (modo single)")
    ap.add_argument("output", nargs="?", help="Caminho do .md de saída (modo single)")
    ap.add_argument("--batch", help="Arquivo .tsv com pares 'url<TAB>output_path'")
    ap.add_argument("--headless", action="store_true", help="Roda sem janela (após validar)")
    args = ap.parse_args()

    if args.batch:
        jobs = parse_batch(Path(args.batch))
        if not jobs:
            print("[ERRO] batch vazio")
            return 2
        return run(jobs, headless=args.headless)

    if not args.url or not args.output:
        ap.print_help()
        return 2

    return run([(args.url, Path(args.output))], headless=args.headless)


if __name__ == "__main__":
    sys.exit(main())
