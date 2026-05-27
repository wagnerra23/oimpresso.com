"""
Fase 5+ — Importer VENDA_PRODUTO Delphi → transaction_sell_lines UltimatePOS.

Lê itens das vendas Delphi e popula linhas em `transaction_sell_lines`. Requer
que `import-vendas.py` (cria transactions.ref_no=CODVENDA) e `import-produtos.py`
(cria products.sku=CODPRODUTO + variation default) tenham rodado antes.

Mapping:
  - VENDA_PRODUTO.CODVENDA → transactions.ref_no → transaction_id
  - VENDA_PRODUTO.CODPRODUTO → products.sku → product_id + variation_id (default)
  - VENDA_PRODUTO.QUANT → quantity
  - VENDA_PRODUTO.VALOR → unit_price (e unit_price_inc_tax — sem tax detalhado)
  - VENDA_PRODUTO.PICMS+PICMSST+IPI → item_tax (somatório simplificado)

Idempotência: DELETE existing rows da transaction antes de re-inserir (replace
set-based). Re-rodar é safe: limpa e regrava — preserva valores atualizados.

Multi-tenant Tier 0 (ADR 0093): transaction_id sempre filtrado por
business_id no lookup. transaction_sell_lines não tem business_id direto.

Uso:
    python import-venda-itens.py --alias "Martinho online" --target-business 164 \\
        --start-date 2026-04-01 --end-date 2026-04-30 --target prod --confirm
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


def normalize_decimal(raw) -> float:
    if raw is None or raw == "":
        return 0.0
    try:
        return float(raw)
    except (ValueError, TypeError):
        return 0.0


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True)
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--start-date", help="VENDA.DT_EMISSAO >= YYYY-MM-DD")
    parser.add_argument("--end-date", help="VENDA.DT_EMISSAO <= YYYY-MM-DD")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
    args = parser.parse_args()

    if args.target == "prod" and not args.confirm:
        print("[ERRO] --target prod requer --confirm", file=sys.stderr)
        return 2

    print(f"== Importer VENDA_PRODUTO → transaction_sell_lines v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird   : {args.alias}")
    print(f"  business_id alvo : {args.target_business}")
    print(f"  Target MySQL     : {args.target}")
    if args.start_date or args.end_date:
        print(f"  Filtro VENDA.DT_EMISSAO: [{args.start_date or '-inf'} .. {args.end_date or '+inf'}]")

    stats = {
        "lidos": 0, "inserts": 0, "skipped_no_transaction": 0,
        "skipped_no_product": 0, "transactions_processadas": 0, "errors": 0,
    }

    if args.target == "dry-run":
        print("\n[Dry-run mode — vou só contar linhas, não conectar MySQL]")

    with firebird_connect(args.alias, password_override=args.firebird_password) as fb_con:
        # Lookups MySQL (pre-load CODVENDA→transaction_id + CODPRODUTO→(product_id, variation_id))
        transaction_lookup: dict[str, int] = {}
        product_lookup: dict[str, tuple[int, int]] = {}

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
                        "SELECT id, ref_no FROM transactions "
                        "WHERE business_id=%s AND ref_no IS NOT NULL",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        ref = str(r["ref_no"])
                        transaction_lookup[ref] = int(r["id"])
                    # Products + 1 variation default por product
                    cur.execute(
                        "SELECT p.id AS product_id, p.sku, v.id AS variation_id "
                        "FROM products p "
                        "LEFT JOIN variations v ON v.product_id = p.id "
                        "WHERE p.business_id=%s",
                        (args.target_business,),
                    )
                    for r in cur.fetchall():
                        sku = str(r["sku"] or "").strip()
                        if sku and r["variation_id"]:
                            # Primeira variation por product (default)
                            if sku not in product_lookup:
                                product_lookup[sku] = (int(r["product_id"]), int(r["variation_id"]))
            finally:
                tmp_con.close()
            print(f"[Transactions lookup] {len(transaction_lookup)} entries")
            print(f"[Products+Variation lookup] {len(product_lookup)} entries")

        # Query VENDA_PRODUTO JOIN VENDA pra filtro de data
        where_parts = []
        params: list = []
        if args.start_date:
            where_parts.append("V.DT_EMISSAO >= ?")
            params.append(args.start_date)
        if args.end_date:
            where_parts.append("V.DT_EMISSAO <= ?")
            params.append(args.end_date + " 23:59:59")
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        # Adapter: detecta colunas reais de VENDA_PRODUTO (versões variam — PRODUTO
        # desnormalizado às vezes ausente em Martinho v1404; VALOR_PRAZO opcional)
        vp_cols_existentes = set()
        try:
            adapter_cur = fb_con.cursor()
            adapter_cur.execute("SELECT FIRST 1 * FROM VENDA_PRODUTO")
            vp_cols_existentes = {d[0] for d in adapter_cur.description or []}
            adapter_cur.close()
        except Exception:
            pass

        # Cols canônicas que tentamos pegar — substitui por NULL quando ausente
        wanted = ["CODIGO", "CODVENDA", "CODPRODUTO", "QUANT", "VALOR",
                  "VALOR_PRAZO", "ACRESCIMO_DESCONTO", "UNIDADE",
                  "VICMS", "VICMSST", "IPI_VIPI"]
        select_parts = []
        for c in wanted:
            if not vp_cols_existentes or c in vp_cols_existentes:
                select_parts.append(f"VP.{c}")
            else:
                select_parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
        select_clause = ", ".join(select_parts)
        sql = (
            f"SELECT {first_clause} {select_clause}, V.DT_EMISSAO "
            f"FROM VENDA_PRODUTO VP "
            f"INNER JOIN VENDA V ON V.CODIGO = VP.CODVENDA "
            f"{where_clause} "
            f"ORDER BY VP.CODVENDA, VP.CODIGO"
        )
        print(f"\n[Query VENDA_PRODUTO] {sql[:240]}...")

        cur = fb_con.cursor()
        cur.execute(sql, tuple(params))
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
            # Agrupa items por venda pra fazer DELETE prévio (replace set-based)
            current_codvenda = None
            current_transaction_id = None
            already_cleared = set()
            batch_count = 0
            BATCH_SIZE = 1000

            for row_tuple in cur:
                stats["lidos"] += 1
                if stats["lidos"] % 1000 == 0:
                    print(f"  ... lidos {stats['lidos']} · INSERTs {stats['inserts']}", flush=True)
                item = dict(zip(col_names, row_tuple))
                codvenda = str(item.get("CODVENDA") or "").strip()
                codproduto = str(item.get("CODPRODUTO") or "").strip()
                if not codvenda or not codproduto:
                    stats["skipped_no_transaction"] += 1
                    continue

                # Resolve transaction_id
                tx_id = transaction_lookup.get(codvenda)
                if tx_id is None:
                    # Fallback: tenta sem o suffix -N
                    if "-" in codvenda:
                        tx_id = transaction_lookup.get(codvenda.split("-")[0])
                if tx_id is None:
                    stats["skipped_no_transaction"] += 1
                    continue

                # Resolve product
                prod = product_lookup.get(codproduto)
                if prod is None:
                    stats["skipped_no_product"] += 1
                    continue
                product_id, variation_id = prod

                quantity = normalize_decimal(item.get("QUANT")) or 1.0
                unit_price = normalize_decimal(item.get("VALOR"))
                # item_tax simplificado: somatório dos impostos do item / quantidade
                item_tax = (
                    normalize_decimal(item.get("VICMS"))
                    + normalize_decimal(item.get("VICMSST"))
                    + normalize_decimal(item.get("IPI_VIPI"))
                ) / max(quantity, 1)

                if args.target == "dry-run":
                    stats["inserts"] += 1
                    continue

                assert con is not None
                with con.cursor() as wcur:
                    # Limpa items prévios da transaction (idempotente — replace set-based)
                    if tx_id not in already_cleared:
                        wcur.execute(
                            "DELETE FROM transaction_sell_lines WHERE transaction_id=%s",
                            (tx_id,),
                        )
                        already_cleared.add(tx_id)
                        stats["transactions_processadas"] += 1

                    # INSERT IGNORE aproveita UK uk_tsl_dup_prevent
                    # (transaction_id, product_id, variation_id) — evita dup
                    # mesmo em re-run ou execução paralela. Mais rápido que SELECT prévio.
                    wcur.execute(
                        "INSERT IGNORE INTO transaction_sell_lines "
                        "(transaction_id, product_id, variation_id, quantity, "
                        " unit_price, unit_price_inc_tax, item_tax, created_at, updated_at) "
                        "VALUES (%s, %s, %s, %s, %s, %s, %s, NOW(), NOW())",
                        (tx_id, product_id, variation_id, quantity,
                         unit_price, unit_price + item_tax, item_tax),
                    )
                    if wcur.rowcount > 0:
                        stats["inserts"] += 1
                    else:
                        stats.setdefault("skipped_uk_dup", 0)
                        stats["skipped_uk_dup"] += 1

                batch_count += 1
                if batch_count >= BATCH_SIZE:
                    # Retry on deadlock (MySQL 1213)
                    for attempt in range(3):
                        try:
                            con.commit()
                            break
                        except pymysql.err.OperationalError as e:
                            if e.args[0] == 1213 and attempt < 2:
                                print(f"  [deadlock retry {attempt+1}/3]", flush=True)
                                import time; time.sleep(0.5 * (attempt + 1))
                                continue
                            raise
                    print(f"  [batch commit] lidos={stats['lidos']} inserts={stats['inserts']} transactions_clr={stats['transactions_processadas']}", flush=True)
                    batch_count = 0

            if con:
                con.commit()
                print("[OK] Commit MySQL final", flush=True)
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
    audit_path = out / f"audit-venda-itens-biz{args.target_business}-{ts}.json"
    audit_path.write_text(
        json.dumps({
            "importer_version": IMPORTER_VERSION,
            "business_id": args.target_business,
            "target": args.target,
            "filter": {"start_date": args.start_date, "end_date": args.end_date},
            "stats": stats,
        }, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"\n[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  Lidos                    : {stats['lidos']}")
    print(f"  Transactions processadas : {stats['transactions_processadas']}")
    print(f"  Items INSERT             : {stats['inserts']}")
    print(f"  Skipped (sem transaction): {stats['skipped_no_transaction']}")
    print(f"  Skipped (sem product)    : {stats['skipped_no_product']}")
    print(f"  Errors                   : {stats['errors']}")
    print(f"[OK] VENDA_PRODUTO concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
