"""
Fase 6 — Importer PRODUTO Delphi WR Comercial → products + product_variations + variations + variation_location_details.

Lê tabela PRODUTO do Firebird de um cliente OfficeImpresso e popula `products`
em business alvo do oimpresso (Laravel/MySQL). Pareada com import-estoque.py
(que atualiza qty_available depois) e import-compras.py (purchase_lines).

UPSERT idempotente via (business_id, officeimpresso_codigo) — `products.officeimpresso_codigo`
preserva `PRODUTO.CODIGO` Delphi. Re-rodar = no-op pra rows existentes, update
pra mudanças, insert pra novas.

Estoque INICIAL = 0 em variation_location_details. `import-estoque.py` atualiza
qty_available depois (separação de fases).

Estrutura UltimatePOS gerada (4 tabelas):
   products            : id, name, business_id, type='single', unit_id, sku, ...
   product_variations  : id, product_id, name='DUMMY', is_dummy=1
   variations          : id, product_id, product_variation_id, name='DUMMY', sub_sku
   variation_location_details: id, product_id, product_variation_id, variation_id,
                               location_id=1, qty_available=0

Multi-tenant Tier 0 (ADR 0093): impõe `business_id` em todo INSERT/UPDATE.

Adapter por versão Firebird (v1404 Martinho → v1474 Zoom canônica):
   - Lê cols presentes via `SELECT FIRST 1 *` em runtime
   - Cols ausentes vs canônica viram NULL no INSERT (não quebra)

PII redaction (audit JSON): nenhum campo PII em PRODUTO (texto livre só).

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-produtos.py --alias MartinhoServidor --target-business 164

    # Local Laragon (Herd dev)
    python import-produtos.py --alias MartinhoServidor --target-business 164 --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-produtos.py --alias MartinhoServidor --target-business 164 --target prod --confirm

Refs:
    - memory/reference/migracao-officeimpresso-pattern.md §Fase 6
    - ADR 0093 — Multi-tenant Tier 0
    - import-vendas.py / import-financeiro.py (pattern UPSERT canônico)
"""

from __future__ import annotations

import argparse
import json
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

from lib.firebird_reader import firebird_connect, query  # noqa: E402
from lib import sync_checkpoint as sc  # noqa: E402

try:
    import pymysql
    import pymysql.cursors
except ImportError:
    pymysql = None  # type: ignore

IMPORTER_VERSION = "0.2.0"  # bump: --delta-since-last-sync
LEGACY_SOURCE = "wr-comercial-delphi"
SYNC_TYPE_DEFAULT = "produtos"

# Cols canônicas PRODUTO (Martinho v1404 — 200+ cols, pegamos só as relevantes).
# Adapter detecta ausentes em runtime — viram NULL no SELECT.
PRODUTO_COLS = [
    "CODIGO",              # → products.officeimpresso_codigo (chave UPSERT)
    "DESCRICAO",           # → products.name
    "DESCRICAO_NFE",       # → products.product_description (se col existir)
    "CODIGOEAN",           # → SKU + variations.sub_sku
    "VALOR_COMPRA",        # → variations.default_purchase_price
    "VALOR",               # → variations.default_sell_price
    "CUSTO",               # → variations.dpp_inc_tax (preço compra c/ impostos)
    "UNIDADE",             # → products.unit_id (default 1)
    "CODNF_NCM",           # → products.ncm
    "CODNF_CEST",          # → products.cest
    "ATIVO",               # → products.is_inactive (inverso — ATIVO='S' → is_inactive=0)
    "OIMPRESSO_CODIGO",    # metadata bridge oimpresso novo
    "OIMPRESSO_DT_ALTERACAO",  # metadata bridge oimpresso novo
    "DT_ALTERACAO",        # metadata audit
    "PESO_LIQUIDO",        # → products.weight (se col existir)
    "PESO_BRUTO",          # → metadata
    "ESTOQUE_MIN",         # → products.alert_quantity (alerta estoque mínimo)
    "MARCA",               # metadata (brand_id ficará NULL — Wagner classifica depois)
    "GRUPO",               # metadata (category_id ficará NULL)
    "SUBGRUPO",            # metadata (sub_category_id ficará NULL)
    "OBSERVACAO",          # → products.product_description fallback
]


def get_existing_cols(con, table: str) -> set[str]:
    """Detecta cols realmente presentes na versão (adapter)."""
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def build_adapted_select(cols_presentes: set[str], cols_canonicas: list[str]) -> str:
    """Gera SELECT com CAST(NULL AS VARCHAR(1)) AS col pra cols ausentes."""
    parts = []
    for c in cols_canonicas:
        if c in cols_presentes:
            parts.append(f"P.{c}")
        else:
            parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
    return ", ".join(parts)


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


def derive_sku(produto: dict, legacy_id: str) -> str:
    """SKU prioriza CODIGOEAN; fallback CODIGO Delphi (LEG-xxx)."""
    ean = normalize_str(produto.get("CODIGOEAN"), 191)
    if ean and len(ean) >= 6:
        return ean
    return f"LEG-{legacy_id}"[:191]


def derive_is_inactive(produto: dict) -> int:
    """ATIVO='S' → is_inactive=0 (ativo). ATIVO='N' → is_inactive=1."""
    ativo = (produto.get("ATIVO") or "S").upper().strip()
    return 0 if ativo == "S" else 1


def map_produto_to_product(
    produto: dict,
    business_id: int,
    unit_default: int,
    location_default: int,
    created_by: int,
) -> tuple[dict, dict, dict, dict, dict]:
    """PRODUTO Delphi → tuple(products, product_variations, variations, vld, audit).

    Retorna 5 dicts (1 produto + 1 variation_template + 1 variation + 1 vld + audit).
    """
    legacy_id = str(produto["CODIGO"]).strip()
    name = normalize_str(produto.get("DESCRICAO"), 191) or f"Produto {legacy_id}"
    sku = derive_sku(produto, legacy_id)

    valor_compra = normalize_decimal(produto.get("VALOR_COMPRA"))
    valor_venda = normalize_decimal(produto.get("VALOR"))
    custo = normalize_decimal(produto.get("CUSTO"))

    # dpp_inc_tax (compra com impostos): se CUSTO > 0 usa CUSTO senão VALOR_COMPRA
    dpp_inc_tax = custo if custo > 0 else valor_compra

    # NCM/CEST — campos opcionais em UltimatePOS
    ncm = normalize_str(produto.get("CODNF_NCM"), 20)
    cest = normalize_str(produto.get("CODNF_CEST"), 20)

    # Alerta estoque mínimo (default 0)
    alert_qty = normalize_decimal(produto.get("ESTOQUE_MIN"))

    # Description (DESCRICAO_NFE prioritário sobre OBSERVACAO)
    description = (
        normalize_str(produto.get("DESCRICAO_NFE"), 1000)
        or normalize_str(produto.get("OBSERVACAO"), 1000)
    )

    is_inactive = derive_is_inactive(produto)

    # Products row
    product_data = {
        "business_id": business_id,
        "name": name,
        "type": "single",
        "unit_id": unit_default,
        "brand_id": None,
        "category_id": None,
        "sub_category_id": None,
        "tax": None,
        "tax_type": "exclusive",
        "enable_stock": 1,
        "alert_quantity": alert_qty,
        "sku": sku,
        "barcode_type": "C128",  # default UltimatePOS
        "product_description": description,
        "created_by": created_by,
        "officeimpresso_codigo": legacy_id,
        # NCM/CEST opcionais (podem não existir no schema, controller filtra)
        "ncm": ncm,
        "cest": cest,
        "is_inactive": is_inactive,
    }

    # product_variations row (DUMMY pra type='single')
    pv_data = {
        "name": "DUMMY",
        "is_dummy": 1,
        # product_id é resolvido no momento do INSERT
    }

    # variations row (DUMMY pra type='single')
    var_data = {
        "name": "DUMMY",
        "sub_sku": sku,
        "default_purchase_price": valor_compra,
        "dpp_inc_tax": dpp_inc_tax,
        "profit_percent": 0,
        "default_sell_price": valor_venda,
        "sell_price_inc_tax": valor_venda,  # exclusive — igual default
    }

    # variation_location_details row (qty=0 — import-estoque atualiza depois)
    vld_data = {
        "product_id": None,             # resolved at INSERT time
        "product_variation_id": None,   # resolved at INSERT time
        "variation_id": None,           # resolved at INSERT time
        "location_id": location_default,
        "qty_available": 0,
    }

    audit = {
        "legacy_id": legacy_id,
        "name": name,
        "sku": sku,
        "ean_present": bool(normalize_str(produto.get("CODIGOEAN"))),
        "valor_compra": valor_compra,
        "valor_venda": valor_venda,
        "custo": custo,
        "ncm": ncm,
        "cest": cest,
        "ativo_delphi": normalize_str(produto.get("ATIVO")),
        "is_inactive_resolved": is_inactive,
        "marca_delphi": normalize_str(produto.get("MARCA")),
        "grupo_delphi": normalize_str(produto.get("GRUPO")),
        "subgrupo_delphi": normalize_str(produto.get("SUBGRUPO")),
        "oimpresso_codigo_bridge": normalize_str(produto.get("OIMPRESSO_CODIGO")),
        "imported_at_iso": datetime.utcnow().isoformat() + "Z",
        "importer_version": IMPORTER_VERSION,
    }

    return product_data, pv_data, var_data, vld_data, audit


def sql_value(v) -> str:
    """Escape Python value pra literal SQL (apenas dry-run preview)."""
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime):
        return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    s = str(v).replace("'", "''")
    return "'" + s + "'"


def emit_insert_sql(table: str, data: dict) -> str:
    cols = [c for c, v in data.items() if v is not None]
    vals = ", ".join(sql_value(data[c]) for c in cols)
    return (
        f"INSERT INTO {table} ({', '.join(cols)}, created_at, updated_at) "
        f"VALUES ({vals}, NOW(), NOW());"
    )


def get_writable_cols(con, table: str) -> set[str]:
    """Lê cols REAIS da tabela MySQL alvo. Filtra INSERT pra cols ausentes
    no schema (NCM/CEST/is_inactive podem não existir conforme deploy).
    """
    with con.cursor() as cur:
        cur.execute(f"SELECT * FROM {table} LIMIT 0")
        return {d[0] for d in cur.description}


def filter_to_schema(data: dict, cols_reais: set[str]) -> dict:
    """Remove keys que não existem no schema MySQL alvo (proteção contra drift)."""
    return {k: v for k, v in data.items() if k in cols_reais}


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias Firebird HKCU (ex MartinhoServidor)")
    parser.add_argument("--target-business", type=int, required=True, help="business_id alvo")
    parser.add_argument(
        "--target",
        choices=["dry-run", "local", "prod"],
        default="dry-run",
    )
    parser.add_argument("--limit", type=int, default=0, help="Limite rows (0 = sem limite)")
    parser.add_argument("--batch-size", type=int, default=500, help="Commits per N rows (local/prod)")
    parser.add_argument("--unit-default", type=int, default=1, help="unit_id default pra products (1)")
    parser.add_argument("--location-default", type=int, default=1, help="location_id default pra VLD (1)")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--created-by", type=int, default=int(os.environ.get("CREATED_BY", "1")))
    parser.add_argument("--confirm", action="store_true", help="Obrigatório pra --target prod")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    parser.add_argument("--only-ativo", action="store_true", help="Filtra ATIVO='S' (ignora inativos)")
    parser.add_argument(
        "--delta-since-last-sync",
        action="store_true",
        help="Daemon dual-sync — lê apenas rows com DT_ALTERACAO > sync_checkpoint.last_sync_at",
    )
    parser.add_argument(
        "--sync-type",
        default=SYNC_TYPE_DEFAULT,
        help=f"Identificador sync_checkpoint (default '{SYNC_TYPE_DEFAULT}')",
    )
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm explícito (segurança)", file=sys.stderr)
        return 2

    print(f"== Importer PRODUTO v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird       : {args.alias}")
    print(f"  business_id alvo     : {args.target_business}")
    print(f"  Target MySQL         : {args.target}")
    print(f"  unit_id default      : {args.unit_default}")
    print(f"  location_id default  : {args.location_default}")
    if args.limit:
        print(f"  Limit rows           : {args.limit}")
    if args.only_ativo:
        print(f"  Filtro ATIVO='S'     : sim")

    stats = {
        "lidos": 0,
        "inserts_products": 0,
        "updates_products": 0,
        "inserts_variations": 0,
        "inserts_vld": 0,
        "skipped_inativo": 0,
        "skipped_sem_codigo": 0,
        "errors": 0,
        "com_ean": 0,
        "sem_ean": 0,
        "com_ncm": 0,
        "sem_ncm": 0,
    }

    audit_records: list[dict] = []
    sample_inserts: list[str] = []
    dry_run_lines: list[str] = [
        f"-- Generated by import-produtos v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        "",
        "-- Idempotência: real importer faz SELECT por (business_id, officeimpresso_codigo) +",
        "-- UPDATE OU INSERT manual + cria product_variations + variations + VLD em cascata.",
        "-- Este preview emite INSERT preview de products apenas (1 row por produto).",
        "",
    ]

    # Delta-since-last-sync — lê checkpoint MySQL ANTES de Firebird
    delta_since: datetime | None = None
    delta_active = False
    if args.delta_since_last_sync and args.target in ("local", "prod"):
        if pymysql is None:
            print("[ERRO] pymysql necessário pra --delta-since-last-sync", file=sys.stderr)
            return 3
        sc_con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port,
            user=args.mysql_user, password=args.mysql_password,
            database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
        try:
            delta_since = sc.read_last_sync_at(sc_con, args.target_business, args.sync_type)
            sc.mark_running(sc_con, args.target_business, args.sync_type)
            sc_con.commit()
        finally:
            sc_con.close()
        delta_active = delta_since is not None
        if delta_active:
            print(f"[delta] last_sync_at = {delta_since} — filtrando DT_ALTERACAO > este valor")
        else:
            print("[delta] checkpoint inexistente — FULL SYNC (próxima rodada será delta)")

    print("\n[Firebird] Conectando...")
    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        cols_existentes = get_existing_cols(fb_con, "PRODUTO")
        cols_ausentes = [c for c in PRODUTO_COLS if c not in cols_existentes]
        print(f"[Adapter] PRODUTO cols presentes: {len(cols_existentes)} · canônicas pedidas: {len(PRODUTO_COLS)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (viram NULL): {cols_ausentes}")

        # Delta filter — DT_ALTERACAO ausente em algumas versões → fallback FULL SYNC com warn
        has_dt_alteracao = "DT_ALTERACAO" in cols_existentes
        if delta_active and not has_dt_alteracao:
            print(
                "[delta WARN] PRODUTO não tem DT_ALTERACAO — fallback FULL SYNC",
                file=sys.stderr,
            )
            delta_active = False

        # Query principal
        where_parts = []
        if args.only_ativo and "ATIVO" in cols_existentes:
            where_parts.append("P.ATIVO = 'S'")
        if delta_active and delta_since is not None:
            where_parts.append("P.DT_ALTERACAO > ?")
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        select_clause = build_adapted_select(cols_existentes, PRODUTO_COLS)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"""
            SELECT {first_clause} {select_clause}
            FROM PRODUTO P
            {where_clause}
            ORDER BY P.CODIGO
        """
        print(f"\n[Query PRODUTO] {sql.strip()[:200]}...")
        cur = fb_con.cursor()
        query_params: tuple = ()
        if delta_active and delta_since is not None:
            query_params = (sc.format_firebird_timestamp(delta_since),)
        cur.execute(sql, query_params)
        col_names = [d[0] for d in cur.description]

        # MySQL writer
        con = None
        cols_reais_products: set[str] = set()
        cols_reais_pv: set[str] = set()
        cols_reais_variations: set[str] = set()
        cols_reais_vld: set[str] = set()
        if args.target in ("local", "prod"):
            if pymysql is None:
                print("[ERRO] pymysql não instalado: pip install pymysql", file=sys.stderr)
                return 3
            con = pymysql.connect(
                host=args.mysql_host, port=args.mysql_port,
                user=args.mysql_user, password=args.mysql_password,
                database=args.mysql_database, charset="utf8mb4",
                cursorclass=pymysql.cursors.DictCursor, autocommit=False,
            )
            cols_reais_products = get_writable_cols(con, "products")
            cols_reais_pv = get_writable_cols(con, "product_variations")
            cols_reais_variations = get_writable_cols(con, "variations")
            cols_reais_vld = get_writable_cols(con, "variation_location_details")
            print(f"[Schema] products cols reais: {len(cols_reais_products)}")
            print(f"[Schema] product_variations cols reais: {len(cols_reais_pv)}")
            print(f"[Schema] variations cols reais: {len(cols_reais_variations)}")
            print(f"[Schema] variation_location_details cols reais: {len(cols_reais_vld)}")

        try:
            batch_count = 0
            for row in cur:
                stats["lidos"] += 1
                produto = dict(zip(col_names, row))

                if produto.get("CODIGO") is None:
                    stats["skipped_sem_codigo"] += 1
                    continue

                product_data, pv_data, var_data, vld_data, audit = map_produto_to_product(
                    produto, args.target_business, args.unit_default,
                    args.location_default, args.created_by,
                )

                if audit["ean_present"]:
                    stats["com_ean"] += 1
                else:
                    stats["sem_ean"] += 1
                if audit["ncm"]:
                    stats["com_ncm"] += 1
                else:
                    stats["sem_ncm"] += 1

                if len(audit_records) < 500:
                    audit_records.append(audit)

                if args.target == "dry-run":
                    sql_str = emit_insert_sql("products", product_data)
                    if len(sample_inserts) < 5:
                        sample_inserts.append(sql_str)
                    dry_run_lines.append(
                        f"-- PRODUTO {audit['legacy_id']} · SKU={audit['sku']} · ATIVO={audit['ativo_delphi']}"
                    )
                    dry_run_lines.append(sql_str)
                    dry_run_lines.append("-- (segue product_variations + variations + variation_location_details em cascata)")
                    dry_run_lines.append("")
                    stats["inserts_products"] += 1
                else:
                    assert con is not None
                    # Filter pra cols REAIS do schema (defensivo contra drift)
                    product_filtered = filter_to_schema(product_data, cols_reais_products)
                    product_filtered = {k: v for k, v in product_filtered.items() if v is not None}

                    with con.cursor() as wcur:
                        # 1. UPSERT products
                        wcur.execute(
                            "SELECT id FROM products WHERE business_id=%s AND officeimpresso_codigo=%s LIMIT 1",
                            (args.target_business, str(audit["legacy_id"])),
                        )
                        existing = wcur.fetchone()
                        if existing:
                            product_id = int(existing["id"])
                            update_fields = {
                                k: v for k, v in product_filtered.items()
                                if k not in ("business_id", "officeimpresso_codigo", "created_by", "type")
                            }
                            if update_fields:
                                set_clause = ", ".join(f"{k}=%s" for k in update_fields)
                                wcur.execute(
                                    f"UPDATE products SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                    (*update_fields.values(), product_id),
                                )
                            stats["updates_products"] += 1
                        else:
                            cols = list(product_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO products ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(product_filtered.values()),
                            )
                            product_id = wcur.lastrowid
                            stats["inserts_products"] += 1

                        # 2. UPSERT product_variations (DUMMY pra type='single')
                        pv_filtered = filter_to_schema(pv_data, cols_reais_pv)
                        pv_filtered["product_id"] = product_id
                        wcur.execute(
                            "SELECT id FROM product_variations WHERE product_id=%s LIMIT 1",
                            (product_id,),
                        )
                        existing_pv = wcur.fetchone()
                        if existing_pv:
                            product_variation_id = int(existing_pv["id"])
                        else:
                            cols = list(pv_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO product_variations ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(pv_filtered.values()),
                            )
                            product_variation_id = wcur.lastrowid
                            stats["inserts_variations"] += 1

                        # 3. UPSERT variations
                        var_filtered = filter_to_schema(var_data, cols_reais_variations)
                        var_filtered["product_id"] = product_id
                        var_filtered["product_variation_id"] = product_variation_id
                        wcur.execute(
                            "SELECT id FROM variations WHERE product_id=%s AND product_variation_id=%s LIMIT 1",
                            (product_id, product_variation_id),
                        )
                        existing_var = wcur.fetchone()
                        if existing_var:
                            variation_id = int(existing_var["id"])
                            # Update prices só
                            price_fields = {
                                k: v for k, v in var_filtered.items()
                                if k in ("default_purchase_price", "dpp_inc_tax", "default_sell_price",
                                         "sell_price_inc_tax", "profit_percent", "sub_sku")
                            }
                            if price_fields:
                                set_clause = ", ".join(f"{k}=%s" for k in price_fields)
                                wcur.execute(
                                    f"UPDATE variations SET {set_clause}, updated_at=NOW() WHERE id=%s",
                                    (*price_fields.values(), variation_id),
                                )
                        else:
                            cols = list(var_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO variations ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(var_filtered.values()),
                            )
                            variation_id = wcur.lastrowid

                        # 4. UPSERT variation_location_details (qty=0 inicial — import-estoque atualiza)
                        vld_filtered = filter_to_schema(vld_data, cols_reais_vld)
                        vld_filtered["product_id"] = product_id
                        vld_filtered["product_variation_id"] = product_variation_id
                        vld_filtered["variation_id"] = variation_id
                        # CINTO+SUSPENSÓRIO Tier 0 (ADR 0093): SELECT com JOIN products + business_id
                        # explícito previne contaminação cross-business (incidente 2026-05-14 ROTA LIVRE).
                        wcur.execute(
                            "SELECT vld.id FROM variation_location_details vld "
                            "INNER JOIN products p ON p.id = vld.product_id "
                            "WHERE p.business_id = %s "
                            "AND vld.product_id=%s AND vld.variation_id=%s AND vld.location_id=%s "
                            "LIMIT 1",
                            (args.target_business, product_id, variation_id, args.location_default),
                        )
                        existing_vld = wcur.fetchone()
                        if not existing_vld:
                            cols = list(vld_filtered.keys())
                            placeholders = ", ".join(["%s"] * len(cols))
                            wcur.execute(
                                f"INSERT INTO variation_location_details ({', '.join(cols)}, created_at, updated_at) "
                                f"VALUES ({placeholders}, NOW(), NOW())",
                                tuple(vld_filtered.values()),
                            )
                            stats["inserts_vld"] += 1

                    batch_count += 1
                    if batch_count >= args.batch_size:
                        con.commit()
                        print(f"  [batch] commited {batch_count} · total lidos: {stats['lidos']}")
                        batch_count = 0

                if stats["lidos"] % 500 == 0:
                    print(f"  ... lidos {stats['lidos']}")

            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count}")
            if con and args.delta_since_last_sync:
                try:
                    sc.mark_success(
                        con,
                        args.target_business,
                        args.sync_type,
                        rows_processed=stats["inserts_products"] + stats["updates_products"],
                    )
                    con.commit()
                except Exception as e:
                    print(f"[sync_checkpoint] WARN mark_success: {e!r}", file=sys.stderr)

        except Exception as e:
            if con:
                con.rollback()
                print("\n[ERRO] Rollback MySQL", file=sys.stderr)
                if args.delta_since_last_sync:
                    try:
                        sc.mark_failed(con, args.target_business, args.sync_type, error_msg=repr(e))
                        con.commit()
                    except Exception as e2:
                        print(f"[sync_checkpoint] WARN mark_failed: {e2!r}", file=sys.stderr)
            stats["errors"] += 1
            print(f"Exceção: {e!r}", file=sys.stderr)
            raise
        finally:
            cur.close()
            if con:
                con.close()

    # Output dry-run SQL + audit JSON
    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    biz_suffix = f"biz{args.target_business}"

    if args.target == "dry-run":
        sql_path = out / f"dry-run-produtos-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    audit_path = out / f"audit-produtos-{biz_suffix}-{ts}.json"
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "filter": {"limit": args.limit, "only_ativo": args.only_ativo},
        "adapter": {
            "cols_canonicas_pedidas": len(PRODUTO_COLS),
            "cols_ausentes_v_canonica": cols_ausentes,
        },
        "stats": stats,
        "sample_inserts": sample_inserts,
        "records_total": len(audit_records),
        "records_sample_first_500": audit_records[:500],
    }
    audit_path.write_text(
        json.dumps(audit_summary, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  PRODUTO lidos          : {stats['lidos']}")
    print(f"  com EAN (CODIGOEAN)    : {stats['com_ean']}")
    print(f"  sem EAN (SKU=LEG-xxx)  : {stats['sem_ean']}")
    print(f"  com NCM                : {stats['com_ncm']}")
    print(f"  sem NCM                : {stats['sem_ncm']}")
    print(f"  Skipped sem CODIGO     : {stats['skipped_sem_codigo']}")
    print(f"  Products INSERT        : {stats['inserts_products']}")
    print(f"  Products UPDATE        : {stats['updates_products']}")
    print(f"  Variations INSERT      : {stats['inserts_variations']}")
    print(f"  VLD INSERT (qty=0)     : {stats['inserts_vld']}")
    print(f"  Errors                 : {stats['errors']}")
    print(f"\n[OK] Produtos concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
