"""
Dump schema-only da tabela PESSOAS do Firebird WR Comercial.
Output: scripts/legacy-migration/output/pessoas-schema-{cliente}-{timestamp}.txt

- Lista 329 cols com tipo Firebird, size, nullable, default.
- Conta cardinalidade por TIPO (CLI/FOR/FUN/REP/T/custom).
- Calcula nullability/distinct real top 30 colunas (limita pra não demorar).
- ZERO PII no output. Schema-only por design.
- Header com data, alias, cliente, total cadastros.

Contexto (Wagner direcionou 2026-05-26):
- Finaliza Bucket B do gap PESSOAS vs contacts (memory/sessions/2026-05-26-gap-pessoas-vs-contacts.md §1bis + §4).
- Output cola no chat com Claude → confirmação Bucket B + ajustes Bucket A.

Uso:
    pip install firebird-driver python-dotenv
    python dump-pessoas-schema.py --alias=Vargas

Opções:
    --alias <name>        Alias HKCU registry (default: Vargas)
    --app-title <title>   ApplicationTitle WR (default: Office Comercial)
    --host <ip:db>        Override conexão direta (ex: 192.168.0.55:Banco)
    --user <user>         User Firebird (default: SYSDBA)
    --password <pwd>      Override senha (default: masterkey ou registry)
    --charset <c>         Charset (default: WIN1252)
    --out <path>          Override output path
    --top-cols <N>        Quantas cols calcular nullability real (default: 30)

LGPD/Tier 0:
- NUNCA roda SELECT * FROM PESSOAS.
- NUNCA dumpa valores reais.
- Só agregados: COUNT(*), COUNT(col IS NOT NULL), COUNT(DISTINCT col).
- Cardinalidade por TIPO usa GROUP BY TIPO (1 char — F/J, sem PII).
- Safe pra rodar e colar no chat público.
"""

from __future__ import annotations

import argparse
import os
import sys
import winreg
from datetime import datetime
from pathlib import Path

# Força stdout UTF-8 — Windows console default é cp1252 e crasha em emojis
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

try:
    import firebird.driver as fb
except ImportError:
    print(
        "[ERRO] firebird-driver não instalado. Rode:\n"
        "   pip install firebird-driver python-dotenv",
        file=sys.stderr,
    )
    sys.exit(2)


# Mapping tipo Firebird (RDB$FIELD_TYPE) → label legível
# Ref: docs.firebirdsql.org/refdocs/langrefupd25-appx04-fields.html
FB_TYPES = {
    7: "SMALLINT",
    8: "INTEGER",
    10: "FLOAT",
    12: "DATE",
    13: "TIME",
    14: "CHAR",
    16: "BIGINT",
    27: "DOUBLE PRECISION",
    35: "TIMESTAMP",
    37: "VARCHAR",
    261: "BLOB",
}

# Mapping subtype pra NUMERIC/DECIMAL (RDB$FIELD_SUB_TYPE em INT64/INT)
FB_SUBTYPES_NUMERIC = {
    1: "NUMERIC",
    2: "DECIMAL",
}


def read_registry_alias(app_title: str, alias: str) -> tuple[str, str]:
    """Lê path+senha do registry pra um alias do WR Office Comercial.

    Estrutura confirmada em backup\\Principal.pas:3482:
        HKCU\\Software\\Rocha\\<APP_TITLE>\\Banco\\Caminhos       (props = aliases → paths)
        HKCU\\Software\\Rocha\\<APP_TITLE>\\Banco\\Caminhos\\Senhas (props = paths → passwords)
    """
    caminhos_key = rf"Software\Rocha\{app_title}\Banco\Caminhos"
    senhas_key = rf"Software\Rocha\{app_title}\Banco\Caminhos\Senhas"

    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, caminhos_key) as k:
            path, _ = winreg.QueryValueEx(k, alias)
    except FileNotFoundError as e:
        raise RuntimeError(
            f"Alias '{alias}' não encontrado em {caminhos_key}. "
            f"Verifique no app Editor de Registros de Bancos de Dados."
        ) from e

    try:
        with winreg.OpenKey(winreg.HKEY_CURRENT_USER, senhas_key) as k:
            password, _ = winreg.QueryValueEx(k, path)
    except FileNotFoundError:
        password = ""

    return path, password


def fb_type_label(field_type: int, sub_type: int | None, length: int | None,
                  precision: int | None, scale: int | None) -> str:
    """Monta label tipo legível a partir dos códigos Firebird."""
    base = FB_TYPES.get(field_type, f"UNKNOWN({field_type})")

    if field_type in (16, 7, 8) and sub_type in FB_SUBTYPES_NUMERIC:
        # NUMERIC(p,s) ou DECIMAL(p,s)
        base = FB_SUBTYPES_NUMERIC[sub_type]
        if precision is not None and scale is not None:
            return f"{base}({precision},{abs(scale)})"
        return base

    if field_type in (14, 37) and length:
        return f"{base}({length})"

    return base


def dump_schema(con, top_cols: int) -> dict:
    """Executa todas as queries de schema e cardinalidade. Retorna dict pra render."""
    cur = con.cursor()
    result = {
        "columns": [],
        "total_rows": 0,
        "by_tipo": [],
        "top_cols_stats": [],
    }

    # 1. Lista colunas via RDB$RELATION_FIELDS + RDB$FIELDS
    print("[1/4] Listando colunas via RDB$RELATION_FIELDS...")
    cur.execute("""
        SELECT
            TRIM(rf.RDB$FIELD_NAME)       AS field_name,
            f.RDB$FIELD_TYPE              AS field_type,
            f.RDB$FIELD_SUB_TYPE          AS sub_type,
            f.RDB$FIELD_LENGTH            AS length,
            f.RDB$FIELD_PRECISION         AS precision_,
            f.RDB$FIELD_SCALE             AS scale_,
            COALESCE(rf.RDB$NULL_FLAG, f.RDB$NULL_FLAG) AS not_null_flag,
            rf.RDB$DEFAULT_SOURCE         AS default_source,
            rf.RDB$FIELD_POSITION         AS position_
        FROM RDB$RELATION_FIELDS rf
        JOIN RDB$FIELDS f ON f.RDB$FIELD_NAME = rf.RDB$FIELD_SOURCE
        WHERE rf.RDB$RELATION_NAME = 'PESSOAS'
        ORDER BY rf.RDB$FIELD_POSITION
    """)
    for row in cur.fetchall():
        (field_name, field_type, sub_type, length, precision_,
         scale_, not_null_flag, default_source, position_) = row

        default_str = ""
        if default_source:
            # Default vem como "DEFAULT 'foo'" — extrair só o valor literal
            try:
                ds = default_source.decode("WIN1252") if isinstance(default_source, bytes) else default_source
                default_str = ds.replace("DEFAULT", "").strip()
            except Exception:
                default_str = "(default)"

        result["columns"].append({
            "name": field_name,
            "type": fb_type_label(field_type, sub_type, length, precision_, scale_),
            "nullable": "NO" if not_null_flag == 1 else "YES",
            "default": default_str,
            "position": position_,
        })

    print(f"      OK: {len(result['columns'])} colunas detectadas")

    # 2. Total de registros
    print("[2/4] Contando total de cadastros...")
    cur.execute("SELECT COUNT(*) FROM PESSOAS")
    result["total_rows"] = cur.fetchone()[0]
    print(f"      OK: {result['total_rows']:,} cadastros")

    # 3. Cardinalidade por TIPO (F/J = pessoa física/jurídica)
    print("[3/4] Cardinalidade por TIPO (F/J)...")
    try:
        cur.execute("""
            SELECT TRIM(TIPO) AS tipo, COUNT(*) AS qtd
            FROM PESSOAS
            GROUP BY TIPO
            ORDER BY 2 DESC
        """)
        for tipo, qtd in cur.fetchall():
            result["by_tipo"].append({"tipo": tipo or "(NULL)", "qtd": qtd})
        print(f"      OK: {len(result['by_tipo'])} valores distintos de TIPO")
    except Exception as e:
        print(f"      WARN: {e!r} — continuando sem cardinalidade TIPO")

    # 3b. Cardinalidade por IS_<flags> — quantos cadastros têm cada papel
    # Detecta cols IS_* dinamicamente do schema já dumpado
    is_cols = [c["name"] for c in result["columns"] if c["name"].startswith("IS_")]
    print(f"[3b] Cardinalidade por flag IS_* ({len(is_cols)} cols)...")
    result["by_is_flag"] = []
    for col in is_cols[:20]:  # limita pra não demorar — top 20 IS_* já cobre 4-6 papéis principais
        try:
            # Conta cadastros onde a flag está ativa ('S' ou 1, depende cliente)
            cur.execute(
                f"SELECT COUNT(*) FROM PESSOAS WHERE {col} = 'S' OR {col} = 1"
            )
            qtd = cur.fetchone()[0]
            if qtd > 0:
                result["by_is_flag"].append({"flag": col, "qtd": qtd})
        except Exception:
            # Tipo da coluna pode ser diferente — skip silencioso
            pass

    # 4. Top N colunas — nullability real (COUNT not null) + distinct (limit p/ perf)
    print(f"[4/4] Top {top_cols} colunas: nullability + distinct (pode demorar 30-60s)...")
    # Pega top N por posição — primeiras cols são as canônicas (CODIGO, TIPO, CNPJCPF, etc)
    top_col_names = [c["name"] for c in result["columns"][:top_cols]]
    total = result["total_rows"] or 1

    for col in top_col_names:
        try:
            cur.execute(f"SELECT COUNT({col}) FROM PESSOAS")
            not_null_count = cur.fetchone()[0]

            # COUNT DISTINCT pode ser caro em BLOB/TEXT — pula se for tipo gigante
            col_info = next(c for c in result["columns"] if c["name"] == col)
            if "BLOB" in col_info["type"]:
                distinct_count = "(BLOB-skip)"
            else:
                cur.execute(f"SELECT COUNT(DISTINCT {col}) FROM PESSOAS")
                distinct_count = cur.fetchone()[0]

            null_pct = (1 - not_null_count / total) * 100 if total else 0
            result["top_cols_stats"].append({
                "name": col,
                "not_null_count": not_null_count,
                "null_pct": round(null_pct, 1),
                "distinct_count": distinct_count,
            })
        except Exception as e:
            result["top_cols_stats"].append({
                "name": col,
                "not_null_count": "(error)",
                "null_pct": "(error)",
                "distinct_count": str(e)[:50],
            })

    print(f"      OK")
    cur.close()
    return result


def render_output(data: dict, alias: str, db_path: str, timestamp: str) -> str:
    """Renderiza markdown-like output (texto plain, copy-paste-friendly pro chat)."""
    lines = []
    lines.append("=" * 80)
    lines.append("DUMP SCHEMA PESSOAS — Firebird WR Comercial (schema-only, ZERO PII)")
    lines.append("=" * 80)
    lines.append(f"Alias       : {alias}")
    lines.append(f"DB Path     : {db_path}")
    lines.append(f"Timestamp   : {timestamp}")
    lines.append(f"Total cads  : {data['total_rows']:,}")
    lines.append(f"Total cols  : {len(data['columns'])}")
    lines.append("")
    lines.append("Origem      : memory/sessions/2026-05-26-gap-pessoas-vs-contacts.md")
    lines.append("Direcao     : Wagner 2026-05-26 — finalizar Bucket B contact_profile_legacy")
    lines.append("")

    # Seção 1: cardinalidade por TIPO
    lines.append("-" * 80)
    lines.append("SEÇÃO 1 — Cardinalidade por TIPO (F=Física / J=Jurídica)")
    lines.append("-" * 80)
    if data.get("by_tipo"):
        for row in data["by_tipo"]:
            lines.append(f"  TIPO={row['tipo']:8s}  qtd={row['qtd']:>8,}")
    else:
        lines.append("  (sem dados)")
    lines.append("")

    # Seção 1b: cardinalidade por IS_* flag
    lines.append("-" * 80)
    lines.append("SEÇÃO 1b — Cardinalidade por flag IS_* (papéis Delphi — ADR 0188 maps)")
    lines.append("-" * 80)
    if data.get("by_is_flag"):
        for row in data["by_is_flag"]:
            lines.append(f"  {row['flag']:30s}  qtd={row['qtd']:>8,}")
    else:
        lines.append("  (nenhuma flag IS_* ativa — pode ser que cliente use schema custom)")
    lines.append("")

    # Seção 2: Schema completo
    lines.append("-" * 80)
    lines.append(f"SEÇÃO 2 — Schema completo PESSOAS ({len(data['columns'])} colunas)")
    lines.append("-" * 80)
    lines.append(f"{'pos':>4} | {'nome':<35} | {'tipo':<25} | {'null':<4} | default")
    lines.append("-" * 80)
    for col in data["columns"]:
        default_display = col["default"][:20] if col["default"] else ""
        lines.append(
            f"{col['position']:>4} | {col['name']:<35} | {col['type']:<25} | "
            f"{col['nullable']:<4} | {default_display}"
        )
    lines.append("")

    # Seção 3: Top cols stats
    lines.append("-" * 80)
    lines.append(f"SEÇÃO 3 — Top {len(data['top_cols_stats'])} colunas: nullability + distinct real")
    lines.append("(útil pra Bucket D — descartar cols 100% nulas / cardinalidade 0)")
    lines.append("-" * 80)
    lines.append(f"{'col':<35} | {'not_null':>10} | {'null%':>7} | {'distinct':>10}")
    lines.append("-" * 80)
    for col in data["top_cols_stats"]:
        nn = col["not_null_count"]
        nn_str = f"{nn:>10,}" if isinstance(nn, int) else f"{str(nn):>10}"
        np = col["null_pct"]
        np_str = f"{np:>6.1f}%" if isinstance(np, (int, float)) else f"{str(np):>7}"
        dc = col["distinct_count"]
        dc_str = f"{dc:>10,}" if isinstance(dc, int) else f"{str(dc):>10}"
        lines.append(f"{col['name']:<35} | {nn_str} | {np_str} | {dc_str}")
    lines.append("")

    lines.append("=" * 80)
    lines.append("FIM DO DUMP")
    lines.append("=" * 80)
    lines.append("")
    lines.append(">> Cola esse output no chat com Claude pra finalizar Bucket B (ADR 0195) <<")
    lines.append("")

    return "\n".join(lines)


def main() -> int:
    parser = argparse.ArgumentParser(
        description="Dump schema-only PESSOAS Firebird (ZERO PII)",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    parser.add_argument("--alias", default="Vargas",
                        help="Alias do banco no registry HKCU (default: Vargas)")
    parser.add_argument("--app-title",
                        default=os.environ.get("WR_APP_TITLE", "Office Comercial"),
                        help="ApplicationTitle do app WR (default: Office Comercial)")
    parser.add_argument("--host", default=None,
                        help="Override conexão direta (ex: 192.168.0.55:Banco) — pula registry")
    parser.add_argument("--user",
                        default=os.environ.get("FIREBIRD_USER", "SYSDBA"),
                        help="User Firebird (default: SYSDBA)")
    parser.add_argument("--password",
                        default=os.environ.get("FIREBIRD_PASSWORD"),
                        help="Override senha (default: 'masterkey' fallback ou registry)")
    parser.add_argument("--charset",
                        default=os.environ.get("FIREBIRD_CHARSET", "WIN1252"),
                        help="Charset (default: WIN1252)")
    parser.add_argument("--out", default=None,
                        help="Override output path")
    parser.add_argument("--top-cols", type=int, default=30,
                        help="Quantas cols calcular nullability real (default: 30)")
    args = parser.parse_args()

    # Passo 1: resolve conexão
    if args.host:
        db_path = args.host
        password = args.password or "masterkey"
        print(f"[CONN] Conexão direta: {db_path} (sem registry)")
    else:
        print(f"[CONN] Resolvendo alias '{args.alias}' no registry HKCU\\Software\\Rocha\\...")
        try:
            db_path, registry_pwd = read_registry_alias(args.app_title, args.alias)
        except RuntimeError as e:
            print(f"[ERRO] {e}", file=sys.stderr)
            print(
                "\nDicas:\n"
                f"  - Alias '{args.alias}' está cadastrado no app Editor de Registros de Bancos de Dados?\n"
                f"  - --app-title é '{args.app_title}'? Pode ser 'Office Comercial' ou outro.\n"
                f"  - Use --host pra conexão direta sem registry: --host servidor-crm:Banco",
                file=sys.stderr,
            )
            return 1

        password = args.password or registry_pwd or "masterkey"
        print(f"       DB Path: {db_path}")
        print(f"       Senha  : {'(override)' if args.password else '(registry/fallback)'}")

    # Passo 2: conecta
    print(f"\n[CONN] Conectando como {args.user}@{db_path} (charset={args.charset})...")
    try:
        con = fb.connect(
            database=db_path,
            user=args.user,
            password=password,
            charset=args.charset,
        )
    except Exception as e:
        print(f"[ERRO] Falha na conexão: {e!r}", file=sys.stderr)
        print(
            "\nDicas:\n"
            "  - fbclient.dll está no PATH? (deve estar instalado pelo Delphi)\n"
            "  - Host resolvível na sua LAN?\n"
            "  - User/senha corretos? (default factory: SYSDBA / masterkey)\n"
            "  - Charset alternativo? Tenta --charset NONE ou --charset UTF8",
            file=sys.stderr,
        )
        return 1

    print("       OK conectado")

    # Passo 3: dump
    print()
    timestamp = datetime.now().strftime("%Y%m%d-%H%M%S")
    try:
        data = dump_schema(con, top_cols=args.top_cols)
    except Exception as e:
        print(f"[ERRO] Falha no dump: {e!r}", file=sys.stderr)
        con.close()
        return 1

    con.close()

    # Passo 4: render output
    output_text = render_output(data, args.alias, db_path, timestamp)

    # Passo 5: escreve arquivo (append-only — nunca sobrescreve)
    if args.out:
        out_path = Path(args.out)
    else:
        out_dir = Path(os.environ.get("OUTPUT_DIR", "scripts/legacy-migration/output"))
        out_dir.mkdir(parents=True, exist_ok=True)
        out_path = out_dir / f"pessoas-schema-{args.alias}-{timestamp}.txt"

    if out_path.exists():
        print(f"[ERRO] Output já existe (append-only): {out_path}", file=sys.stderr)
        return 1

    out_path.write_text(output_text, encoding="utf-8")

    print(f"\n[OK] Dump escrito em: {out_path}")
    print(f"     {len(data['columns'])} cols · {data['total_rows']:,} cadastros · {args.top_cols} cols com stats reais")
    print()
    print(">> Cola o conteúdo desse arquivo no chat com Claude pra finalizar o gap analysis. <<")
    return 0


if __name__ == "__main__":
    sys.exit(main())
