"""
Fase 5+ — Enrich COMPLETO dos products migrados:
1. Categorias  PRODUTO_GRUPO → categories + UPDATE products.category_id
2. Tabelas de preço  PRODUTO_PRECO → selling_price_groups + variation_group_prices
3. Flags products: not_for_selling=0, enable_stock=1 (todos ATIVO=S)

Roda DEPOIS de import-produtos.py + enrich-produtos.py (que já setou VALOR/CUSTO/ESTOQUE).

Uso:
    python enrich-produtos-completo.py --alias "Martinho online" --target-business 164 \\
        --target prod --confirm
"""
from __future__ import annotations
import argparse, os, sys
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


def normalize_str(raw, max_len=None):
    if raw is None: return None
    s = str(raw).strip()
    if not s: return None
    if max_len and len(s) > max_len: s = s[:max_len]
    return s


def normalize_decimal(raw) -> float:
    if raw is None or raw == "": return 0.0
    try: return float(raw)
    except (ValueError, TypeError): return 0.0


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
    parser.add_argument("--target", choices=["local", "prod"], default="local")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    if pymysql is None:
        print("[ERRO] pymysql não instalado", file=sys.stderr)
        return 3

    stats = {
        "categorias_lidas": 0, "categorias_inseridas": 0, "produtos_atualizados_cat": 0,
        "precos_lidos": 0, "precos_inseridos": 0,
        "selling_groups_inseridos": 0,
        "produtos_flag_atualizados": 0,
    }

    print(f"== Enricher COMPLETO v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")

    my_con = pymysql.connect(
        host=args.mysql_host, port=args.mysql_port,
        user=args.mysql_user, password=args.mysql_password,
        database=args.mysql_database, charset="utf8mb4",
        cursorclass=pymysql.cursors.DictCursor, autocommit=False,
    )

    try:
        with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
            # ========================================================
            # PARTE 1: CATEGORIAS (PRODUTO_GRUPO → categories)
            # ========================================================
            print("\n[1/3] Categorias")
            fb_cur = fb_con.cursor()
            fb_cur.execute("SELECT CODIGO, DESCRICAO FROM PRODUTO_GRUPO ORDER BY CODIGO")
            grupos = fb_cur.fetchall()
            stats["categorias_lidas"] = len(grupos)
            print(f"  Lidos PRODUTO_GRUPO: {stats['categorias_lidas']}")

            # category lookup já existente (UK biz+name)
            cat_lookup = {}
            with my_con.cursor() as cur:
                cur.execute("SELECT id, name FROM categories WHERE business_id=%s AND category_type='product'",
                            (args.target_business,))
                for r in cur.fetchall():
                    cat_lookup[str(r["name"]).upper()] = int(r["id"])

            # Mapa CODIGO Delphi → category_id oimpresso
            grupo_to_cat: dict[str, int] = {}
            with my_con.cursor() as wcur:
                for grupo in grupos:
                    desc = normalize_str(grupo[1], 191)
                    if not desc:
                        continue
                    codigo = str(grupo[0]).strip()
                    key = desc.upper()
                    if key in cat_lookup:
                        grupo_to_cat[codigo] = cat_lookup[key]
                        continue
                    # Insert nova categoria
                    try:
                        wcur.execute(
                            "INSERT INTO categories (name, business_id, short_code, parent_id, created_by, category_type, created_at, updated_at) "
                            "VALUES (%s, %s, %s, 0, %s, 'product', NOW(), NOW())",
                            (desc, args.target_business, codigo[:50], args.created_by),
                        )
                        new_id = wcur.lastrowid
                        grupo_to_cat[codigo] = new_id
                        cat_lookup[key] = new_id
                        stats["categorias_inseridas"] += 1
                    except Exception as e:
                        print(f"  [warn] cat fail {codigo}: {e}")
            my_con.commit()
            print(f"  Categorias inseridas: {stats['categorias_inseridas']}")

            # UPDATE products.category_id baseado no CODPRODUTO_GRUPO Delphi
            fb_cur.execute("SELECT CODIGO, CODPRODUTO_GRUPO FROM PRODUTO WHERE ATIVO='S'")
            updated = 0
            with my_con.cursor() as wcur:
                for prod_row in fb_cur.fetchall():
                    sku = str(prod_row[0]).strip()
                    cod_grupo = str(prod_row[1] or "").strip()
                    cat_id = grupo_to_cat.get(cod_grupo)
                    if not cat_id or not sku:
                        continue
                    wcur.execute(
                        "UPDATE products SET category_id=%s, updated_at=NOW() WHERE business_id=%s AND sku=%s",
                        (cat_id, args.target_business, sku),
                    )
                    updated += wcur.rowcount
                    if updated % 500 == 0:
                        my_con.commit()
                        print(f"    ... category_id update {updated}", flush=True)
            my_con.commit()
            stats["produtos_atualizados_cat"] = updated
            print(f"  Products com category_id atualizados: {updated}")
            fb_cur.close()

            # ========================================================
            # PARTE 2: TABELAS DE PREÇO (PRODUTO_PRECO → variation_group_prices)
            # ========================================================
            print("\n[2/3] Tabelas de Preço")
            # Cria selling_price_groups "Tabela <TIPO>" pra cada TIPO único
            fb_cur = fb_con.cursor()
            fb_cur.execute("SELECT DISTINCT TIPO FROM PRODUTO_PRECO WHERE TIPO IS NOT NULL ORDER BY TIPO")
            tipos = [r[0] for r in fb_cur.fetchall()]
            tipo_to_group: dict[int, int] = {}
            tipo_names_map = {1: "MECÂNICA", 2: "FABRICANTE"}  # Hardcoded Martinho

            with my_con.cursor() as wcur:
                wcur.execute("SELECT id, name FROM selling_price_groups WHERE business_id=%s",
                             (args.target_business,))
                group_lookup = {str(r["name"]).upper(): int(r["id"]) for r in wcur.fetchall()}

                for tipo in tipos:
                    nome = tipo_names_map.get(int(tipo), f"TABELA {int(tipo)}")
                    if nome.upper() in group_lookup:
                        tipo_to_group[int(tipo)] = group_lookup[nome.upper()]
                        continue
                    wcur.execute(
                        "INSERT INTO selling_price_groups (name, business_id, is_active, created_at, updated_at) "
                        "VALUES (%s, %s, 1, NOW(), NOW())",
                        (nome, args.target_business),
                    )
                    tipo_to_group[int(tipo)] = wcur.lastrowid
                    stats["selling_groups_inseridos"] += 1
            my_con.commit()
            print(f"  Tipos: {tipos} → groups: {tipo_to_group}")

            # Pre-load variations por sku (mesmo lookup do enrich básico)
            with my_con.cursor() as rcur:
                rcur.execute(
                    "SELECT p.sku, v.id AS variation_id FROM products p "
                    "JOIN variations v ON v.product_id=p.id WHERE p.business_id=%s",
                    (args.target_business,),
                )
                sku_to_var = {str(r["sku"]).strip(): int(r["variation_id"])
                               for r in rcur.fetchall() if r["sku"]}
            print(f"  Variations indexadas: {len(sku_to_var)}")

            # Lê PRODUTO_PRECO e insere
            fb_cur.execute(
                "SELECT CODPRODUTO, TIPO, VALOR FROM PRODUTO_PRECO "
                "WHERE VALOR > 0 AND TIPO IS NOT NULL ORDER BY CODPRODUTO"
            )
            with my_con.cursor() as wcur:
                batch = 0
                for r in fb_cur.fetchall():
                    stats["precos_lidos"] += 1
                    sku = str(r[0]).strip()
                    tipo = int(r[1]) if r[1] is not None else 0
                    valor = normalize_decimal(r[2])
                    var_id = sku_to_var.get(sku)
                    group_id = tipo_to_group.get(tipo)
                    if not var_id or not group_id or valor <= 0:
                        continue
                    # Idempotente: deleta existing
                    wcur.execute(
                        "DELETE FROM variation_group_prices WHERE variation_id=%s AND price_group_id=%s",
                        (var_id, group_id),
                    )
                    wcur.execute(
                        "INSERT INTO variation_group_prices "
                        "(variation_id, price_group_id, price_type, price_inc_tax, created_at, updated_at) "
                        "VALUES (%s, %s, 'fixed', %s, NOW(), NOW())",
                        (var_id, group_id, valor),
                    )
                    stats["precos_inseridos"] += 1
                    batch += 1
                    if batch >= 500:
                        my_con.commit()
                        print(f"    ... precos {stats['precos_inseridos']}", flush=True)
                        batch = 0
            my_con.commit()
            print(f"  variation_group_prices: {stats['precos_inseridos']}")
            fb_cur.close()

            # ========================================================
            # PARTE 3: FLAGS products (not_for_selling=0)
            # ========================================================
            print("\n[3/3] Flags products")
            with my_con.cursor() as wcur:
                wcur.execute(
                    "UPDATE products SET not_for_selling=0 WHERE business_id=%s AND not_for_selling IS NULL",
                    (args.target_business,),
                )
                stats["produtos_flag_atualizados"] = wcur.rowcount
            my_con.commit()
            print(f"  Products not_for_selling=0: {stats['produtos_flag_atualizados']}")

    except Exception as e:
        my_con.rollback()
        print(f"\n[ERRO] {e}", file=sys.stderr)
        raise
    finally:
        my_con.close()

    print("\n== Relatório FINAL ==")
    for k, v in stats.items():
        print(f"  {k:35s}: {v}")
    print(f"[OK] Enricher COMPLETO concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
