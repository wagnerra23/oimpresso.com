"""
Fase 6.1 — Importer PRODUTO_ESTOQUE Delphi WR Comercial → variation_location_details.qty_available.

Lê tabela PRODUTO_ESTOQUE do Firebird (4.581 rows · 8 cols Martinho v1404)
e ATUALIZA `variation_location_details.qty_available` em business alvo.

Pré-requisito: `import-produtos.py` rodado ANTES (cria products + variations + VLD com qty=0).
Se products não existir → skip + log warn.

UPDATE only — não cria rows (products já criados pelo import-produtos).
Defensive INSERT: se products existe mas VLD não existe (raro), cria.

Idempotente — re-rodar = mesmo qty_available (overwrite seguro).

Filtro default: nenhum (Martinho v1404 não tem distinção armazém — PRINCIPAL é NUMERIC,
não 'S'/'N'). Pra clientes v1474+ que tenham PRINCIPAL='S', usar --filter-principal-s.

Lookup: PRODUTO_ESTOQUE.CODPRODUTO → products.officeimpresso_codigo → product_id.

Agregação: se múltiplos rows pra mesmo CODPRODUTO (armazéns diferentes),
SOMA ESTOQUE → qty_available total único (UltimatePOS multi-location ficará pra refactor depois).

Multi-tenant Tier 0 (ADR 0093): impõe `business_id` no SELECT/UPDATE.

Adapter por versão Firebird (v1404 Martinho → v1474 Zoom canônica):
   - Lê cols presentes via `SELECT FIRST 1 *` em runtime
   - Cols ausentes vs canônica viram NULL (não quebra)

Uso:
    # Dry-run (gera SQL preview + audit, não toca DB)
    python import-estoque.py --alias MartinhoServidor --target-business 164

    # Local Laragon (Herd dev)
    python import-estoque.py --alias MartinhoServidor --target-business 164 --target local

    # Hostinger prod (perigoso — exige --confirm)
    python import-estoque.py --alias MartinhoServidor --target-business 164 --target prod --confirm

    # Filtro PRINCIPAL='S' (clientes v1474+ que tenham flag 'S'/'N' textual)
    python import-estoque.py --alias MartinhoServidor --target-business 164 --filter-principal-s

Refs:
    - memory/reference/migracao-officeimpresso-pattern.md §Fase 6.1
    - ADR 0093 — Multi-tenant Tier 0
    - import-produtos.py (pré-req — cria VLD rows)
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
SYNC_TYPE_DEFAULT = "estoque"

# Cols canônicas PRODUTO_ESTOQUE (Martinho v1404). Adapter detecta ausentes.
PRODUTO_ESTOQUE_COLS = [
    "CODIGO",         # PK Delphi
    "CODPRODUTO",     # FK → PRODUTO.CODIGO → resolve via products.officeimpresso_codigo
    "CODEMPRESA",     # business legacy (audit)
    "ESTOQUE",        # → variation_location_details.qty_available
    "PRINCIPAL",      # filtro PRINCIPAL='S'
    "DT_ALTERACAO",   # audit
    "OIMPRESSO_CODIGO",
    "OIMPRESSO_DT_ALTERACAO",
]


def get_existing_cols(con, table: str) -> set[str]:
    cur = con.cursor()
    try:
        cur.execute(f"SELECT FIRST 1 * FROM {table}")
        return {d[0] for d in cur.description or []}
    finally:
        cur.close()


def build_adapted_select(cols_presentes: set[str], cols_canonicas: list[str]) -> str:
    parts = []
    for c in cols_canonicas:
        if c in cols_presentes:
            parts.append(f"P.{c}")
        else:
            parts.append(f"CAST(NULL AS VARCHAR(1)) AS {c}")
    return ", ".join(parts)


def normalize_decimal(raw) -> float:
    if raw is None or raw == "":
        return 0.0
    try:
        return float(raw)
    except (ValueError, TypeError):
        return 0.0


def normalize_str(raw) -> str | None:
    if raw is None:
        return None
    s = str(raw).strip()
    return s or None


def sql_value(v) -> str:
    if v is None:
        return "NULL"
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime):
        return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    s = str(v).replace("'", "''")
    return "'" + s + "'"


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--alias", required=True, help="Alias Firebird HKCU")
    parser.add_argument("--target-business", type=int, required=True)
    parser.add_argument("--target", choices=["dry-run", "local", "prod"], default="dry-run")
    parser.add_argument("--limit", type=int, default=0)
    parser.add_argument("--batch-size", type=int, default=500)
    parser.add_argument("--location-default", type=int, default=1, help="location_id alvo")
    parser.add_argument("--filter-principal-s", action="store_true",
                        help="Aplica filtro PRINCIPAL='S' (opt-in pra clientes v1474+ onde PRINCIPAL é VARCHAR 'S'/'N'). "
                             "Default OFF: Martinho v1404 tem PRINCIPAL NUMERIC, soma todos rows por CODPRODUTO.")
    parser.add_argument("--mysql-host", default=os.environ.get("MYSQL_HOST", "127.0.0.1"))
    parser.add_argument("--mysql-port", type=int, default=int(os.environ.get("MYSQL_PORT", "3306")))
    parser.add_argument("--mysql-user", default=os.environ.get("MYSQL_USER", "root"))
    parser.add_argument("--mysql-password", default=os.environ.get("MYSQL_PASSWORD", ""))
    parser.add_argument("--mysql-database", default=os.environ.get("MYSQL_DATABASE", "oimpresso"))
    parser.add_argument("--firebird-password", default=os.environ.get("FIREBIRD_PASSWORD", "masterkey"))
    parser.add_argument("--confirm", action="store_true")
    parser.add_argument("--output-dir", default="scripts/legacy-migration/output")
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
        print("[ERRO] --target prod requer --confirm explícito", file=sys.stderr)
        return 2

    print(f"== Importer ESTOQUE v{IMPORTER_VERSION} ==")
    print(f"  Alias Firebird       : {args.alias}")
    print(f"  business_id alvo     : {args.target_business}")
    print(f"  Target MySQL         : {args.target}")
    print(f"  location_id alvo     : {args.location_default}")
    print(f"  Filtro PRINCIPAL='S' : {'SIM (opt-in v1474+)' if args.filter_principal_s else 'NÃO (default — soma todos rows por CODPRODUTO)'}")
    if args.limit:
        print(f"  Limit rows           : {args.limit}")

    stats = {
        "lidos": 0,
        "updates": 0,
        "skipped_principal_n": 0,
        "skipped_produto_nao_encontrado": 0,
        "skipped_vld_nao_existe": 0,
        "skipped_qty_zero": 0,
        "soma_estoque_total": 0.0,
        "produtos_atualizados": 0,
        "errors": 0,
    }

    audit_records: list[dict] = []
    sample_updates: list[str] = []
    dry_run_lines: list[str] = [
        f"-- Generated by import-estoque v{IMPORTER_VERSION}",
        f"-- Generated at: {datetime.utcnow().isoformat()}Z",
        f"-- Target: {args.target}  business={args.target_business}  alias={args.alias}",
        "",
        "-- UPDATE only — products + variations + VLD precisam existir (import-produtos.py rodou antes).",
        "-- Skip se produto não tem officeimpresso_codigo em biz alvo.",
        "-- Lookup: PRODUTO_ESTOQUE.CODPRODUTO → products.officeimpresso_codigo (biz=N) → variation_id → VLD.qty_available",
        "",
    ]

    # Carrega product lookup (CODPRODUTO Delphi → variation_id MySQL)
    product_lookup: dict[str, dict] = {}  # {legacy_codigo: {product_id, variation_id, product_variation_id}}

    print("\n[MySQL] Carregando product_lookup...")
    if args.target in ("local", "prod"):
        if pymysql is None:
            print("[ERRO] pymysql não instalado", file=sys.stderr)
            return 3
        lookup_con = pymysql.connect(
            host=args.mysql_host, port=args.mysql_port,
            user=args.mysql_user, password=args.mysql_password,
            database=args.mysql_database, charset="utf8mb4",
            cursorclass=pymysql.cursors.DictCursor,
        )
        try:
            with lookup_con.cursor() as cur:
                cur.execute(
                    """
                    SELECT p.id AS product_id, p.officeimpresso_codigo,
                           v.id AS variation_id, v.product_variation_id
                    FROM products p
                    INNER JOIN variations v ON v.product_id = p.id
                    WHERE p.business_id = %s
                      AND p.officeimpresso_codigo IS NOT NULL
                      AND p.deleted_at IS NULL
                    """,
                    (args.target_business,),
                )
                for r in cur.fetchall():
                    product_lookup[str(r["officeimpresso_codigo"])] = {
                        "product_id": int(r["product_id"]),
                        "variation_id": int(r["variation_id"]),
                        "product_variation_id": int(r["product_variation_id"]),
                    }
        finally:
            lookup_con.close()
        print(f"[Product lookup] {len(product_lookup)} produtos com officeimpresso_codigo em biz={args.target_business}")
        if not product_lookup:
            print("[ERRO] Nenhum product encontrado — rode import-produtos.py ANTES!", file=sys.stderr)
            return 4
    else:
        print("[Product lookup] dry-run · simulado vazio (lookup feito no Firebird mesmo)")

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
        cols_existentes = get_existing_cols(fb_con, "PRODUTO_ESTOQUE")
        cols_ausentes = [c for c in PRODUTO_ESTOQUE_COLS if c not in cols_existentes]
        print(f"[Adapter] PRODUTO_ESTOQUE cols presentes: {len(cols_existentes)} · ausentes: {len(cols_ausentes)}")
        if cols_ausentes:
            print(f"          Ausentes (NULL): {cols_ausentes}")

        # Delta filter — DT_ALTERACAO ausente em algumas versões → fallback FULL SYNC com warn
        has_dt_alteracao = "DT_ALTERACAO" in cols_existentes
        if delta_active and not has_dt_alteracao:
            print(
                "[delta WARN] PRODUTO_ESTOQUE não tem DT_ALTERACAO — fallback FULL SYNC",
                file=sys.stderr,
            )
            delta_active = False

        # Filtro PRINCIPAL='S' apenas se opt-in (v1474+).
        # Default OFF: Martinho v1404 tem PRINCIPAL NUMERIC (mesmo valor de ESTOQUE em vez de flag).
        where_parts = []
        params: list = []
        if args.filter_principal_s and "PRINCIPAL" in cols_existentes:
            where_parts.append("P.PRINCIPAL = 'S'")
        if delta_active and delta_since is not None:
            where_parts.append("P.DT_ALTERACAO > ?")
            params.append(sc.format_firebird_timestamp(delta_since))
        where_clause = ("WHERE " + " AND ".join(where_parts)) if where_parts else ""

        select_clause = build_adapted_select(cols_existentes, PRODUTO_ESTOQUE_COLS)
        first_clause = f"FIRST {args.limit}" if args.limit > 0 else ""
        sql = f"""
            SELECT {first_clause} {select_clause}
            FROM PRODUTO_ESTOQUE P
            {where_clause}
            ORDER BY P.CODPRODUTO
        """
        print(f"\n[Query PRODUTO_ESTOQUE] {sql.strip()[:200]}...")
        cur = fb_con.cursor()
        cur.execute(sql, tuple(params))
        col_names = [d[0] for d in cur.description]

        # Agrega estoque por CODPRODUTO (caso --all-warehouses, soma)
        estoque_agregado: dict[str, float] = {}
        rows_originais: dict[str, list[dict]] = {}

        for row in cur:
            stats["lidos"] += 1
            estoque_row = dict(zip(col_names, row))

            cod_produto = normalize_str(estoque_row.get("CODPRODUTO"))
            if not cod_produto:
                continue

            # PRINCIPAL filter (opt-in via --filter-principal-s só pra v1474+ textual)
            if args.filter_principal_s:
                principal = (str(estoque_row.get("PRINCIPAL") or "")).upper().strip()
                if principal != "S":
                    stats["skipped_principal_n"] += 1
                    continue

            qty = normalize_decimal(estoque_row.get("ESTOQUE"))
            estoque_agregado[cod_produto] = estoque_agregado.get(cod_produto, 0.0) + qty
            rows_originais.setdefault(cod_produto, []).append(estoque_row)

            if stats["lidos"] % 1000 == 0:
                print(f"  ... lidos {stats['lidos']}")

        cur.close()
        print(f"[Agregação] {len(estoque_agregado)} CODPRODUTO únicos com estoque")

        # MySQL writer pra UPDATE
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
            for cod_produto, qty_total in sorted(estoque_agregado.items()):
                stats["soma_estoque_total"] += qty_total

                # Lookup product_id via officeimpresso_codigo
                lookup = product_lookup.get(cod_produto)
                if not lookup and args.target in ("local", "prod"):
                    stats["skipped_produto_nao_encontrado"] += 1
                    if stats["skipped_produto_nao_encontrado"] <= 3:
                        print(f"  WARN: CODPRODUTO={cod_produto} sem product em biz={args.target_business} (rode import-produtos antes)")
                    audit_records.append({
                        "cod_produto": cod_produto,
                        "qty_total": qty_total,
                        "status": "skipped_produto_nao_encontrado",
                    })
                    continue

                if args.target == "dry-run":
                    # Dry-run: SQL preview UPDATE direto via lookup officeimpresso_codigo
                    sql_str = (
                        f"UPDATE variation_location_details vld "
                        f"INNER JOIN variations v ON v.id = vld.variation_id "
                        f"INNER JOIN products p ON p.id = v.product_id "
                        f"SET vld.qty_available = {sql_value(qty_total)}, vld.updated_at = NOW() "
                        f"WHERE p.business_id = {args.target_business} "
                        f"AND p.officeimpresso_codigo = {sql_value(cod_produto)} "
                        f"AND vld.location_id = {args.location_default};"
                    )
                    if len(sample_updates) < 5:
                        sample_updates.append(sql_str)
                    dry_run_lines.append(
                        f"-- CODPRODUTO={cod_produto} · qty={qty_total} · rows_origem={len(rows_originais.get(cod_produto, []))}"
                    )
                    dry_run_lines.append(sql_str)
                    dry_run_lines.append("")
                    stats["updates"] += 1
                    stats["produtos_atualizados"] += 1
                    if len(audit_records) < 500:
                        audit_records.append({
                            "cod_produto": cod_produto,
                            "qty_total": qty_total,
                            "rows_origem": len(rows_originais.get(cod_produto, [])),
                            "status": "dry-run",
                        })
                else:
                    assert con is not None
                    product_id = lookup["product_id"]
                    variation_id = lookup["variation_id"]
                    product_variation_id = lookup["product_variation_id"]

                    with con.cursor() as wcur:
                        # UPDATE VLD direto (CREATE se não existir — defensivo)
                        # CINTO+SUSPENSÓRIO Tier 0 (ADR 0093): JOIN com products + WHERE business_id
                        # explícito previne contaminação cross-business (incidente 2026-05-14 ROTA LIVRE).
                        # ZERO custo perf — index (product_id, variation_id) já existe.
                        wcur.execute(
                            "SELECT vld.id FROM variation_location_details vld "
                            "INNER JOIN products p ON p.id = vld.product_id "
                            "WHERE p.business_id = %s "
                            "AND vld.product_id=%s AND vld.variation_id=%s AND vld.location_id=%s "
                            "LIMIT 1",
                            (args.target_business, product_id, variation_id, args.location_default),
                        )
                        existing_vld = wcur.fetchone()
                        if existing_vld:
                            # UPDATE também qualificado com double-check business_id via JOIN
                            # pra defender contra race condition / vld.product_id stale
                            wcur.execute(
                                "UPDATE variation_location_details vld "
                                "INNER JOIN products p ON p.id = vld.product_id "
                                "SET vld.qty_available=%s, vld.updated_at=NOW() "
                                "WHERE vld.id=%s AND p.business_id=%s",
                                (qty_total, existing_vld["id"], args.target_business),
                            )
                            if wcur.rowcount == 0:
                                # Defesa final: UPDATE não pegou (vld pertence a outro biz). Log + skip.
                                stats["skipped_cross_business_guard"] = stats.get("skipped_cross_business_guard", 0) + 1
                                if len(audit_records) < 500:
                                    audit_records.append({
                                        "cod_produto": cod_produto,
                                        "qty_total": qty_total,
                                        "status": "BLOCKED_cross_business_guard",
                                        "vld_id_tentado": existing_vld["id"],
                                    })
                                continue
                            stats["updates"] += 1
                        else:
                            # Defensive — VLD não existe, cria (não deveria acontecer se import-produtos rodou)
                            wcur.execute(
                                "INSERT INTO variation_location_details "
                                "(product_id, product_variation_id, variation_id, location_id, qty_available, created_at, updated_at) "
                                "VALUES (%s, %s, %s, %s, %s, NOW(), NOW())",
                                (product_id, product_variation_id, variation_id, args.location_default, qty_total),
                            )
                            stats["updates"] += 1
                            stats["skipped_vld_nao_existe"] += 1  # tracked como defensive insert

                        stats["produtos_atualizados"] += 1

                    if len(audit_records) < 500:
                        audit_records.append({
                            "cod_produto": cod_produto,
                            "qty_total": qty_total,
                            "product_id": product_id,
                            "variation_id": variation_id,
                            "rows_origem": len(rows_originais.get(cod_produto, [])),
                            "status": "updated",
                        })

                    batch_count += 1
                    if batch_count >= args.batch_size:
                        con.commit()
                        print(f"  [batch] commited {batch_count} · total atualizados: {stats['produtos_atualizados']}")
                        batch_count = 0

            if con and batch_count > 0:
                con.commit()
                print(f"  [batch final] commited {batch_count}")
            if con and args.delta_since_last_sync:
                try:
                    sc.mark_success(
                        con,
                        args.target_business,
                        args.sync_type,
                        rows_processed=stats["produtos_atualizados"],
                    )
                    con.commit()
                except Exception as e:
                    print(f"[sync_checkpoint] WARN mark_success: {e!r}", file=sys.stderr)

        except Exception as e:
            if con:
                con.rollback()
                print(f"\n[ERRO] Rollback: {e}", file=sys.stderr)
                if args.delta_since_last_sync:
                    try:
                        sc.mark_failed(con, args.target_business, args.sync_type, error_msg=repr(e))
                        con.commit()
                    except Exception as e2:
                        print(f"[sync_checkpoint] WARN mark_failed: {e2!r}", file=sys.stderr)
            stats["errors"] += 1
            raise
        finally:
            if con:
                con.close()

    out = Path(args.output_dir)
    out.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d-%H%M%S")
    biz_suffix = f"biz{args.target_business}"

    if args.target == "dry-run":
        sql_path = out / f"dry-run-estoque-{biz_suffix}-{ts}.sql"
        sql_path.write_text("\n".join(dry_run_lines), encoding="utf-8")
        print(f"\n[SQL preview] {sql_path}")

    audit_path = out / f"audit-estoque-{biz_suffix}-{ts}.json"
    audit_summary = {
        "importer_version": IMPORTER_VERSION,
        "alias": args.alias,
        "business_id": args.target_business,
        "target": args.target,
        "filter": {"limit": args.limit, "filter_principal_s": args.filter_principal_s},
        "adapter": {
            "cols_canonicas_pedidas": len(PRODUTO_ESTOQUE_COLS),
            "cols_ausentes_v_canonica": cols_ausentes,
        },
        "stats": stats,
        "sample_updates": sample_updates,
        "records_total": len(audit_records),
        "records_sample_first_500": audit_records[:500],
    }
    audit_path.write_text(
        json.dumps(audit_summary, ensure_ascii=False, indent=2, default=str),
        encoding="utf-8",
    )
    print(f"[Audit JSON] {audit_path}")

    print("\n== Relatório ==")
    print(f"  PRODUTO_ESTOQUE lidos       : {stats['lidos']}")
    print(f"  Skipped PRINCIPAL!='S'      : {stats['skipped_principal_n']}")
    print(f"  Produtos únicos agregados   : {len(estoque_agregado) if 'estoque_agregado' in dir() else 0}")
    print(f"  Produtos atualizados        : {stats['produtos_atualizados']}")
    print(f"  Skipped produto não encontr.: {stats['skipped_produto_nao_encontrado']}")
    print(f"  Updates VLD                 : {stats['updates']}")
    print(f"  VLD defensive insert        : {stats['skipped_vld_nao_existe']}")
    print(f"  Soma estoque total          : {stats['soma_estoque_total']:.2f}")
    print(f"  Errors                      : {stats['errors']}")
    print(f"\n[OK] Estoque concluido (v{IMPORTER_VERSION}, target={args.target})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
