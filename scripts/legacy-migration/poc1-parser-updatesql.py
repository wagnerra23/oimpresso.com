"""
POC 1 — Parser do UpdateSQL.txt do Delphi WR Comercial.

Parseia o changelog DDL incremental (blocos `UPDATE N;` ... próximo `UPDATE N+1;`)
e gera JSON com {versao: [linhas_DDL]}. Equivalente a `migrate:status` do Laravel
mas pra schema Firebird do legacy WR Comercial.

Critério de aceite:
- Versão mín deve ser 6 (primeira no .txt do Wagner, 2026-05-09)
- Versão máx deve ser ~1468 + stub 1999
- Total de versões válidas (excl. 1999) deve ser ~1463

Uso:
    python poc1-parser-updatesql.py
    python poc1-parser-updatesql.py --updatesql-path "D:/outro/UpdateSQL.txt"
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from pathlib import Path

# Força stdout/stderr UTF-8 — Windows console default é cp1252 e crasha em emojis
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass  # python-dotenv opcional; defaults via env funcionam

UPDATE_HEADER_RE = re.compile(r"^UPDATE\s+(\d+)\s*;\s*(--.*)?$", re.IGNORECASE)


def parse_updatesql(path: Path) -> dict[int, list[str]]:
    """Lê UpdateSQL.txt e retorna {versao: [linhas_DDL]}.

    Encoding: tenta utf-8-sig primeiro (tira BOM), cai pra cp1252 (Windows-1252)
    que é o default do Delphi RAD Studio em PT-BR.
    """
    raw: str
    for encoding in ("utf-8-sig", "utf-8", "cp1252", "latin-1"):
        try:
            raw = path.read_text(encoding=encoding)
            break
        except UnicodeDecodeError:
            continue
    else:
        raise RuntimeError(f"Não consegui decodificar {path} em utf-8/cp1252/latin-1")

    blocks: dict[int, list[str]] = {}
    current_version: int | None = None
    current_lines: list[str] = []

    for line in raw.splitlines():
        m = UPDATE_HEADER_RE.match(line)
        if m:
            # Fecha bloco anterior
            if current_version is not None:
                blocks[current_version] = current_lines
            current_version = int(m.group(1))
            current_lines = []
        elif current_version is not None:
            # Linha pertence ao bloco corrente. Mantém a linha bruta;
            # análise sintática DDL fica pra fase 3.
            current_lines.append(line)

    # Fecha último bloco
    if current_version is not None:
        blocks[current_version] = current_lines

    return blocks


def summarize(blocks: dict[int, list[str]]) -> None:
    """Imprime resumo legível."""
    if not blocks:
        print("⚠️  Nenhum bloco UPDATE N; encontrado — arquivo vazio ou formato inesperado")
        return

    versions = sorted(blocks.keys())
    print(f"\n=== Resumo do parse ===")
    print(f"Total de blocos UPDATE N;     : {len(versions)}")
    print(f"Versão mínima                 : {versions[0]}")
    print(f"Versão máxima                 : {versions[-1]}")
    print(f"Linhas totais (todos blocos)  : {sum(len(v) for v in blocks.values())}")
    # Conta DDL statements (heurística: linhas com palavras-chave SQL)
    ddl_keywords = ("CREATE ", "ALTER ", "DROP ", "INSERT ", "UPDATE ", "EXECUTE ")
    ddl_count = sum(
        1
        for lines in blocks.values()
        for line in lines
        if any(line.upper().lstrip().startswith(kw) for kw in ddl_keywords)
    )
    print(f"Linhas DDL detectadas         : ~{ddl_count}")

    print("\n=== Primeiros 5 blocos ===")
    for v in versions[:5]:
        non_empty = [l for l in blocks[v] if l.strip()]
        print(f"  UPDATE {v:>4}; → {len(non_empty)} linhas não-vazias")

    print("\n=== Últimos 5 blocos ===")
    for v in versions[-5:]:
        non_empty = [l for l in blocks[v] if l.strip()]
        print(f"  UPDATE {v:>4}; → {len(non_empty)} linhas não-vazias")

    # Validação de gaps na sequência
    expected = set(range(versions[0], versions[-1] + 1))
    missing = expected - set(versions)
    if missing:
        print(f"\n⚠️  Versões faltantes na sequência: {sorted(missing)[:20]}{'...' if len(missing) > 20 else ''}")
    else:
        print(f"\n✅ Sequência {versions[0]}..{versions[-1]} sem gaps")


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--updatesql-path",
        default=os.environ.get(
            "UPDATESQL_PATH", "D:/Programas/WR Comercial/Resources/UpdateSQL.txt"
        ),
        help="Caminho pro UpdateSQL.txt (default via env UPDATESQL_PATH)",
    )
    parser.add_argument(
        "--output-dir",
        default=os.environ.get("OUTPUT_DIR", "output"),
        help="Pasta de output (default ./output)",
    )
    args = parser.parse_args()

    path = Path(args.updatesql_path)
    if not path.is_file():
        print(f"❌ Arquivo não encontrado: {path}", file=sys.stderr)
        return 1

    print(f"📖 Lendo {path}")
    print(f"   Tamanho: {path.stat().st_size:,} bytes")

    blocks = parse_updatesql(path)
    summarize(blocks)

    # Salva JSON
    out_dir = Path(args.output_dir)
    out_dir.mkdir(parents=True, exist_ok=True)
    out_path = out_dir / "updatesql-parsed.json"
    with out_path.open("w", encoding="utf-8") as f:
        json.dump(
            {str(k): v for k, v in blocks.items()},
            f,
            ensure_ascii=False,
            indent=2,
        )
    print(f"\n💾 Salvo: {out_path} ({out_path.stat().st_size:,} bytes)")

    return 0


if __name__ == "__main__":
    sys.exit(main())
