"""
Fase 3 — Gera schema baseline reconstruído do legacy WR Comercial.

Pipeline:
  1. Lê UpdateSQL.txt (ou JSON parseado da POC 1)
  2. Aplica blocos UPDATE N; sequencialmente (1..target_version) via DDL parser
  3. Classifica tabelas em módulos por prefixo
  4. Gera 1 markdown por tabela em memory/dominios/wr-comercial/modulos/<dom>/tabelas/<TABELA>.md
  5. Gera _index.md por módulo + sumário global

Output 100% auto-gerado (não editar manualmente — re-rode script). Wagner pode
adicionar contexto humano em arquivos `_notes.md` separados ao lado.

Uso:
    python generate-baseline.py
    python generate-baseline.py --target-version 1466 --dry-run
    python generate-baseline.py --updatesql-path "D:/.../UpdateSQL.txt"
"""

from __future__ import annotations

import argparse
import os
import re
import sys
from datetime import date
from pathlib import Path

# Força UTF-8 stdout no Windows
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

# Adiciona lib/ ao path
sys.path.insert(0, str(Path(__file__).parent))

from lib.ddl_parser import (  # noqa: E402
    SchemaState,
    Table,
    UPDATE_HEADER_RE as DDL_UPDATE_HEADER_RE,  # não usar — temos próprio abaixo
)
from lib.fk_resolver import infer_fks_for_table  # noqa: E402
from lib.module_classifier import classify_all, classify_table  # noqa: E402


# Re-implementação local porque o lib usa formato sem ;
UPDATE_HEADER_RE = re.compile(r"^UPDATE\s+(\d+)\s*;\s*(--.*)?$", re.IGNORECASE)


def parse_blocks(path: Path) -> dict[int, list[str]]:
    """Parser idêntico ao POC 1 — retorna {versao: [linhas_DDL]}."""
    raw: str = ""
    for encoding in ("utf-8-sig", "utf-8", "cp1252", "latin-1"):
        try:
            raw = path.read_text(encoding=encoding)
            break
        except UnicodeDecodeError:
            continue
    if not raw:
        raise RuntimeError(f"Não consegui decodificar {path}")

    blocks: dict[int, list[str]] = {}
    current_version: int | None = None
    current_lines: list[str] = []
    for line in raw.splitlines():
        m = UPDATE_HEADER_RE.match(line)
        if m:
            if current_version is not None:
                blocks[current_version] = current_lines
            current_version = int(m.group(1))
            current_lines = []
        elif current_version is not None:
            current_lines.append(line)
    if current_version is not None:
        blocks[current_version] = current_lines
    return blocks


def slugify(s: str) -> str:
    return re.sub(r"[^A-Za-z0-9_-]+", "-", s).strip("-").lower()


def render_table_md(t: Table, module: str, target_v: int, all_tables: set[str]) -> str:
    """Renderiza 1 doc markdown por tabela (com FKs inferidas)."""
    lines: list[str] = []

    # Detecta FKs por convenção COD<TABELA> — ver dominios/wr-comercial/CONVENCOES.md
    fks = infer_fks_for_table(list(t.columns.keys()), all_tables)

    # Frontmatter
    lines.append("---")
    lines.append(f"table: {t.name}")
    lines.append(f"module: {module}")
    lines.append(f"created_at_version: {t.created_at_v}")
    lines.append(f"last_modified_version: {t.last_modified_v}")
    lines.append(f"target_version: {target_v}")
    lines.append(f"columns_count: {len(t.columns)}")
    lines.append(f"foreign_keys_count: {len(fks)}")
    if fks:
        lines.append("foreign_keys:")
        for col, target in sorted(fks.items()):
            lines.append(f"  {col}: {target}")
    if t.dropped_at_v:
        lines.append(f"dropped_at_version: {t.dropped_at_v}")
    lines.append(f"auto_generated: true")
    lines.append(f"generated_at: {date.today().isoformat()}")
    lines.append(f"generator: scripts/legacy-migration/generate-baseline.py")
    lines.append("source: D:/Programas/WR Comercial/Resources/UpdateSQL.txt")
    lines.append("---")
    lines.append("")

    # Body
    lines.append(f"# `{t.name}`")
    lines.append("")
    lines.append(
        f"> ⚠️ **Auto-gerado** a partir de `UpdateSQL.txt` blocos UPDATE 1..{target_v}. "
        "Não editar manualmente — re-rode `scripts/legacy-migration/generate-baseline.py`. "
        "Notas humanas vão em `_notes.md` ao lado."
    )
    lines.append("")
    lines.append(
        f"- **Módulo:** `{module}` (heurística por prefixo — Wagner refina em `lib/module_classifier.py` se errado)"
    )
    lines.append(f"- **Criada em:** UPDATE {t.created_at_v};")
    lines.append(f"- **Última mudança:** UPDATE {t.last_modified_v};")
    if t.dropped_at_v:
        lines.append(f"- **DROPPED em:** UPDATE {t.dropped_at_v};")
    lines.append(f"- **Total colunas (versão {target_v}):** {len(t.columns)}")
    lines.append("")

    # Foreign keys (detectadas por convenção COD<TABELA>)
    if fks:
        lines.append("## Foreign Keys (inferidas)")
        lines.append("")
        lines.append(
            "> Convenção [`CONVENCOES.md` §1](../../../../CONVENCOES.md): "
            "colunas `COD<TABELA>` apontam pra `<TABELA>(CODIGO)`. "
            "Auto-detectadas — Wagner refina exceções em `lib/fk_resolver.py`."
        )
        lines.append("")
        lines.append("| Coluna | → Tabela alvo |")
        lines.append("|---|---|")
        for col, target in sorted(fks.items()):
            lines.append(f"| `{col}` | [`{target}`](../../{classify_table(target)}/tabelas/{target}.md) |")
        lines.append("")

    # Colunas
    if t.columns:
        lines.append(f"## Colunas (versão {target_v})")
        lines.append("")
        lines.append("| # | Coluna | Tipo | Nullable | FK? | Adicionada em | Última mudança |")
        lines.append("|---|---|---|---|---|---|---|")
        for i, (_, col) in enumerate(t.columns.items(), 1):
            nullable = "NOT NULL" if not col.nullable else "NULL"
            col_upper = col.name.upper()
            fk_marker = f"→ `{fks[col_upper]}`" if col_upper in fks else ""
            lines.append(
                f"| {i} | `{col.name}` | `{col.type}` | {nullable} | {fk_marker} | "
                f"v{col.added_at_v} | v{col.last_modified_v} |"
            )
        lines.append("")

    # Histórico (compacto)
    if t.history:
        lines.append("## Evolução")
        lines.append("")
        lines.append("| UPDATE N; | Operação | Detalhe |")
        lines.append("|---|---|---|")
        # Limit a últimas 100 entradas pra não inflar gigantescamente
        history = t.history[-100:] if len(t.history) > 100 else t.history
        if len(t.history) > 100:
            lines.append(f"| (...) | (...) | _Mostrando últimas 100 de {len(t.history)} eventos_ |")
        for ev in history:
            detail_safe = ev.detail.replace("|", "\\|")
            lines.append(f"| {ev.version} | {ev.operation} | {detail_safe} |")
        lines.append("")

    return "\n".join(lines) + "\n"


def render_module_index(module: str, tables: list[Table], target_v: int) -> str:
    """_index.md de um módulo."""
    alive = [t for t in tables if t.dropped_at_v is None]
    dropped = [t for t in tables if t.dropped_at_v]

    lines: list[str] = []
    lines.append("---")
    lines.append(f"module: {module}")
    lines.append(f"target_version: {target_v}")
    lines.append(f"tables_alive: {len(alive)}")
    lines.append(f"tables_dropped: {len(dropped)}")
    lines.append("auto_generated: true")
    lines.append(f"generated_at: {date.today().isoformat()}")
    lines.append("---")
    lines.append("")
    lines.append(f"# Módulo Delphi — `{module}`")
    lines.append("")
    lines.append(
        f"Tabelas WR Comercial classificadas em `{module}` por heurística de prefixo. "
        "Schema reconstruído da versão alvo via `generate-baseline.py`."
    )
    lines.append("")

    if alive:
        lines.append(f"## Tabelas vivas ({len(alive)})")
        lines.append("")
        lines.append("| Tabela | Colunas | Criada | Última mudança |")
        lines.append("|---|---|---|---|")
        for t in sorted(alive, key=lambda x: x.name):
            lines.append(
                f"| [`{t.name}`](tabelas/{t.name}.md) | {len(t.columns)} | "
                f"v{t.created_at_v} | v{t.last_modified_v} |"
            )
        lines.append("")

    if dropped:
        lines.append(f"## Tabelas dropadas ({len(dropped)})")
        lines.append("")
        lines.append("| Tabela | Criada | Dropped |")
        lines.append("|---|---|---|")
        for t in sorted(dropped, key=lambda x: x.name):
            lines.append(
                f"| [`{t.name}`](tabelas/{t.name}.md) | v{t.created_at_v} | v{t.dropped_at_v} |"
            )
        lines.append("")

    return "\n".join(lines) + "\n"


def render_global_summary(state: SchemaState, target_v: int, by_module: dict[str, list[str]]) -> str:
    """Sumário global em memory/dominios/wr-comercial/modulos/_summary.md."""
    alive = state.alive_tables()
    total = len(state.tables)
    dropped = sum(1 for t in state.tables.values() if t.dropped_at_v)

    lines: list[str] = []
    lines.append("---")
    lines.append("auto_generated: true")
    lines.append(f"generated_at: {date.today().isoformat()}")
    lines.append(f"target_version: {target_v}")
    lines.append("---")
    lines.append("")
    lines.append(f"# Sumário schema reconstruído — versão {target_v}")
    lines.append("")
    lines.append(f"- **Total de tabelas registradas:** {total}")
    lines.append(f"- **Vivas na versão {target_v}:** {len(alive)}")
    lines.append(f"- **Dropadas em versões anteriores:** {dropped}")
    lines.append(
        f"- **Statements DDL não-reconhecidos:** {len(state.unrecognized_statements)} "
        "(esperado: INSERTs, procedures, etc — não-schema)"
    )
    lines.append("")

    lines.append("## Distribuição por módulo")
    lines.append("")
    lines.append("| Módulo | Tabelas vivas | Link |")
    lines.append("|---|---|---|")
    sorted_modules = sorted(by_module.items(), key=lambda kv: -len(kv[1]))
    for module, tables in sorted_modules:
        lines.append(f"| `{module}` | {len(tables)} | [_index.md]({module}/_index.md) |")
    lines.append("")

    if state.unrecognized_statements:
        lines.append("## Statements não-reconhecidos (amostra de 10)")
        lines.append("")
        lines.append("> ℹ️ Esperado pra DDL fora de escopo (INSERT, EXECUTE PROCEDURE, etc). "
                     "Se algum for ALTER TABLE relevante, reportar pra ajustar parser em `lib/ddl_parser.py`.")
        lines.append("")
        for v, snippet in state.unrecognized_statements[:10]:
            lines.append(f"- v{v}: `{snippet}`")
        lines.append("")

    return "\n".join(lines) + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--updatesql-path",
        default=os.environ.get(
            "UPDATESQL_PATH", "D:/Programas/WR Comercial/Resources/UpdateSQL.txt"
        ),
    )
    parser.add_argument(
        "--target-version",
        type=int,
        default=None,
        help="Versão alvo (default: maior versão real <1999 do .txt)",
    )
    parser.add_argument(
        "--output-dir",
        default="../../memory/dominios/wr-comercial/modulos",
        help="Pasta destino (relativa ao script)",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="Não escreve arquivos — só imprime sumário",
    )
    args = parser.parse_args()

    path = Path(args.updatesql_path)
    if not path.is_file():
        print(f"❌ {path} não existe", file=sys.stderr)
        return 1

    print(f"📖 Lendo {path}")
    blocks = parse_blocks(path)
    versions = sorted(blocks.keys())
    real_versions = [v for v in versions if v < 1999]
    target_v = args.target_version or max(real_versions)
    print(f"   Total blocos: {len(versions)}; range: v{versions[0]}..v{versions[-1]}")
    print(f"   Target version: v{target_v}")

    # Aplica blocos sequencialmente
    print(f"\n🔧 Aplicando blocos UPDATE 1..{target_v}...")
    state = SchemaState()
    applied = 0
    for v in sorted(blocks.keys()):
        if v > target_v:
            break
        state.apply_block(v, blocks[v])
        applied += 1
    print(f"   {applied} blocos aplicados")

    # Resultados
    alive = state.alive_tables()
    print(f"\n📊 Schema reconstruído:")
    print(f"   Total tabelas registradas: {len(state.tables)}")
    print(f"   Vivas em v{target_v}: {len(alive)}")
    print(f"   Dropadas: {sum(1 for t in state.tables.values() if t.dropped_at_v)}")
    print(f"   Statements não-reconhecidos: {len(state.unrecognized_statements)}")

    # Classifica
    by_module = classify_all(list(alive.keys()))
    print(f"\n🗂️  Distribuição por módulo:")
    for module, tables in sorted(by_module.items(), key=lambda kv: -len(kv[1])):
        print(f"   {module:20} → {len(tables):>3} tabelas")

    if args.dry_run:
        print("\n✋ --dry-run — não escrevi arquivos")
        return 0

    # Gera arquivos
    out_dir = (Path(__file__).parent / args.output_dir).resolve()
    print(f"\n💾 Gerando docs em {out_dir}")
    out_dir.mkdir(parents=True, exist_ok=True)

    files_written = 0

    # 1 doc por tabela viva (FK resolver precisa do set completo)
    all_table_names = set(alive.keys())
    for table_name, t in alive.items():
        module = classify_table(table_name)
        module_dir = out_dir / module / "tabelas"
        module_dir.mkdir(parents=True, exist_ok=True)
        target_path = module_dir / f"{table_name}.md"
        target_path.write_text(
            render_table_md(t, module, target_v, all_table_names),
            encoding="utf-8",
        )
        files_written += 1

    # _index.md por módulo
    for module, tables_in_mod in by_module.items():
        # Recupera Table objects
        table_objs = [alive[name] for name in tables_in_mod]
        index_path = out_dir / module / "_index.md"
        index_path.write_text(
            render_module_index(module, table_objs, target_v), encoding="utf-8"
        )
        files_written += 1

    # Sumário global
    summary_path = out_dir / "_summary.md"
    summary_path.write_text(
        render_global_summary(state, target_v, by_module), encoding="utf-8"
    )
    files_written += 1

    print(f"   {files_written} arquivos escritos")
    print(f"\n✅ Fase 3 concluída — schema baseline em {out_dir}")
    print(f"   Sumário: {summary_path}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
