"""
Importer Python: PRODUTO Delphi WR Comercial → products + variations + categories UltimatePOS.

Migra catálogo de produtos do legacy Firebird pra schema multi-tenant oimpresso:
- PRODUTO            → products (1:1)
- PRODUTO_CATEGORIA  → categories (DISTINCT auto-insert se faltar)
- PRODUTO_BARRAS     → products.sku/variations.sub_sku (EAN/SKU primary + alternativos)
- PRODUTO_SUBUNIDADE → JSON metadata (secondary_unit, conversion) — não promove a variation pro MVP

Schema alvo (Laravel 13.6 UltimatePOS):
  products: id, legacy_id, name, business_id, type[single|variable|modifier],
            unit_id, brand_id?, category_id?, sub_category_id?, tax?,
            tax_type[inclusive|exclusive], enable_stock, alert_quantity,
            sku, barcode_type, created_by, timestamps

  variations: id, name, product_id, sub_sku?, product_variation_id,
              default_purchase_price, dpp_inc_tax, profit_percent,
              default_sell_price, sell_price_inc_tax, timestamps, softDeletes

  product_variations: id, name, product_id, is_dummy, timestamps
  categories: id, name, business_id, short_code, parent_id, created_by,
              category_type='product', slug, timestamps

Tipo Inertia:
- PRODUTO Delphi não tem variação grade no Martinho v1404 (oficina caçambas) →
  produtos viram type='single' + 1 variation dummy is_dummy=1 + 1 product_variation dummy.
- Quando PRODUTO_SUBUNIDADE tiver fator≠1, registra em metadata mas mantém single.

Idempotência:
  Chave natural = (business_id, legacy_id=PRODUTO.CODIGO Delphi).
  Lookup via índice composto criado pela migration 2026_05_13_180001_add_legacy_id_to_products.

Multi-tenant Tier 0 (ADR 0093):
  Todas queries products/variations/categories filtram business_id=target_business.
  NUNCA misturar cliente A com cliente B no mesmo run.

Modes:
  --target dry-run  → gera SQL em output/dry-run-produtos-<ts>.sql (não conecta MySQL)
  --target local    → escreve no MySQL local Laragon (oimpresso db)
  --target prod     → escreve no MySQL Hostinger (exige --confirm explícito)

Batch:
  Commit a cada 1000 produtos. Rollback total em erro de batch.
  Print progresso a cada 1000.

Truncate strings (DEFENSIVE — UltimatePOS varchar limits):
  name → 191 (MySQL utf8mb4 default index limit)
  sku  → 191
  short_code → 191
  slug → 191

Uso:
  python import-produtos.py --alias ServidorMartinho --target-business 4 --target dry-run
  python import-produtos.py --alias ServidorMartinho --target-business 4 --target local
  python import-produtos.py --alias ServidorMartinho --target-business 4 --target prod --confirm

Refs:
  - .claude/agents/migracao-produtos.md          (orquestrador 8-fase)
  - database/migrations/...add_legacy_id_to_products.php
  - scripts/legacy-migration/import-empresas.py  (pattern referência)
  - memory/reference/migracao-officeimpresso-pattern.md
  - ADR 0093 (multi-tenant Tier 0 IRREVOGÁVEL)
"""

from __future__ import annotations

import argparse
import json
import os
import re
import sys
from datetime import datetime
from pathlib import Path

# Força UTF-8 stdout no Windows
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

sys.path.insert(0, str(Path(__file__).parent))

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

from lib.firebird_reader import firebird_connect, query  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.1.0"
LEGACY_SOURCE = "wr-comercial-delphi"
BATCH_SIZE = 1000

# UltimatePOS varchar(191) defensivo (utf8mb4 max index)
MAX_NAME = 191
MAX_SKU = 191
MAX_SHORT_CODE = 191
MAX_SLUG = 191


# ---------------------------------------------------------------------------
# Truncate helpers (pattern estabelecido nos importers irmãos)
# ---------------------------------------------------------------------------
def truncate(value, limit):
    if value is None:
        return None
    s = str(value).strip()
    if not s:
        return None
    return s[:limit]


def slugify(name: str) -> str:
    s = re.sub(r"[^a-zA-Z0-9]+", "-", name.lower()).strip("-")
    return s[:MAX_SLUG] or "produto"


# ---------------------------------------------------------------------------
# Firebird readers — defensivos (algumas colunas só existem em versões > N)
# ---------------------------------------------------------------------------
def list_table_columns(con, table_name: str) -> set[str]:
    """Lista colunas de uma tabela Firebird (uppercase, trimmed)."""
    rows = query(
        con,
        "SELECT TRIM(rf.RDB$FIELD_NAME) AS name FROM RDB$RELATION_FIELDS rf "
        "WHERE rf.RDB$RELATION_NAME = ? ORDER BY rf.RDB$FIELD_POSITION",
        (table_name.upper(),),
    )
    return {r["NAME"].upper() if "NAME" in r else r["name"].upper() for r in rows}


def query_produtos_delphi(con, has_categoria: bool, has_barras: bool) -> list[dict]:
    """Lê PRODUTO Firebird. JOINs opcionais conforme presença de tabelas/colunas.

    Retorna lista de dicts com keys uppercase Firebird-style (CODIGO, DESCRICAO etc).
    """
    select_parts = ["p.*"]
    join_parts = []

    if has_categoria:
        select_parts.append("c.DESCRICAO AS CATEGORIA_DESCRICAO")
        select_parts.append("c.CODIGO AS CATEGORIA_CODIGO")
        join_parts.append("LEFT JOIN PRODUTO_CATEGORIA c ON c.CODIGO = p.CODPRODUTO_CATEGORIA")

    sql = (
        f"SELECT {', '.join(select_parts)} "
        f"FROM PRODUTO p "
        f"{' '.join(join_parts)} "
        f"ORDER BY p.CODIGO"
    )
    return query(con, sql)


def query_barras_for_produto(con, codigo: int) -> list[dict]:
    """PRODUTO_BARRAS → lista de EAN/SKU alternativos. Vazio se tabela não existe."""
    try:
        return query(
            con,
            "SELECT CODBARRAS, REFERENCIA FROM PRODUTO_BARRAS WHERE CODPRODUTO = ? ORDER BY CODIGO",
            (codigo,),
        )
    except Exception:
        return []


def query_subunidades_for_produto(con, codigo: int) -> list[dict]:
    """PRODUTO_SUBUNIDADE → lista de unidades secundárias com fator. Vazio se faltar."""
    try:
        return query(
            con,
            "SELECT * FROM PRODUTO_SUBUNIDADE WHERE CODPRODUTO = ? ORDER BY CODIGO",
            (codigo,),
        )
    except Exception:
        return []


# ---------------------------------------------------------------------------
# Mappers
# ---------------------------------------------------------------------------
def pick(row: dict, *keys, default=None):
    """Defensive getter — Firebird upper, dict keys podem variar entre drivers."""
    for k in keys:
        if k in row and row[k] is not None:
            return row[k]
        if k.upper() in row and row[k.upper()] is not None:
            return row[k.upper()]
    return default


def map_categoria_delphi(cat_codigo, cat_descricao, business_id: int, created_by: int) -> dict:
    name = truncate(cat_descricao, MAX_NAME) or f"Legacy {cat_codigo}"
    return {
        "business_id": business_id,
        "name": name,
        "short_code": truncate(str(cat_codigo), MAX_SHORT_CODE),
        "parent_id": 0,
        "created_by": created_by,
        "category_type": "product",
        "description": None,
        "slug": slugify(name),
    }


def map_produto_to_product(
    p: dict,
    business_id: int,
    unit_id: int,
    created_by: int,
    category_id: int | None,
    primary_sku: str | None,
    subunidades: list[dict],
) -> tuple[dict, dict]:
    """PRODUTO Delphi → linha em `products`. Retorna (data, metadata)."""
    legacy_id = str(pick(p, "CODIGO"))
    descricao = pick(p, "DESCRICAO") or pick(p, "DESCRICAO_REDUZIDA") or f"Produto {legacy_id}"
    referencia = pick(p, "REFERENCIA")

    # SKU: primeiro EAN PRODUTO_BARRAS → senão REFERENCIA Delphi → senão "LEG-{codigo}"
    sku = (
        truncate(primary_sku, MAX_SKU)
        or truncate(referencia, MAX_SKU)
        or f"LEG-{legacy_id}"
    )

    # tax_type Delphi não tem default mapping direto → 'exclusive' (mais comum BR)
    # enable_stock: respeita PODE_ALTERAR_ESTOQUE se existir; default 1
    pode_estoque = pick(p, "PODE_ALTERAR_ESTOQUE", "CONTROLA_ESTOQUE")
    enable_stock = 1 if (pode_estoque is None or str(pode_estoque).upper() == "S") else 0

    alert_qty = pick(p, "ESTOQUE_MIN", default=0) or 0
    try:
        alert_qty = float(alert_qty)
    except (ValueError, TypeError):
        alert_qty = 0.0

    product_data = {
        "legacy_id": legacy_id,
        "business_id": business_id,
        "name": truncate(descricao, MAX_NAME),
        "type": "single",  # Martinho v1404 não tem variação grade — MVP single
        "unit_id": unit_id,
        "brand_id": None,
        "category_id": category_id,
        "sub_category_id": None,
        "tax": None,
        "tax_type": "exclusive",
        "enable_stock": enable_stock,
        "alert_quantity": alert_qty,
        "sku": sku,
        "barcode_type": "C128",  # default UltimatePOS
        "created_by": created_by,
    }

    metadata = {
        "delphi_legacy": {
            "codigo": pick(p, "CODIGO"),
            "referencia": referencia,
            "unidade": pick(p, "UNIDADE"),
            "tipo": pick(p, "TIPO", "CODPRODUTO_TIPO"),
            "ncm": pick(p, "NCM"),
            "cest": pick(p, "CEST"),
            "cfop": pick(p, "CFOP"),
            "preco_venda": str(pick(p, "PRECO_VENDA", "VENDA", "PRECO")) if pick(p, "PRECO_VENDA", "VENDA", "PRECO") else None,
            "preco_custo": str(pick(p, "PRECO_CUSTO", "CUSTO")) if pick(p, "PRECO_CUSTO", "CUSTO") else None,
            "estoque": str(pick(p, "ESTOQUE", "ESTOQUE_ATUAL")) if pick(p, "ESTOQUE", "ESTOQUE_ATUAL") else None,
            "estoque_min": pick(p, "ESTOQUE_MIN"),
            "estoque_max": pick(p, "ESTOQUE_MAX"),
            "categoria_codigo": pick(p, "CATEGORIA_CODIGO", "CODPRODUTO_CATEGORIA"),
            "categoria_descricao": pick(p, "CATEGORIA_DESCRICAO"),
        },
        "subunidades": [
            {
                "codigo": pick(s, "CODIGO"),
                "unidade": pick(s, "UNIDADE"),
                "fator": str(pick(s, "FATOR", "FATOR_CONVERSAO")) if pick(s, "FATOR", "FATOR_CONVERSAO") else None,
            }
            for s in subunidades
        ],
        "import_meta": {
            "imported_at_iso": datetime.utcnow().isoformat() + "Z",
            "importer_version": IMPORTER_VERSION,
            "legacy_source": LEGACY_SOURCE,
            "legacy_id": legacy_id,
        },
    }

    # Variation primária (dummy) — espelha preco_venda/custo Delphi
    preco_venda = pick(p, "PRECO_VENDA", "VENDA", "PRECO")
    preco_custo = pick(p, "PRECO_CUSTO", "CUSTO")
    variation_data = {
        "name": "DUMMY",  # convenção UltimatePOS pra type='single'
        "sub_sku": sku,
        "default_purchase_price": float(preco_custo) if preco_custo else None,
        "dpp_inc_tax": float(preco_custo) if preco_custo else 0,
        "profit_percent": 0,
        "default_sell_price": float(preco_venda) if preco_venda else None,
        "sell_price_inc_tax": float(preco_venda) if preco_venda else None,
    }

    return product_data, variation_data, metadata


# ---------------------------------------------------------------------------
# MySQL writers
# ---------------------------------------------------------------------------
def upsert_categoria(cur, cat_data: dict, business_id: int, legacy_codigo) -> int:
    """Lookup por (business_id, short_code=legacy_codigo) — auto-insert se faltar.

    Retorna category_id.
    """
    cur.execute(
        "SELECT id FROM categories "
        "WHERE business_id=%s AND short_code=%s AND category_type='product' "
        "AND deleted_at IS NULL LIMIT 1",
        (business_id, str(legacy_codigo)),
    )
    row = cur.fetchone()
    if row:
        return row["id"]

    cols = list(cat_data.keys())
    placeholders = ", ".join(["%s"] * len(cols))
    cur.execute(
        f"INSERT INTO categories ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({placeholders}, NOW(), NOW())",
        tuple(cat_data.values()),
    )
    return cur.lastrowid


def upsert_product(
    cur,
    product_data: dict,
    variation_data: dict,
    business_id: int,
    legacy_id: str,
) -> tuple[int, str]:
    """UPSERT products + product_variations + variations (dummy chain).

    Retorna (product_id, action) — action ∈ {'inserted', 'updated'}.
    """
    cur.execute(
        "SELECT id FROM products WHERE business_id=%s AND legacy_id=%s LIMIT 1",
        (business_id, legacy_id),
    )
    row = cur.fetchone()

    if row:
        product_id = row["id"]
        update_fields = {
            k: v for k, v in product_data.items()
            if k not in ("business_id", "legacy_id", "created_by") and v is not None
        }
        if update_fields:
            set_clause = ", ".join(f"{k}=%s" for k in update_fields)
            cur.execute(
                f"UPDATE products SET {set_clause}, updated_at=NOW() WHERE id=%s",
                (*update_fields.values(), product_id),
            )
        # Atualiza dummy variation
        cur.execute(
            "SELECT id FROM variations WHERE product_id=%s AND name='DUMMY' "
            "AND deleted_at IS NULL LIMIT 1",
            (product_id,),
        )
        v_row = cur.fetchone()
        if v_row:
            v_update = {k: v for k, v in variation_data.items() if v is not None}
            if v_update:
                set_clause = ", ".join(f"{k}=%s" for k in v_update)
                cur.execute(
                    f"UPDATE variations SET {set_clause}, updated_at=NOW() WHERE id=%s",
                    (*v_update.values(), v_row["id"]),
                )
        return product_id, "updated"

    # INSERT — chain product → product_variation(dummy) → variation(dummy)
    cols = list(product_data.keys())
    placeholders = ", ".join(["%s"] * len(cols))
    cur.execute(
        f"INSERT INTO products ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({placeholders}, NOW(), NOW())",
        tuple(product_data.values()),
    )
    product_id = cur.lastrowid

    cur.execute(
        "INSERT INTO product_variations (name, product_id, is_dummy, created_at, updated_at) "
        "VALUES (%s, %s, 1, NOW(), NOW())",
        ("DUMMY", product_id),
    )
    pv_id = cur.lastrowid

    v_cols = list(variation_data.keys()) + ["product_id", "product_variation_id"]
    v_vals = list(variation_data.values()) + [product_id, pv_id]
    v_placeholders = ", ".join(["%s"] * len(v_cols))
    cur.execute(
        f"INSERT INTO variations ({', '.join(v_cols)}, created_at, updated_at) "
        f"VALUES ({v_placeholders}, NOW(), NOW())",
        tuple(v_vals),
    )

    return product_id, "inserted"


def get_default_unit_id(cur, business_id: int) -> int:
    """Lookup `units` 'Unit' / 'UN' / qualquer pra business_id. Erra se nada existir."""
    cur.execute(
        "SELECT id FROM units WHERE business_id=%s "
        "ORDER BY (CASE WHEN short_name IN ('UN','Un','un') THEN 0 ELSE 1 END), id "
        "LIMIT 1",
        (business_id,),
    )
    row = cur.fetchone()
    if not row:
        raise RuntimeError(
            f"❌ Nenhum `units` row pra business_id={business_id} — "
            "criar via UI antes de importar produtos (UltimatePOS exige unit_id NOT NULL)."
        )
    return row["id"]


# ---------------------------------------------------------------------------
# Dry-run SQL builder
# ---------------------------------------------------------------------------
def sql_value(v):
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace("'", "''") + "'"


def dry_run_product_block(product_data, variation_data, legacy_id, metadata) -> list[str]:
    lines = [
        f"-- PRODUTO legacy_id={legacy_id} → product (UPSERT por business_id+legacy_id)",
        f"-- metadata: {json.dumps(metadata)[:300]}{'...' if len(json.dumps(metadata)) > 300 else ''}",
    ]
    cols = ", ".join(product_data.keys())
    vals = ", ".join(sql_value(v) for v in product_data.values())
    lines.append(
        f"INSERT INTO products ({cols}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW()) "
        f"ON DUPLICATE KEY UPDATE "
        f"name=VALUES(name), sku=VALUES(sku), category_id=VALUES(category_id), "
        f"alert_quantity=VALUES(alert_quantity), enable_stock=VALUES(enable_stock), "
        f"updated_at=NOW();"
    )
    lines.append("-- product_variations + variations (dummy chain) — gerados em runtime")
    lines.append(f"-- variation default_sell_price={variation_data.get('default_sell_price')} "
                 f"default_purchase_price={variation_data.get('default_purchase_price')}")
    lines.append("")
    return lines


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--unit-id", type=int, default=None,
                        help="Force unit_id (senão pega 1º units row do business)")
    parser.add_argument("--limit", type=int, default=None, help="Limit pra smoke (dry-run sample 5)")
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("❌ --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"🚀 Importer Produtos v{IMPORTER_VERSION}")
    print(f"   Alias Firebird       : {args.alias}")
    print(f"   business_id alvo     : {args.target_business}")
    print(f"   Target MySQL         : {args.target}")
    print(f"   Limit                : {args.limit or 'all'}")

    dry_run_lines: list[str] = [
        f"-- Generated by import-produtos v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business_id={args.target_business}",
        "",
    ]
    stats = {"inserts": 0, "updates": 0, "errors": 0, "categories_created": 0}
    category_cache: dict[str, int] = {}  # legacy_codigo → category_id

    # 1) Firebird
    print(f"\n🔌 Conectando Firebird...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        # Detecta presença de tabelas opcionais
        try:
            cols_categoria = list_table_columns(fb_con, "PRODUTO_CATEGORIA")
            has_categoria = len(cols_categoria) > 0
        except Exception:
            has_categoria = False

        try:
            cols_barras = list_table_columns(fb_con, "PRODUTO_BARRAS")
            has_barras = len(cols_barras) > 0
        except Exception:
            has_barras = False

        print(f"   PRODUTO_CATEGORIA presente : {has_categoria}")
        print(f"   PRODUTO_BARRAS presente    : {has_barras}")

        produtos = query_produtos_delphi(fb_con, has_categoria, has_barras)
        total = len(produtos)
        print(f"   PRODUTOS lidos             : {total}")

        if args.limit:
            produtos = produtos[: args.limit]
            print(f"   Limitado a {args.limit} (smoke)")

        # 2) MySQL writer
        con = None
        unit_id = args.unit_id
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("❌ pymysql não instalado", file=sys.stderr)
                return 3
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )
            with con.cursor() as cur:
                if unit_id is None:
                    unit_id = get_default_unit_id(cur, args.target_business)
                print(f"   unit_id resolvido          : {unit_id}")
        else:
            # Dry-run: usa placeholder 1 — SQL gerado terá unit_id=1 (revisar manual)
            unit_id = unit_id or 1
            print(f"   unit_id (dry-run placeholder): {unit_id}")

        try:
            for i, p in enumerate(produtos, 1):
                codigo = pick(p, "CODIGO")
                legacy_id = str(codigo)

                # 2.1) Resolver categoria (auto-insert se faltar)
                category_id = None
                cat_codigo = pick(p, "CATEGORIA_CODIGO", "CODPRODUTO_CATEGORIA")
                if cat_codigo and has_categoria:
                    cat_key = str(cat_codigo)
                    if cat_key in category_cache:
                        category_id = category_cache[cat_key]
                    else:
                        cat_descricao = pick(p, "CATEGORIA_DESCRICAO")
                        cat_data = map_categoria_delphi(
                            cat_codigo, cat_descricao, args.target_business, args.created_by
                        )
                        if args.target in ("local", "prod"):
                            assert con is not None
                            with con.cursor() as cur:
                                before = stats["categories_created"]
                                # upsert_categoria não retorna 'created' flag — checa via SELECT
                                cur.execute(
                                    "SELECT id FROM categories "
                                    "WHERE business_id=%s AND short_code=%s AND category_type='product' "
                                    "AND deleted_at IS NULL LIMIT 1",
                                    (args.target_business, str(cat_codigo)),
                                )
                                existed = cur.fetchone()
                                category_id = upsert_categoria(
                                    cur, cat_data, args.target_business, cat_codigo
                                )
                                if not existed:
                                    stats["categories_created"] += 1
                        else:
                            # dry-run: simula id sequencial
                            category_id = 1000 + len(category_cache)
                            dry_run_lines.append(
                                f"-- CATEGORIA legacy={cat_codigo} → categories (UPSERT por short_code)"
                            )
                            c_cols = ", ".join(cat_data.keys())
                            c_vals = ", ".join(sql_value(v) for v in cat_data.values())
                            dry_run_lines.append(
                                f"INSERT INTO categories ({c_cols}, created_at, updated_at) "
                                f"VALUES ({c_vals}, NOW(), NOW()) "
                                f"ON DUPLICATE KEY UPDATE name=VALUES(name), updated_at=NOW();"
                            )
                            dry_run_lines.append("")
                            stats["categories_created"] += 1
                        category_cache[cat_key] = category_id

                # 2.2) PRODUTO_BARRAS → pega 1º EAN como primary SKU
                barras = query_barras_for_produto(fb_con, codigo) if has_barras else []
                primary_sku = None
                if barras:
                    primary_sku = pick(barras[0], "CODBARRAS") or pick(barras[0], "REFERENCIA")

                # 2.3) PRODUTO_SUBUNIDADE → metadata
                subunidades = query_subunidades_for_produto(fb_con, codigo)

                # 2.4) Map + write
                product_data, variation_data, metadata = map_produto_to_product(
                    p,
                    business_id=args.target_business,
                    unit_id=unit_id,
                    created_by=args.created_by,
                    category_id=category_id,
                    primary_sku=primary_sku,
                    subunidades=subunidades,
                )

                if args.target == "dry-run":
                    dry_run_lines.extend(
                        dry_run_product_block(product_data, variation_data, legacy_id, metadata)
                    )
                    stats["inserts"] += 1
                else:
                    assert con is not None
                    with con.cursor() as cur:
                        _, action = upsert_product(
                            cur, product_data, variation_data, args.target_business, legacy_id
                        )
                        if action == "inserted":
                            stats["inserts"] += 1
                        else:
                            stats["updates"] += 1

                # Commit batch
                if con and i % BATCH_SIZE == 0:
                    con.commit()
                    print(f"   [{i}/{total}] commit batch — "
                          f"ins={stats['inserts']} upd={stats['updates']} cats={stats['categories_created']}")
                elif i % BATCH_SIZE == 0:
                    print(f"   [{i}/{total}] dry-run progress — "
                          f"ins={stats['inserts']} cats={stats['categories_created']}")

            if con:
                con.commit()
        except Exception as e:
            if con:
                con.rollback()
            stats["errors"] += 1
            print(f"❌ Erro: {e!r}", file=sys.stderr)
            raise
        finally:
            if con:
                con.close()

        # Salva dry-run SQL
        if args.target == "dry-run":
            out = Path(args.output_dir)
            out.mkdir(parents=True, exist_ok=True)
            ts = datetime.now().strftime("%Y%m%d-%H%M%S")
            path = out / f"dry-run-produtos-biz{args.target_business}-{ts}.sql"
            path.write_text("\n".join(dry_run_lines), encoding="utf-8")
            print(f"\n💾 SQL salvo: {path}")

    print(f"\n📊 Relatório:")
    print(f"   Inserts          : {stats['inserts']}")
    print(f"   Updates          : {stats['updates']}")
    print(f"   Categories novas : {stats['categories_created']}")
    print(f"   Erros            : {stats['errors']}")
    print(f"✅ Produtos concluído (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
