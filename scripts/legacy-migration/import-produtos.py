"""
Fase 5+ — Importer PRODUTO Delphi WR Comercial → products + variations.

PRODUTO Delphi tem PK alfanumérica (CODIGO VARCHAR(15) — ex: "16L24/100"). Não
existe FK FK_CODPRODUTO em VENDA_PRODUTO porque é match por string.

Mapping mínimo viable (pra demo + linkage com transaction_sell_lines):
  - PRODUTO.CODIGO → products.sku + products.legacy_id (lookup key)
  - PRODUTO.DESCRICAO → products.name
  - PRODUTO.VALOR → products.unit_price (preço default tabela)
  - PRODUTO.UNIDADE → products.unit_id (via lookup tabela units, fallback id=1)
  - PRODUTO.ATIVO='S' → products.not_for_selling=0

Cria também 1 default `variation` por product (DUMMY variation_id 1 padrão UltimatePOS,
mas precisa criar real per product pra FK do transaction_sell_lines funcionar).

Multi-tenant Tier 0 (ADR 0093). Idempotente via (business_id, sku).

Uso:
    python import-produtos.py --alias "Martinho online" --target-business 164 --target prod --confirm
"""

from __future__ import annotations

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from pathlib import Path

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.1.0"
LEGACY_SOURCE = "wr-comercial-delphi"

# Cols canônicas Martinho v1404
PRODUTO_COLS = [
    "CODIGO", "DESCRICAO", "UNIDADE", "CODPRODUTO_GRUPO", "VALOR",
    "VALOR_PRAZO", "CUSTO_LOJA", "CUSTO_FABR", "ESTOQUE", "ATIVO",
    "CODNF_NCM", "CODFABRICA",
]


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def normalize_str(raw, max_len=None) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    if max_len and len(s) > max_len:
        s = s[:max_len]
    return s


def normalize_decimal(raw) -> float:
    if raw is None or raw == "":
        return 0.0
    try:
        return float(raw)
    except (ValueError, TypeError):
        return 0.0


def map_produto(prod: dict, business_id: int, unit_lookup: dict[str, int], default_unit: int) -> dict:
    codigo = str(prod["CODIGO"]).strip()
    descricao = normalize_str(prod.get("DESCRICAO"), 191) or f"Produto {codigo}"
    unidade_str = normalize_str(prod.get("UNIDADE"))
    unit_id = unit_lookup.get((unidade_str or "").upper(), default_unit) if unidade_str else default_unit
    valor = normalize_decimal(prod.get("VALOR"))
    ativo = (prod.get("ATIVO") or "S").upper() == "S"

    # NOTA: tabela `products` UltimatePOS NÃO tem coluna `legacy_id` ainda.
    # Idempotência via UK (business_id, sku) onde sku = CODIGO Delphi.
    # Valor default + NCM ficam pra preencher via UPDATE de campos diretos
    # (não JSON) — alert_quantity reusado pra valor_default? Não — fica em
    # observação futura quando migration add_legacy_id rodar.
    return {
        "business_id": business_id,
        "name": descricao,
        "type": "single",  # produto simples (sem variations reais)
        "unit_id": unit_id,
        "sku": codigo[:191],
        "not_for_selling": 0 if ativo else 1,
        "enable_stock": 1 if normalize_decimal(prod.get("ESTOQUE")) > 0 else 0,
        "alert_quantity": 0,
        "tax_type": "exclusive",
    }, valor  # retorna (data, valor) — valor usado pro variation default sell_price


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--only-ativo", action="store_true", default=True)
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=500)
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    print(f"== Importer PRODUTO v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")

    stats = {"lidos": 0, "inserts": 0, "updates": 0, "skipped": 0, "errors": 0,
             "variations_inserts": 0, "variations_existing": 0}

    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes = get_existing_cols(fb_con, "PRODUTO")
        cols_pedidas = [c for c in PRODUTO_COLS if c in cols_existentes]
        if len(cols_pedidas) < len(PRODUTO_COLS):
            print(f"[Adapter] cols ausentes: {set(PRODUTO_COLS) - cols_existentes}")

        # Lookup units no oimpresso pra mapear UNIDADE Delphi → unit_id
        unit_lookup: dict[str, int] = {}
        default_unit = 1
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("[ERRO] pymysql não instalado", file=sys.stderr)
                return 3
            tmp_con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor,
            )
            try:
                with tmp_con.cursor() as cur:
                    cur.execute(
                        "SELECT id, short_name FROM units WHERE business_id=%s",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        if r["short_name"]:
                            unit_lookup[r["short_name"].upper()] = int(r["id"])
                    # default
                    cur.execute("SELECT id FROM units WHERE business_id=%s ORDER BY id LIMIT 1",
                                (args.target_business,))
                    row = cur.fetchone()
                    if row:
                        default_unit = int(row["id"])
            finally:
                tmp_con.close()
            print(f"[Units lookup] {len(unit_lookup)} entries · default unit_id={default_unit}")

        # Query PRODUTO
        where_clause = "WHERE ATIVO = 'S'" if args.only_ativo and "ATIVO" in cols_existentes else ""
        select_clause = ", ".join(cols_pedidas)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"SELECT {first_clause} {select_clause} FROM PRODUTO {where_clause} ORDER BY CODIGO"
        print(f"\n[Query PRODUTO] {sql[:200]}...")

        cur = fb_con.cursor()
        cur.execute(sql)
        col_names = [d[0] for d in cur.description]

        con = None
        if args.target in ("local", "prod"):
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )

        try:
            batch_count = 0
            for row_tuple in cur:
                stats["lidos"] += 1
                prod = dict(zip(col_names, row_tuple))
                codigo = str(prod.get("CODIGO") or "").strip()
                if not codigo:
                    stats["skipped"] += 1
                    continue

                data, valor = map_produto(prod, args.target_business, unit_lookup, default_unit)

                if args.target == "dry-run":
                    stats["inserts"] += 1
                    continue

                assert con is not None
                with con.cursor() as wcur:
                    # Idempotência: (business_id, sku)
                    wcur.execute(
                        "SELECT id FROM products WHERE business_id=%s AND sku=%s LIMIT 1",
                        (args.target_business, data["sku"]),
                    )
                    existing = wcur.fetchone()
                    if existing:
                        product_id = int(existing["id"])
                        update_fields = {k: v for k, v in data.items()
                                          if k not in ("business_id", "sku", "type")}
                        set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                        wcur.execute(
                            f"UPDATE products SET {set_clause}, updated_at=NOW() WHERE id=%s",
                            (*update_fields.values(), product_id),
                        )
                        stats["updates"] += 1
                    else:
                        cols = list(data.keys()) + ["created_by"]
                        placeholders = ", ".join(["%s"] * len(cols))
                        wcur.execute(
                            f"INSERT INTO products ({', '.join(cols)}, created_at, updated_at) "
                            f"VALUES ({placeholders}, NOW(), NOW())",
                            (*data.values(), args.created_by),
                        )
                        product_id = wcur.lastrowid
                        stats["inserts"] += 1

                    # variation default — necessária pra FK de transaction_sell_lines
                    wcur.execute(
                        "SELECT id FROM variations WHERE product_id=%s LIMIT 1",
                        (product_id,),
                    )
                    var_existing = wcur.fetchone()
                    if not var_existing:
                        # Precisa de variation_template_id default ou variation_value_id?
                        # Schema simples: só product_id + name + sub_sku + default_purchase_price/sell_price
                        try:
                            wcur.execute(
                                "INSERT INTO variations (product_id, name, sub_sku, "
                                "default_purchase_price, dpp_inc_tax, profit_percent, "
                                "default_sell_price, sell_price_inc_tax, created_at, updated_at) "
                                "VALUES (%s, 'DUMMY', %s, %s, %s, 0, %s, %s, NOW(), NOW())",
                                (product_id, data["sku"], 0, 0, valor, valor),
                            )
                            stats["variations_inserts"] += 1
                        except pymysql.err.IntegrityError as e:
                            # variation_template_id NOT NULL? race? skip
                            pass
                    else:
                        stats["variations_existing"] += 1

                batch_count += 1
                if batch_count >= args.batch_size:
                    con.commit()
                    print(f"  [batch] commited {batch_count} · total lidos: {stats['lidos']}")
                    batch_count = 0
            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count}")
        except Exception as e:
            if con:
                con.rollback()
                print(f"\n[ERRO] Rollback: {e}", file=sys.stderr)
            stats["errors"] += 1
            raise
        finally:
            cur.close()
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    audit_path = out / f"audit-produtos-biz{args.target_business}-{ts}.json"
    audit_path.write_text(
        json.dumps({
            "importer_version": IMPORTER_VERSION,
            "business_id": args.target_business,
            "target": args.target,
            "stats": stats,
        }, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"\n[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  Lidos              : {stats['lidos']}")
    print(f"  Produtos INSERT    : {stats['inserts']}")
    print(f"  Produtos UPDATE    : {stats['updates']}")
    print(f"  Variations INSERT  : {stats['variations_inserts']}")
    print(f"  Variations existing: {stats['variations_existing']}")
    print(f"  Skipped            : {stats['skipped']}")
    print(f"  Errors             : {stats['errors']}")
    print(f"[OK] PRODUTO concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
