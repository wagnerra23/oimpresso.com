"""
Fase 5+ — Enrichment dos products migrados (executa DEPOIS de import-produtos.py).

Atualiza retroativamente os products + variations + variation_location_details +
variation_group_prices a partir dos campos completos da tabela PRODUTO Delphi.

Campos atualizados:
- variations.default_purchase_price (CUSTO_LOJA Delphi)
- variations.default_sell_price (VALOR Delphi)
- variations.dpp_inc_tax (= default_purchase_price)
- variations.sell_price_inc_tax (= default_sell_price)
- variations.profit_percent (MARGEM Delphi)
- variation_location_details.qty_available (ESTOQUE Delphi)
- products.alert_quantity (ESTOQUE_MIN Delphi)
- products.product_description (HISTORICO Delphi)

Skip: categorias e tabela de preço — requerem mapping lookups separados.

Uso:
    python enrich-produtos.py --alias "Martinho online" --target-business 164 \\
        --location-id 132 --target prod --confirm
"""

from __future__ import annotations

import argparse
import os
import sys
from datetime import datetime
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


def normalize_decimal(raw) -> float:
    if raw is None or raw == "":
        return 0.0
    try:
        return float(raw)
    except (ValueError, TypeError):
        return 0.0


def normalize_str(raw, max_len=None):
    if raw is None:
        return None
    s = str(raw).strip()
    if not s:
        return None
    if max_len and len(s) > max_len:
        s = s[:max_len]
    return s


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--location-id", type=int, required=True,
                        help="business_location_id no oimpresso pra qty_available")
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    print(f"== Enricher PRODUTO v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  location_id alvo : {args.location_id}")
    print(f"  Target MySQL     : {args.target}")

    stats = {
        "lidos_fb": 0, "matched": 0, "no_product": 0,
        "var_updates": 0, "prod_updates": 0, "vld_upserts": 0,
        "errors": 0,
    }

    if args.target not in ("local", "prod"):
        print("[ERRO] --target deve ser local ou prod (dry-run não suportado aqui)", file=sys.stderr)
        return 3
    if pymysql is None:
        print("[ERRO] pymysql não instalado", file=sys.stderr)
        return 3

    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols = get_existing_cols(fb_con, "PRODUTO")
        print(f"[Adapter] PRODUTO cols presentes: {len(cols)}")

        # Cols canônicas (com fallback NULL quando ausente)
        wanted = ["CODIGO", "VALOR", "CUSTO_LOJA", "CUSTO_FABR", "CUSTO_MEDIO",
                  "MARGEM", "VALOR_PRAZO", "MARGEM_PRAZO", "VALORATACADO",
                  "MARGEM_ATACADO", "ESTOQUE", "ESTOQUE_MIN", "ESTOQUE_MAX",
                  "ATIVO", "HISTORICO", "CODNF_NCM"]
        select_parts = []
        for c in wanted:
            if c in cols:
                select_parts.append(f"P.{c}")
            else:
                select_parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
        select_clause = ", ".join(select_parts)

        ativo_where = "WHERE P.ATIVO = 'S'" if "ATIVO" in cols else ""
        sql = f"SELECT {select_clause} FROM PRODUTO P {ativo_where} ORDER BY P.CODIGO"
        print(f"\n[Query PRODUTO] {sql[:200]}...")

        cur = fb_con.cursor()
        cur.execute(sql)
        col_names = [d[0] for d in cur.description]

        # Pre-load lookup products + variation por SKU
        print("\n[Pre-load] products + variations biz=164...")
        my_con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port,
            user=args.mysql_user, password=args.mysql_password,
            database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor, autocommit=False,
        )
        product_lookup: dict[str, tuple[int, int]] = {}  # sku → (product_id, variation_id)
        with my_con.cursor() as rcur:
            rcur.execute(
                "SELECT p.id AS product_id, p.sku, v.id AS variation_id "
                "FROM products p LEFT JOIN variations v ON v.product_id=p.id "
                "WHERE p.business_id=%s",
                (args.target_business,),
            )
            for r in rcur.fetchall():
                sku = str(r["sku"] or "").strip()
                if sku and r["variation_id"] and sku not in product_lookup:
                    product_lookup[sku] = (int(r["product_id"]), int(r["variation_id"]))
        print(f"  → {len(product_lookup)} products com variation indexados")

        try:
            batch_count = 0
            BATCH = 500
            for row_tuple in cur:
                stats["lidos_fb"] += 1
                if stats["lidos_fb"] % 500 == 0:
                    print(f"  ... lidos {stats['lidos_fb']} · matched {stats['matched']}", flush=True)
                prod = dict(zip(col_names, row_tuple))
                codigo = str(prod.get("CODIGO") or "").strip()
                if not codigo:
                    continue

                found = product_lookup.get(codigo)
                if not found:
                    stats["no_product"] += 1
                    continue
                stats["matched"] += 1
                product_id, variation_id = found

                valor = normalize_decimal(prod.get("VALOR"))
                custo = normalize_decimal(prod.get("CUSTO_LOJA"))
                if custo == 0:
                    custo = normalize_decimal(prod.get("CUSTO_MEDIO"))
                if custo == 0:
                    custo = normalize_decimal(prod.get("CUSTO_FABR"))
                margem = normalize_decimal(prod.get("MARGEM"))
                estoque = normalize_decimal(prod.get("ESTOQUE"))
                estoque_min = normalize_decimal(prod.get("ESTOQUE_MIN"))
                descricao = normalize_str(prod.get("HISTORICO"), 500)

                with my_con.cursor() as wcur:
                    # 1. UPDATE variations com preços + margem
                    wcur.execute(
                        "UPDATE variations SET default_purchase_price=%s, dpp_inc_tax=%s, "
                        "profit_percent=%s, default_sell_price=%s, sell_price_inc_tax=%s, "
                        "updated_at=NOW() WHERE id=%s",
                        (custo, custo, margem, valor, valor, variation_id),
                    )
                    if wcur.rowcount > 0:
                        stats["var_updates"] += 1

                    # 2. UPSERT variation_location_details com qty_available
                    wcur.execute(
                        "SELECT id FROM variation_location_details "
                        "WHERE variation_id=%s AND location_id=%s LIMIT 1",
                        (variation_id, args.location_id),
                    )
                    vld = wcur.fetchone()
                    if vld:
                        wcur.execute(
                            "UPDATE variation_location_details SET qty_available=%s, updated_at=NOW() WHERE id=%s",
                            (estoque, vld["id"]),
                        )
                    else:
                        try:
                            wcur.execute(
                                "INSERT INTO variation_location_details "
                                "(product_id, product_variation_id, variation_id, location_id, qty_available, created_at, updated_at) "
                                "VALUES (%s, "
                                "(SELECT product_variation_id FROM variations WHERE id=%s), "
                                "%s, %s, %s, NOW(), NOW())",
                                (product_id, variation_id, variation_id, args.location_id, estoque),
                            )
                        except Exception as e:
                            print(f"  [warn] vld fail prod={product_id}: {e}")
                    stats["vld_upserts"] += 1

                    # 3. UPDATE products com alert_quantity + product_description
                    wcur.execute(
                        "UPDATE products SET alert_quantity=%s, product_description=%s, updated_at=NOW() WHERE id=%s",
                        (estoque_min, descricao, product_id),
                    )
                    if wcur.rowcount > 0:
                        stats["prod_updates"] += 1

                batch_count += 1
                if batch_count >= BATCH:
                    my_con.commit()
                    print(f"  [batch commit] matched={stats['matched']} var={stats['var_updates']} vld={stats['vld_upserts']} prod={stats['prod_updates']}", flush=True)
                    batch_count = 0

            my_con.commit()
            print("[OK] Commit MySQL final", flush=True)
        except Exception as e:
            my_con.rollback()
            print(f"[ERRO] Rollback: {e}", file=sys.stderr)
            stats["errors"] += 1
            raise
        finally:
            cur.close()
            my_con.close()

    print("\n== Relatório ==")
    print(f"  Lidos Firebird       : {stats['lidos_fb']}")
    print(f"  Matched (SKU=CODIGO) : {stats['matched']}")
    print(f"  Sem product (skip)   : {stats['no_product']}")
    print(f"  Variations updated   : {stats['var_updates']}")
    print(f"  Products updated     : {stats['prod_updates']}")
    print(f"  Variation_locations  : {stats['vld_upserts']}")
    print(f"  Errors               : {stats['errors']}")
    print(f"[OK] Enricher concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
